<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
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
}
