<?php

namespace App\Services;

use App\Course;
use App\CourseCertificate;
use App\CourseFeedback;
use App\CourseInvoice;
use App\StudentProgress;
use App\TrainingRegistration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CourseManagerService
{
    protected $training;

    public function __construct(TrainingService $training)
    {
        $this->training = $training;
    }

    public function ensureSeeded()
    {
        $this->training->ensureCoursesSeeded();
    }

    /** Public trainings page shape from DB courses. */
    public function programsForFrontend()
    {
        $this->ensureSeeded();
        $courses = Course::where('status', 'active')->orderBy('sort_order')->orderBy('name')->get();
        if ($courses->isEmpty()) {
            return [];
        }

        return $courses->map(function ($c, $i) {
            return [
                'id' => $c->legacy_id ?: ($i + 1),
                'uuid' => $c->id,
                'title' => $c->name,
                'duration' => $c->duration ?: '',
                'deliveryMode' => $c->delivery_mode ?: '',
                'category' => $c->category ?: 'Training',
                'color' => $c->color ?: '#003D82',
                'icon' => $c->icon ?: 'Briefcase',
                'description' => $c->description,
                'price' => (float) $c->price,
                'sections' => \App\Support\CourseOutline::enrich($c->name, $c->sections),
            ];
        })->values()->all();
    }

    public function allCourses($q = null)
    {
        $this->ensureSeeded();
        $query = Course::query()->orderBy('sort_order')->orderBy('name');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%')
                    ->orWhere('category', 'like', '%' . $q . '%');
            });
        }

        return $query->get();
    }

    public function createCourse(array $data)
    {
        $max = (int) Course::max('sort_order');

        return Course::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::random(4),
            'description' => $data['description'] ?? null,
            'price' => (float) ($data['price'] ?? 0),
            'duration' => $data['duration'] ?? null,
            'delivery_mode' => $data['delivery_mode'] ?? null,
            'category' => $data['category'] ?? 'Training',
            'curriculum_json' => isset($data['curriculum_json']) ? $data['curriculum_json'] : null,
            'icon' => $data['icon'] ?? 'Briefcase',
            'color' => $data['color'] ?? '#003D82',
            'sort_order' => $max + 1,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    public function updateCourse(Course $course, array $data)
    {
        $course->fill([
            'name' => $data['name'] ?? $course->name,
            'slug' => isset($data['name']) ? Str::slug($data['name']) : $course->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $course->description,
            'price' => array_key_exists('price', $data) ? (float) $data['price'] : $course->price,
            'duration' => array_key_exists('duration', $data) ? $data['duration'] : $course->duration,
            'delivery_mode' => array_key_exists('delivery_mode', $data) ? $data['delivery_mode'] : $course->delivery_mode,
            'category' => array_key_exists('category', $data) ? $data['category'] : $course->category,
            'icon' => array_key_exists('icon', $data) ? $data['icon'] : $course->icon,
            'color' => array_key_exists('color', $data) ? $data['color'] : $course->color,
            'status' => array_key_exists('status', $data) ? $data['status'] : $course->status,
        ]);
        if (array_key_exists('curriculum_json', $data)) {
            $course->curriculum_json = $data['curriculum_json'];
        }
        $course->save();

        return $course;
    }

    public function deleteCourse(Course $course)
    {
        return $course->delete();
    }

    public function moveCourse(Course $course, $direction)
    {
        $courses = Course::orderBy('sort_order')->orderBy('name')->get()->values();
        $index = $courses->search(function ($c) use ($course) {
            return $c->id === $course->id;
        });
        if ($index === false) {
            return;
        }

        $target = $index;
        if ($direction === 'top') {
            $target = 0;
        } elseif ($direction === 'up') {
            $target = max(0, $index - 1);
        } elseif ($direction === 'down') {
            $target = min($courses->count() - 1, $index + 1);
        } elseif ($direction === 'bottom') {
            $target = $courses->count() - 1;
        }

        if ($target === $index) {
            return;
        }

        $item = $courses->pull($index);
        $courses = $courses->values();
        $courses->splice($target, 0, [$item]);
        foreach ($courses->values() as $i => $c) {
            Course::where('id', $c->id)->update(['sort_order' => $i]);
        }
    }

    public function reorderCourses(array $orderedIds)
    {
        foreach (array_values($orderedIds) as $i => $id) {
            Course::where('id', $id)->update(['sort_order' => $i]);
        }
    }

    public function cloneCourse(Course $source)
    {
        $max = (int) Course::max('sort_order');
        $name = 'Copy of ' . $source->name;

        return Course::create([
            'id' => (string) Str::uuid(),
            'legacy_id' => null,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'description' => $source->description,
            'price' => $source->price,
            'duration' => $source->duration,
            'delivery_mode' => $source->delivery_mode,
            'category' => $source->category,
            'curriculum_json' => $source->curriculum_json,
            'icon' => $source->icon,
            'color' => $source->color,
            'sort_order' => $max + 1,
            'status' => 'draft',
        ]);
    }

    public function registrations($q = null, $status = null)
    {
        $query = TrainingRegistration::query()->orderByDesc('created_at');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('client_name', 'like', '%' . $q . '%')
                    ->orWhere('client_email', 'like', '%' . $q . '%')
                    ->orWhere('client_phone', 'like', '%' . $q . '%')
                    ->orWhere('reference_number', 'like', '%' . $q . '%')
                    ->orWhere('course_names', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(40);
    }

    public function registrationStats()
    {
        return [
            'total' => TrainingRegistration::count(),
            'revenue' => (float) TrainingRegistration::whereIn('status', ['confirmed', 'completed'])->sum('total_price'),
            'confirmed' => TrainingRegistration::where('status', 'confirmed')->count(),
            'pending' => TrainingRegistration::where('status', 'pending')->count(),
        ];
    }

    public function updateRegistration(TrainingRegistration $reg, array $data)
    {
        if (isset($data['status'])) {
            $reg->status = $data['status'];
        }
        if (isset($data['payment_status'])) {
            $reg->payment_status = $data['payment_status'];
        }
        $reg->save();

        if (($data['status'] ?? '') === 'confirmed' && $reg->payment_status === 'paid') {
            $this->ensureInvoiceForRegistration($reg);
        }

        return $reg;
    }

    public function ensureInvoiceForRegistration(TrainingRegistration $reg)
    {
        $existing = CourseInvoice::where('registration_id', $reg->id)->first();
        if ($existing) {
            return $existing;
        }

        $total = (float) ($reg->total_price ?: 0);

        return CourseInvoice::create([
            'invoice_number' => $this->nextInvoiceNumber(),
            'registration_id' => $reg->id,
            'client_name' => $reg->client_name,
            'email' => $reg->client_email,
            'courses_json' => json_encode([['name' => $reg->course_names, 'amount' => $total]]),
            'subtotal' => $total,
            'tax' => 0,
            'total' => $total,
            'payment_status' => $reg->payment_status ?: 'pending',
            'payment_date' => $reg->payment_status === 'paid' ? now() : null,
        ]);
    }

    public function nextInvoiceNumber()
    {
        $n = CourseInvoice::count() + 1;

        return 'INV-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function invoices($q = null)
    {
        $query = CourseInvoice::query()->orderByDesc('created_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('invoice_number', 'like', '%' . $q . '%')
                    ->orWhere('client_name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(40);
    }

    public function certificates($q = null)
    {
        $query = CourseCertificate::query()->orderByDesc('created_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('certificate_number', 'like', '%' . $q . '%')
                    ->orWhere('student_name', 'like', '%' . $q . '%')
                    ->orWhere('course_name', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(40);
    }

    public function issueCertificate(StudentProgress $progress, TrainingRegistration $reg = null)
    {
        $reg = $reg ?: TrainingRegistration::find($progress->registration_id);
        $existing = CourseCertificate::where('registration_id', $progress->registration_id)
            ->where('course_id', $progress->course_id)
            ->where('status', 'active')
            ->first();
        if ($existing) {
            return $existing;
        }

        return CourseCertificate::create([
            'certificate_number' => $this->nextCertificateNumber(),
            'registration_id' => $progress->registration_id,
            'course_id' => $progress->course_id,
            'student_name' => $reg ? $reg->client_name : 'Student',
            'course_name' => $progress->course_name ?: 'Course',
            'completion_date' => $progress->completion_date ?: now(),
            'status' => 'active',
        ]);
    }

    public function nextCertificateNumber()
    {
        $n = CourseCertificate::count() + 1;

        return 'CERT-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function revokeCertificate(CourseCertificate $cert)
    {
        $cert->status = 'revoked';
        $cert->revoked_at = now();
        $cert->save();

        return $cert;
    }

    public function progressList($q = null)
    {
        $query = StudentProgress::query()->orderByDesc('updated_at');
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('course_name', 'like', '%' . $q . '%');
            });
        }
        $rows = $query->paginate(50);
        $regIds = $rows->pluck('registration_id')->unique()->filter()->all();
        $regs = TrainingRegistration::whereIn('id', $regIds)->get()->keyBy('id');
        $rows->getCollection()->transform(function ($p) use ($regs) {
            $p->student_name = optional($regs->get($p->registration_id))->client_name;

            return $p;
        });

        return $rows;
    }

    public function progressStats()
    {
        return [
            'completed' => StudentProgress::where('status', 'completed')->count(),
            'avg' => (float) StudentProgress::avg('progress_percentage'),
        ];
    }

    public function updateProgress(StudentProgress $progress, array $data)
    {
        if (isset($data['progress_percentage'])) {
            $progress->progress_percentage = min(100, max(0, (float) $data['progress_percentage']));
        }
        if (isset($data['status'])) {
            $progress->status = $data['status'];
        }
        if ($progress->progress_percentage >= 100) {
            $progress->status = 'completed';
            $progress->completion_date = $progress->completion_date ?: now();
        }
        if (Schema::hasColumn('student_progress', 'last_updated')) {
            $progress->last_updated = now();
        }
        $progress->save();

        if ($progress->status === 'completed') {
            $reg = TrainingRegistration::find($progress->registration_id);
            if ($reg) {
                $this->issueCertificate($progress, $reg);
            }
        }

        return $progress;
    }

    public function feedbackList($q = null, $courseId = null, $rating = null)
    {
        $query = CourseFeedback::query()->orderByDesc('created_at');
        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        if ($rating) {
            $query->where('rating', (int) $rating);
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('student_name', 'like', '%' . $q . '%')
                    ->orWhere('feedback_text', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(40);
    }

    public function deleteFeedback($id)
    {
        return CourseFeedback::where('id', $id)->delete();
    }

    public function feedbackForCourse($courseId)
    {
        return CourseFeedback::where('course_id', $courseId)->orderByDesc('created_at')->get();
    }
}
