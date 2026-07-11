<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use Carbon\Carbon;

class JobService
{
    public function activeJobs($search = null)
    {
        $query = JobPosting::whereIn('status', ['active', 'open']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function find($id)
    {
        return JobPosting::find($id);
    }

    public function stats(JobPosting $job)
    {
        $count = Application::where('job_id', $job->id)->count();
        $latest = Application::where('job_id', $job->id)->orderByDesc('created_at')->first();

        return [
            'total_applicants' => $count,
            'expected_applicants' => $job->expected_applicants ?: 50,
            'last_application_date' => $latest ? Carbon::parse($latest->created_at)->diffForHumans() : 'No applications yet',
            'available_spots' => max(0, ($job->expected_applicants ?: 50) - $count),
        ];
    }

    public function availability(JobPosting $job)
    {
        if (! in_array($job->status, ['active', 'open'], true)) {
            return ['available' => false, 'reason' => 'This position is currently closed.'];
        }

        if ($job->deadline && $job->deadline->isPast()) {
            return ['available' => false, 'reason' => 'The application deadline has passed.'];
        }

        if ($job->max_applicants) {
            $count = Application::where('job_id', $job->id)->count();
            if ($count >= $job->max_applicants) {
                return ['available' => false, 'reason' => 'This position has reached its application limit.'];
            }
        }

        return ['available' => true];
    }

    public function incrementApplicants(JobPosting $job)
    {
        $job->increment('current_applicants');
    }
}
