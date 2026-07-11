<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\Services\TrainingService;
use App\TrainingRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentDashboardController extends Controller
{
    protected $training;

    public function __construct(TrainingService $training)
    {
        $this->training = $training;
    }

    public function dashboard()
    {
        $user = Auth::guard('beyond')->user();
        $profile = BeyondProfile::find($user->id);

        $registrations = $this->training->registrationsForUser($user);
        $progress = $this->training->progressForRegistrations($registrations->pluck('id')->all());

        $enrolledCount = $progress->count();
        $completedCount = $progress->where('status', 'completed')->count();
        $avgProgress = $progress->count() ? round($progress->avg('progress_percentage')) : 0;

        return view('beyond.student.dashboard', compact(
            'user', 'profile', 'registrations', 'progress',
            'enrolledCount', 'completedCount', 'avgProgress'
        ));
    }

    public function progress()
    {
        $user = Auth::guard('beyond')->user();
        $registrations = $this->training->registrationsForUser($user);
        $progress = $this->training->progressForRegistrations($registrations->pluck('id')->all());

        $feedbackMap = [];
        foreach ($progress as $item) {
            $feedbackMap[$item->id] = $this->training->hasFeedback($item->registration_id, $item->course_id);
        }

        return view('beyond.student.progress', compact('user', 'progress', 'feedbackMap'));
    }

    public function submitFeedback(Request $request)
    {
        $validated = $request->validate([
            'registration_id' => 'required|string',
            'course_id' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'feedback_text' => 'nullable|string|max:2000',
        ]);

        $user = Auth::guard('beyond')->user();

        $owns = TrainingRegistration::where('id', $validated['registration_id'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (! empty($user->email)) {
                    $q->orWhere('client_email', $user->email);
                }
            })->exists();

        if (! $owns) {
            return back()->withErrors(['feedback' => 'Registration not found for your account.']);
        }

        if ($this->training->hasFeedback($validated['registration_id'], $validated['course_id'])) {
            return back()->with('status', 'You have already submitted feedback for this course.');
        }

        $this->training->submitFeedback([
            'registration_id' => $validated['registration_id'],
            'course_id' => $validated['course_id'],
            'student_name' => $user->name ?: $user->email,
            'rating' => $validated['rating'],
            'feedback_text' => $validated['feedback_text'] ?? null,
        ]);

        return back()->with('status', 'Thank you for your feedback!');
    }
}
