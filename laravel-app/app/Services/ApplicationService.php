<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ApplicationService
{
    protected $jobs;

    public function __construct(JobService $jobs)
    {
        $this->jobs = $jobs;
    }

    public function generateReferenceNumber()
    {
        do {
            $ref = 'REF-'.random_int(100000, 999999);
        } while (Application::where('reference_number', $ref)->exists());

        return $ref;
    }

    public function apply(JobPosting $job, array $data, UploadedFile $cv, $userId = null)
    {
        [$cvUrl, $cvPath] = $this->storeCv($job, $cv);

        $fullPhone = $this->combinePhone($data['country_code'] ?? '', $data['phone'] ?? '');

        $application = Application::create([
            'id' => (string) Str::uuid(),
            'job_id' => $job->id,
            'user_id' => $userId,
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone' => $fullPhone,
            'country' => $data['country'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'expected_salary' => $data['expected_salary'] ?? null,
            'availability' => $data['availability'] ?? 'Immediately',
            'availability_days' => ($data['availability'] ?? null) === 'Custom' ? ($data['availability_days'] ?? null) : null,
            'cv_url' => $cvUrl,
            'cv_path' => $cvPath,
            'status' => 'new',
            'reference_number' => $this->generateReferenceNumber(),
            'submitted_at' => now(),
        ]);

        $this->jobs->incrementApplicants($job);

        return $application;
    }

    protected function storeCv(JobPosting $job, UploadedFile $cv)
    {
        $ext = $cv->getClientOriginalExtension() ?: 'pdf';
        $name = 'cv_'.substr($job->id, 0, 8).'_'.time().'_'.Str::random(6).'.'.$ext;
        $dir = 'public/uploads/applications';
        $cv->move($dir, $name);

        $relative = 'uploads/applications/'.$name;

        return ['/'.$relative, $dir.'/'.$name];
    }

    public function combinePhone($code, $number)
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        $digits = ltrim($digits, '0');
        $code = trim((string) $code);
        if ($code === '') {
            return $digits;
        }
        if (strpos($code, '+') !== 0) {
            $code = '+'.$code;
        }

        return $code.$digits;
    }

    public function applicationsForUser($user)
    {
        return Application::with('job')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (! empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function adminList($jobId = null, $status = null, $search = null)
    {
        $q = Application::with('job')->orderByDesc('created_at');
        if ($jobId && $jobId !== 'all') {
            $q->where('job_id', $jobId);
        }
        if ($status && $status !== 'all') {
            $q->where('status', $status);
        }
        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        return $q->paginate(50);
    }

    public function updateStatus(Application $application, array $data)
    {
        $application->status = $data['status'] ?? $application->status;
        if (array_key_exists('rejection_reason', $data)) {
            $application->rejection_reason = $data['rejection_reason'];
        }
        if (array_key_exists('interview_date', $data)) {
            $application->interview_date = $data['interview_date'] ?: null;
        }
        $application->save();

        return $application;
    }

    public function countryCodes()
    {
        return [
            '+250' => 'Rwanda (+250)',
            '+256' => 'Uganda (+256)',
            '+237' => 'Cameroon (+237)',
            '+254' => 'Kenya (+254)',
            '+255' => 'Tanzania (+255)',
            '+234' => 'Nigeria (+234)',
            '+233' => 'Ghana (+233)',
            '+27' => 'South Africa (+27)',
            '+1' => 'USA/Canada (+1)',
            '+44' => 'UK (+44)',
            '+33' => 'France (+33)',
        ];
    }

    public function countryName($code)
    {
        $map = $this->countryCodes();

        if (isset($map[$code])) {
            return trim(preg_replace('/\s*\(.*\)$/', '', $map[$code]));
        }

        return null;
    }
}
