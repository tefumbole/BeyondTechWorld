<?php

namespace App\Http\Controllers;

use App\Application;
use App\InternshipEnrolment;
use App\Services\ApplicationService;
use App\Services\Internship\InternshipProgramService;
use App\Support\InternCompliance;
use App\User;
use Illuminate\Support\Facades\Auth;

class ApplicantDashboardController extends Controller
{
    protected $applications;

    public function __construct(ApplicationService $applications)
    {
        $this->applications = $applications;
    }

    public function dashboard()
    {
        $user = Auth::guard('beyond')->user();
        $applications = $this->applications->applicationsForUser($user);

        $active = $applications->whereNotIn('status', ['rejected', 'hired', 'withdrawn'])->values();
        $interviews = $applications->filter(function ($a) {
            return ! empty($a->interview_date);
        })->values();

        $placement = $this->resolvePlacement($user, $applications);
        if ($placement) {
            // Placed interns belong on the ERP student portal, not the job board.
            if ($this->bridgeInternSession($placement['erp_user'])) {
                $open = $placement['progress']['current'] ?? null;
                if ($open) {
                    return redirect()->route('internship.student.task', $open->id);
                }

                return redirect('/admin');
            }

            return view('beyond.applicant.internship', [
                'user' => $user,
                'applications' => $applications,
                'placement' => $placement,
                'progress' => $placement['progress'],
                'enrolment' => $placement['enrolment'],
                'erpUser' => $placement['erp_user'],
            ]);
        }

        return view('beyond.applicant.dashboard', compact('user', 'applications', 'active', 'interviews'));
    }

    public function downloadCv($id)
    {
        $user = Auth::guard('beyond')->user();

        $application = Application::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (! empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })->first();

        if (! $application) {
            abort(404);
        }

        $path = $application->absoluteUploadPath($application->cv_path ?: $application->cv_url);
        if (! $path && $application->cv_path && is_file($application->cv_path)) {
            $path = $application->cv_path; // legacy absolute paths
        }
        if (! $path) {
            abort(404);
        }

        return response()->download($path);
    }

    /**
     * @return array{application:Application,enrolment:InternshipEnrolment,erp_user:User,progress:array}|null
     */
    protected function resolvePlacement($beyondUser, $applications)
    {
        $placed = $applications->first(function ($app) {
            $status = strtolower((string) $app->status);
            if (! in_array($status, ['selected', 'shortlisted', 'hired'], true)) {
                return false;
            }
            $job = $app->job;
            if (! $job) {
                return true;
            }

            return method_exists($job, 'isInternship') ? $job->isInternship() : true;
        });
        if (! $placed) {
            return null;
        }

        $email = strtolower(trim((string) ($beyondUser->email ?? $placed->email ?? '')));
        $erpUser = null;
        if ($email !== '') {
            $erpUser = User::where('is_active', 1)
                ->where(function ($q) {
                    $q->where('is_deleted', 0)->orWhere('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }
        if (! $erpUser || ! InternCompliance::appliesTo($erpUser)) {
            return null;
        }

        $enrolment = InternshipEnrolment::with(['program', 'student'])
            ->where('student_user_id', $erpUser->id)
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->orderByDesc('id')
            ->first();
        if (! $enrolment && ! empty($placed->id)) {
            $enrolment = InternshipEnrolment::with(['program', 'student'])
                ->where('application_id', $placed->id)
                ->orderByDesc('id')
                ->first();
        }
        if (! $enrolment) {
            return null;
        }

        $progress = app(InternshipProgramService::class)->studentProgressSummary($enrolment);

        return [
            'application' => $placed,
            'enrolment' => $enrolment,
            'erp_user' => $erpUser,
            'progress' => $progress,
        ];
    }

    protected function bridgeInternSession(User $erpUser)
    {
        try {
            $erpUser->otp_verify = 1;
            $erpUser->save();
            Auth::guard('web')->login($erpUser, true);

            return Auth::guard('web')->check();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
