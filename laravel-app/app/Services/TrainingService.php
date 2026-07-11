<?php

namespace App\Services;

use App\Course;
use App\CourseFeedback;
use App\StudentProgress;
use App\TrainingRegistration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TrainingService
{
    public function courses()
    {
        $this->ensureCoursesSeeded();

        return Course::where('status', 'active')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function ensureCoursesSeeded()
    {
        if (Course::count() > 0) {
            return;
        }

        $path = config_path('training-modules.json');
        if (! File::exists($path)) {
            return;
        }

        $modules = json_decode(File::get($path), true) ?: [];
        foreach ($modules as $i => $module) {
            Course::create([
                'id' => (string) Str::uuid(),
                'legacy_id' => $module['id'] ?? null,
                'name' => $module['title'] ?? 'Untitled Course',
                'slug' => Str::slug($module['title'] ?? 'course-'.$i),
                'category' => $module['category'] ?? null,
                'icon' => $module['icon'] ?? null,
                'color' => $module['color'] ?? '#003D82',
                'duration' => $module['duration'] ?? null,
                'delivery_mode' => $module['deliveryMode'] ?? null,
                'curriculum_json' => isset($module['sections']) ? json_encode($module['sections']) : null,
                'price' => 0,
                'sort_order' => $i,
                'status' => 'active',
            ]);
        }
    }

    public function generateReferenceNumber()
    {
        do {
            $ref = 'REG-'.date('Y').'-'.date('md').'-'.random_int(10000, 99999);
        } while (TrainingRegistration::where('reference_number', $ref)->exists());

        return $ref;
    }

    public function register(array $data, array $courseIds, $userId = null)
    {
        $courses = Course::whereIn('id', $courseIds)->get();
        $names = $courses->pluck('name')->all();
        $total = $courses->sum(function ($c) {
            return (float) $c->price;
        });

        $registration = TrainingRegistration::create([
            'id' => (string) Str::uuid(),
            'reference_number' => $this->generateReferenceNumber(),
            'client_name' => trim($data['client_name']),
            'client_email' => trim($data['client_email']),
            'client_phone' => $data['client_phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'course_ids' => json_encode($courses->pluck('id')->all()),
            'course_names' => implode(', ', $names),
            'total_price' => $total > 0 ? $total : null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'user_id' => $userId,
        ]);

        foreach ($courses as $course) {
            StudentProgress::create([
                'id' => (string) Str::uuid(),
                'registration_id' => $registration->id,
                'course_id' => $course->id,
                'course_name' => $course->name,
                'progress_percentage' => 0,
                'status' => 'not_started',
                'start_date' => now(),
            ]);
        }

        return $registration;
    }

    public function registrationsForUser($user)
    {
        $query = TrainingRegistration::query();
        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if (! empty($user->email)) {
                $q->orWhere('client_email', $user->email);
            }
        });

        return $query->orderByDesc('created_at')->get();
    }

    public function progressForRegistrations($registrationIds)
    {
        if (empty($registrationIds)) {
            return collect();
        }

        return StudentProgress::whereIn('registration_id', $registrationIds)
            ->orderBy('created_at')
            ->get();
    }

    public function submitFeedback(array $data)
    {
        return CourseFeedback::create([
            'id' => (string) Str::uuid(),
            'registration_id' => $data['registration_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'student_name' => $data['student_name'] ?? null,
            'rating' => $data['rating'] ?? 5,
            'feedback_text' => $data['feedback_text'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function hasFeedback($registrationId, $courseId)
    {
        return CourseFeedback::where('registration_id', $registrationId)
            ->where('course_id', $courseId)
            ->exists();
    }
}
