<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ApplicationService
{
    protected $jobs;
    protected $notifier;

    public function __construct(JobService $jobs, ApplicationNotifier $notifier)
    {
        $this->jobs = $jobs;
        $this->notifier = $notifier;
    }

    public function generateReferenceNumber()
    {
        do {
            $ref = 'REF-'.random_int(100000, 999999);
        } while (Application::where('reference_number', $ref)->exists());

        return $ref;
    }

    public function apply(JobPosting $job, array $data, UploadedFile $cv = null, $userId = null, array $extraFiles = [])
    {
        $cvUrl = null;
        $cvPath = null;
        if ($cv) {
            [$cvUrl, $cvPath] = $this->storeCv($job, $cv);
        }

        // Single WhatsApp number is used for contact + notifications.
        $whatsapp = $this->combinePhone($data['country_code'] ?? '', $data['whatsapp_number'] ?? ($data['phone'] ?? ''));

        $payload = [
            'id' => (string) Str::uuid(),
            'job_id' => $job->id,
            'user_id' => $userId,
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone' => $whatsapp,
            'whatsapp_number' => $whatsapp,
            'country' => $data['country'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'expected_salary' => $job->isInternship() ? null : ($data['expected_salary'] ?? null),
            'availability' => $data['availability'] ?? 'Immediately',
            'availability_days' => ($data['availability'] ?? null) === 'Custom' ? ($data['availability_days'] ?? null) : null,
            'cv_url' => $cvUrl,
            'cv_path' => $cvPath,
            'status' => Application::STATUS_AWAITING,
            'reference_number' => $this->generateReferenceNumber(),
            'submitted_at' => now(),
            'signature_image' => $data['signature_image'] ?? null,
        ];

        if ($job->isInternship()) {
            if (! empty($extraFiles['student_id'])) {
                $payload['student_id_path'] = $this->storeUploadFlexible($extraFiles['student_id'], 'student_id', $job->id);
            }
            if (! empty($extraFiles['internship_letter'])) {
                $payload['internship_letter_path'] = $this->storeUploadFlexible($extraFiles['internship_letter'], 'internship_letter', $job->id);
            }
            if (! empty($extraFiles['selfie'])) {
                $payload['selfie_path'] = $this->storeImageUpload($extraFiles['selfie'], 'selfie', $job->id);
            }
        }

        $application = Application::create($payload);
        $this->jobs->incrementApplicants($job);
        $this->notifier->underReview($application, $job);

        return $application;
    }

    public function ensureAgreementToken(Application $application)
    {
        if (! $application->agreement_token) {
            $application->agreement_token = Str::random(48);
            $application->save();
        }

        return $application->agreement_token;
    }

    public function agreementUrl(Application $application)
    {
        $token = $this->ensureAgreementToken($application);

        return url('/application-agreement/'.$token);
    }

    protected function storeCv(JobPosting $job, UploadedFile $cv)
    {
        $ext = $cv->getClientOriginalExtension() ?: 'pdf';
        $name = 'cv_'.substr($job->id, 0, 8).'_'.time().'_'.Str::random(6).'.'.$ext;
        $dir = $this->ensureUploadDir();
        $cv->move($dir, $name);

        $relative = 'uploads/applications/'.$name;

        return ['/'.$relative, $dir.'/'.$name];
    }

    protected function storeDocUpload(UploadedFile $file, $prefix, $jobId)
    {
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $name = $prefix.'_'.substr($jobId, 0, 8).'_'.time().'_'.Str::random(6).'.'.$ext;
        $dir = $this->ensureUploadDir();
        $file->move($dir, $name);

        return 'uploads/applications/'.$name;
    }

    protected function storeUploadFlexible(UploadedFile $file, $prefix, $jobId)
    {
        $mime = (string) $file->getMimeType();
        if (strpos($mime, 'image/') === 0) {
            return $this->storeImageUpload($file, $prefix, $jobId);
        }

        return $this->storeDocUpload($file, $prefix, $jobId);
    }

    protected function storeImageUpload(UploadedFile $file, $prefix, $jobId)
    {
        $dir = $this->ensureUploadDir();
        $name = $prefix.'_'.substr($jobId, 0, 8).'_'.time().'_'.Str::random(6).'.jpg';
        $path = $dir.'/'.$name;

        try {
            $img = Image::make($file->getRealPath());
            $img->resize(1200, 1200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $img->encode('jpg', 72)->save($path);
        } catch (\Throwable $e) {
            $file->move($dir, $name);
        }

        return 'uploads/applications/'.$name;
    }

    protected function ensureUploadDir()
    {
        $dir = base_path('public/uploads/applications');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        return $dir;
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
            if ($status === Application::STATUS_AWAITING) {
                $q->whereIn('status', [
                    Application::STATUS_AWAITING, 'new', 'reviewed', 'interview',
                ]);
            } elseif ($status === Application::STATUS_SELECTED) {
                $q->whereIn('status', [Application::STATUS_SELECTED, 'shortlisted', Application::STATUS_HIRED]);
            } elseif ($status === Application::STATUS_REJECTED) {
                $q->whereIn('status', [Application::STATUS_REJECTED, 'withdrawn']);
            } else {
                $q->where('status', $status);
            }
        }
        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        return $q->paginate(50);
    }

    public function updateStatus(Application $application, array $data)
    {
        $previous = $application->status;
        $status = $data['status'] ?? $application->status;
        $application->status = $status;
        if (array_key_exists('rejection_reason', $data)) {
            $application->rejection_reason = $data['rejection_reason'];
        }
        if (array_key_exists('interview_date', $data)) {
            $application->interview_date = $data['interview_date'] ?: null;
        }
        $application->save();
        $application->load('job');

        if ($status !== $previous && $application->job) {
            if ($status === Application::STATUS_SELECTED) {
                $url = $this->agreementUrl($application);
                $application->agreement_sent_at = now();
                $application->save();
                $this->notifier->selected($application, $application->job, $url);
            } elseif ($status === Application::STATUS_REJECTED) {
                $this->notifier->rejected($application, $application->job);
            } elseif ($status === Application::STATUS_AWAITING && $previous !== Application::STATUS_AWAITING) {
                $this->notifier->underReview($application, $application->job);
            }
        }

        return $application;
    }

    public function markAgreementSigned(Application $application, $signatureImage)
    {
        $application->agreement_signature_image = $signatureImage;
        $application->agreement_signed_at = now();
        $application->status = Application::STATUS_HIRED;
        $application->save();
        $application->load('job');

        if ($application->job) {
            $this->notifier->agreementSigned($application, $application->job);
        }

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
