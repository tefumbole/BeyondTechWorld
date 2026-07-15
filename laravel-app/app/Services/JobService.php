<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use Carbon\Carbon;
use Illuminate\Support\Str;

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

    public function allJobs($search = null, $status = null)
    {
        $query = JobPosting::query()->orderByDesc('created_at');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $query->paginate(40);
    }

    public function find($id)
    {
        return JobPosting::find($id);
    }

    public function store(array $data)
    {
        $payload = $this->normalizeJobPayload($data);
        $payload['id'] = (string) Str::uuid();
        $payload['posted_at'] = ($payload['status'] ?? 'active') === 'active' ? now() : null;
        $payload['current_applicants'] = 0;

        return JobPosting::create($payload);
    }

    public function update(JobPosting $job, array $data)
    {
        $payload = $this->normalizeJobPayload($data);
        if (($payload['status'] ?? $job->status) === 'active' && ! $job->posted_at) {
            $payload['posted_at'] = now();
        }
        $job->fill($payload);
        $job->save();

        return $job;
    }

    public function delete(JobPosting $job)
    {
        Application::where('job_id', $job->id)->delete();

        return $job->delete();
    }

    protected function normalizeJobPayload(array $data)
    {
        $type = $data['employment_type'] ?? $data['type'] ?? 'Full-Time';

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'department' => $data['department'] ?? null,
            'employment_type' => $type,
            'type' => $type,
            'salary' => $data['salary'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'qualifications' => $data['qualifications'] ?? null,
            'responsibilities' => $data['responsibilities'] ?? null,
            'min_requirements' => $data['min_requirements'] ?? null,
            'deadline' => ! empty($data['deadline']) ? $data['deadline'] : null,
            'max_positions' => (int) ($data['max_positions'] ?? 1),
            'max_applicants' => isset($data['max_applicants']) && $data['max_applicants'] !== ''
                ? (int) $data['max_applicants'] : null,
            'expected_applicants' => (int) ($data['expected_applicants'] ?? 50),
            'enable_countdown' => ! empty($data['enable_countdown']),
            'status' => $data['status'] ?? 'active',
        ];
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
