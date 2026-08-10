<?php

namespace App\Services;

use App\Application;
use App\InternshipEnrolment;
use App\JobPosting;
use App\Support\WhatsAppPhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        // Internships: one open application per posting; re-apply only after reject or completed internship.
        if ($job->isInternship()) {
            $this->assertNoOpenInternshipDuplicate($job, $data['email'] ?? '', $whatsapp, $userId);
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'job_id' => $job->id,
            'user_id' => $userId,
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone' => $whatsapp,
            'whatsapp_number' => $whatsapp,
            'country' => $data['country'] ?? null,
            'internship_program_id' => $job->isInternship() ? ($data['internship_program_id'] ?? null) : null,
            'internship_duration_days' => $job->isInternship() ? ($data['internship_duration_days'] ?? null) : null,
            'school' => $job->isInternship() ? trim((string) ($data['school'] ?? '')) : null,
            'level_of_study' => $job->isInternship() ? trim((string) ($data['level_of_study'] ?? '')) : null,
            'education_status' => $job->isInternship() ? ($data['education_status'] ?? null) : null,
            'is_academic_required' => $job->isInternship()
                ? filter_var($data['is_academic_required'] ?? false, FILTER_VALIDATE_BOOLEAN)
                : null,
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
            $allowed = $job->internshipProgramIds();
            $chosen = (int) ($payload['internship_program_id'] ?? 0);
            if (empty($allowed) || ! in_array($chosen, $allowed, true)) {
                throw ValidationException::withMessages([
                    'internship_program_id' => ['Please select one of the internship programs offered for this posting.'],
                ]);
            }
            $payload['internship_program_id'] = $chosen;
            $duration = (int) ($payload['internship_duration_days'] ?? 0);
            if ($duration < Application::internshipDurationMin() || $duration > Application::internshipDurationMax()) {
                throw ValidationException::withMessages([
                    'internship_duration_days' => ['Please enter internship duration between '.Application::internshipDurationMin().' and '.Application::internshipDurationMax().' days.'],
                ]);
            }
            $payload['internship_duration_days'] = $duration;
            if ($payload['school'] === '') {
                $payload['school'] = null;
            }
            if ($payload['level_of_study'] === '') {
                $payload['level_of_study'] = null;
            }
            // Graduated candidates are not on an academic-required internship by default.
            if (($payload['education_status'] ?? null) === 'graduated') {
                $payload['is_academic_required'] = false;
            }
            if (! empty($extraFiles['student_id'])) {
                $payload['student_id_path'] = $this->storeUploadFlexible($extraFiles['student_id'], 'student_id_front', $job->id);
            }
            if (! empty($extraFiles['student_id_back'])) {
                $payload['student_id_back_path'] = $this->storeUploadFlexible($extraFiles['student_id_back'], 'student_id_back', $job->id);
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
        $this->tagApplicantUser($application, $userId);
        try {
            $this->notifier->underReview($application, $job);
        } catch (\Throwable $e) {
            Log::warning('Application under-review notify failed: '.$e->getMessage(), [
                'application_id' => $application->id,
            ]);
        }

        return $application;
    }

    /**
     * Ensure portal accounts that apply are tagged as applicants
     * (so People / Letters / Users can filter them separately).
     */
    protected function tagApplicantUser(Application $application, $userId = null)
    {
        try {
            $beyondId = $userId ?: $application->user_id;
            if ($beyondId) {
                $user = \App\BeyondUser::find($beyondId);
                if ($user && ! in_array((string) $user->role, ['admin', 'super_admin', 'staff', 'task_assignee'], true)) {
                    $user->role = 'applicant';
                    $user->save();
                }
            } else {
                app(\App\Services\PeopleDirectoryService::class)->ensureBeyondFromApplicant($application);
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not tag applicant user: '.$e->getMessage());
        }
    }

    /**
     * Unique applicant people directory (one row per email/phone).
     */
    public function applicantDirectory($search = null)
    {
        $q = Application::query()->orderByDesc('submitted_at');
        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }

        $rows = $q->get([
            'id', 'user_id', 'full_name', 'email', 'phone', 'whatsapp_number',
            'country', 'status', 'submitted_at', 'job_id',
        ]);

        $seen = [];
        $people = [];
        foreach ($rows as $app) {
            $key = strtolower(trim((string) $app->email));
            if ($key === '') {
                $key = preg_replace('/\D+/', '', (string) ($app->whatsapp_number ?: $app->phone));
            }
            if ($key === '') {
                $key = 'id:'.$app->id;
            }
            if (isset($seen[$key])) {
                $idx = $seen[$key];
                $people[$idx]['applications_count']++;
                $people[$idx]['application_ids'][] = $app->id;
                // Keep the first (newest) row's status/submitted_at; only grow the ID list.
                continue;
            }
            $seen[$key] = count($people);
            $people[] = [
                'key' => $key,
                'full_name' => $app->full_name,
                'email' => $app->email,
                'phone' => $app->whatsapp_number ?: $app->phone,
                'country' => $app->country,
                'user_id' => $app->user_id,
                'latest_application_id' => $app->id,
                'application_ids' => [$app->id],
                'latest_status' => $app->status,
                'submitted_at' => $app->submitted_at,
                'applications_count' => 1,
                'erp_user_id' => null,
                'erp_role' => null,
            ];
        }

        // Attach linked ERP user / role (by application.user_id or email match).
        $emails = collect($people)->pluck('email')->filter()->map(function ($e) {
            return strtolower(trim($e));
        })->unique()->values()->all();
        $userIds = collect($people)->pluck('user_id')->filter()->unique()->values()->all();
        $users = collect();
        if (! empty($userIds) || ! empty($emails)) {
            $users = \App\User::query()
                ->where('is_deleted', false)
                ->where(function ($q) use ($emails, $userIds) {
                    if (! empty($userIds)) {
                        $q->whereIn('id', $userIds);
                    }
                    foreach ($emails as $i => $em) {
                        if ($i === 0 && empty($userIds)) {
                            $q->whereRaw('LOWER(email) = ?', [$em]);
                        } else {
                            $q->orWhereRaw('LOWER(email) = ?', [$em]);
                        }
                    }
                })
                ->get(['id', 'email', 'role_id', 'name']);
        }
        $roles = \App\Roles::whereIn('id', $users->pluck('role_id')->filter()->unique())->pluck('name', 'id');
        $byId = $users->keyBy('id');
        $byEmail = $users->keyBy(function ($u) {
            return strtolower(trim((string) $u->email));
        });

        foreach ($people as $i => $person) {
            $user = null;
            if (! empty($person['user_id']) && isset($byId[$person['user_id']])) {
                $user = $byId[$person['user_id']];
            } elseif (! empty($person['email'])) {
                $em = strtolower(trim($person['email']));
                $user = $byEmail[$em] ?? null;
            }
            if ($user) {
                $people[$i]['erp_user_id'] = $user->id;
                $people[$i]['erp_role'] = $roles[$user->role_id] ?? null;
            }
        }

        return collect($people);
    }

    /**
     * Delete applications by ID. Returns how many were removed.
     */
    public function deleteApplications(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if (empty($ids)) {
            return 0;
        }

        $apps = Application::whereIn('id', $ids)->get();
        $deleted = 0;
        foreach ($apps as $app) {
            $jobId = $app->job_id;
            $app->delete();
            $deleted++;
            if ($jobId) {
                try {
                    $job = JobPosting::find($jobId);
                    if ($job && (int) $job->current_applicants > 0) {
                        $job->decrement('current_applicants');
                    }
                } catch (\Throwable $e) {
                    // non-fatal
                }
            }
        }

        return $deleted;
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

        // Keep URL + path relative (same scheme as ID/letter/selfie) so downloads work after deploy.
        return ['/'.$relative, $relative];
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
        try {
            $combined = WhatsAppPhone::combine($code, $number);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'whatsapp_number' => $e->getMessage(),
            ]);
        }

        if ($combined === '') {
            throw ValidationException::withMessages([
                'whatsapp_number' => 'Enter a valid WhatsApp number (e.g. 675321739).',
            ]);
        }

        return $combined;
    }

    /**
     * Statuses that mean the applicant still has an open application for a posting.
     */
    public static function openApplicationStatuses()
    {
        return [
            Application::STATUS_AWAITING,
            'new',
            'reviewed',
            'interview',
            'pending',
            Application::STATUS_SELECTED,
            'shortlisted',
            Application::STATUS_HIRED,
        ];
    }

    /**
     * Find an open application for this internship posting by email / WhatsApp / portal user.
     * Hired applications only block while the linked enrolment is not completed.
     *
     * @return Application|null
     */
    public function findBlockingInternshipApplication(JobPosting $job, $email = null, $whatsapp = null, $userId = null)
    {
        if (! $job || ! $job->isInternship()) {
            return null;
        }

        $email = strtolower(trim((string) $email));
        $whatsapp = preg_replace('/\D+/', '', (string) $whatsapp);
        $userId = $userId ? (string) $userId : '';

        if ($email === '' && $whatsapp === '' && $userId === '') {
            return null;
        }

        $candidates = Application::query()
            ->where('job_id', $job->id)
            ->whereIn('status', self::openApplicationStatuses())
            ->where(function ($q) use ($email, $whatsapp, $userId) {
                if ($email !== '') {
                    $q->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                if ($userId !== '') {
                    $q->orWhere('user_id', $userId);
                }
                if ($whatsapp !== '') {
                    // Broad SQL filter; exact digit match is confirmed below.
                    $tail = strlen($whatsapp) > 9 ? substr($whatsapp, -9) : $whatsapp;
                    $q->orWhere('whatsapp_number', 'like', '%'.$tail.'%')
                        ->orWhere('phone', 'like', '%'.$tail.'%');
                }
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->get();

        foreach ($candidates as $application) {
            if (! $this->internshipApplicationStillOpen($application)) {
                continue;
            }
            $appEmail = strtolower(trim((string) $application->email));
            $appPhone = preg_replace('/\D+/', '', (string) ($application->whatsapp_number ?: $application->phone));
            $emailMatch = $email !== '' && $appEmail === $email;
            $userMatch = $userId !== '' && (string) $application->user_id === $userId;
            $phoneMatch = $whatsapp !== '' && $appPhone !== '' && (
                $appPhone === $whatsapp
                || (strlen($whatsapp) >= 9 && strlen($appPhone) >= 9 && substr($appPhone, -9) === substr($whatsapp, -9))
            );
            if ($emailMatch || $userMatch || $phoneMatch) {
                return $application;
            }
        }

        return null;
    }

    /**
     * Whether an application still blocks a new apply for the same internship.
     * Rejected/withdrawn never block. Hired blocks until enrolment is completed.
     */
    public function internshipApplicationStillOpen(Application $application)
    {
        $status = (string) $application->status;
        if (in_array($status, [Application::STATUS_REJECTED, 'withdrawn'], true)) {
            return false;
        }

        if ($status === Application::STATUS_HIRED) {
            $enrolment = InternshipEnrolment::where('application_id', $application->id)
                ->orderByDesc('updated_at')
                ->first();
            if ($enrolment && $enrolment->status === 'completed') {
                return false;
            }

            return true;
        }

        return in_array($status, self::openApplicationStatuses(), true);
    }

    public function assertNoOpenInternshipDuplicate(JobPosting $job, $email, $whatsapp = null, $userId = null)
    {
        $blocking = $this->findBlockingInternshipApplication($job, $email, $whatsapp, $userId);
        if (! $blocking) {
            return;
        }

        $ref = $blocking->reference_number ?: 'your previous application';
        $label = $blocking->statusLabel();

        throw ValidationException::withMessages([
            'email' => [
                "You already have an open application ({$ref}, status: {$label}) for this internship. "
                .'You can apply again only after that application is rejected, or after you complete the internship.',
            ],
        ]);
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
                $q->whereIn('status', [Application::STATUS_SELECTED, 'shortlisted']);
            } elseif ($status === Application::STATUS_HIRED) {
                $q->where('status', Application::STATUS_HIRED);
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
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('school', 'like', "%{$search}%")
                    ->orWhere('level_of_study', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        return $q->paginate(50);
    }

    public function updateStatus(Application $application, array $data)
    {
        $previous = $application->status;
        $status = $data['status'] ?? $application->status;

        // Hired without a signed agreement → treat as Selected and send agreement link.
        if ($status === Application::STATUS_HIRED && empty($application->agreement_signed_at)) {
            $status = Application::STATUS_SELECTED;
        }

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
                $this->enrolInInternshipProgram($application);
            } elseif ($status === Application::STATUS_REJECTED) {
                $this->notifier->rejected($application, $application->job);
            } elseif ($status === Application::STATUS_HIRED) {
                $this->notifier->hiredAdmission($application, $application->job);
                $this->enrolInInternshipProgram($application);
            } elseif ($status === Application::STATUS_AWAITING && $previous !== Application::STATUS_AWAITING) {
                $this->notifier->underReview($application, $application->job);
            }
        }

        return $application;
    }

    /**
     * Bulk-assign applicants (by latest application id) to a program + supervisor + period.
     *
     * @return array{assigned:int, skipped:int, errors:string[]}
     */
    public function assignApplicantsToInternship(array $applicationIds, array $opts)
    {
        $programId = (int) ($opts['program_id'] ?? 0);
        $duration = Application::normalizeInternshipDurationDays($opts['planned_duration_days'] ?? 90, 90);
        $startDate = $opts['start_date'] ?? now()->toDateString();
        $startDay = max(1, min(180, (int) ($opts['start_curriculum_day'] ?? 1)));
        $markSelected = ! empty($opts['mark_selected']);
        $supervisorBundle = $this->resolveSupervisorSelection(
            $opts['supervisor_refs'] ?? [],
            $opts['supervisor_id'] ?? null
        );

        $result = ['assigned' => 0, 'skipped' => 0, 'errors' => [], 'supervisors_notified' => 0];
        $ids = array_values(array_unique(array_filter(array_map('strval', $applicationIds))));
        if (empty($ids) || $programId < 1) {
            $result['errors'][] = 'Select at least one applicant and an internship program.';

            return $result;
        }

        $assignedNames = [];
        foreach ($ids as $id) {
            $application = Application::find($id);
            if (! $application) {
                $result['skipped']++;
                $result['errors'][] = "Application {$id} not found.";
                continue;
            }
            try {
                $enrolment = $this->assignApplicationToInternship($application, [
                    'program_id' => $programId,
                    'supervisor_id' => $supervisorBundle['primary_user_id'],
                    'supervisor_refs' => $supervisorBundle['refs'],
                    'planned_duration_days' => $duration,
                    'start_date' => $startDate,
                    'start_curriculum_day' => $startDay,
                    'mark_selected' => $markSelected,
                ]);
                if ($enrolment) {
                    $result['assigned']++;
                    $assignedNames[] = $application->full_name ?: ('Applicant '.$id);
                } else {
                    $result['skipped']++;
                    $result['errors'][] = ($application->full_name ?: $id).': could not enrol (missing Intern role or program).';
                }
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = ($application->full_name ?: $id).': '.$e->getMessage();
            }
        }

        if ($result['assigned'] > 0 && ! empty($supervisorBundle['refs'])) {
            $program = \App\InternshipProgram::find($programId);
            $programName = $program
                ? (method_exists($program, 'displayName') ? $program->displayName() : $program->name)
                : 'Internship Programme';
            $durationLabel = $duration.' day'.($duration === 1 ? '' : 's');
            $startLabel = \Carbon\Carbon::parse($startDate)->format('F j, Y');
            try {
                $notify = $this->notifier->notifySupervisorsAssigned(
                    $supervisorBundle['refs'],
                    $assignedNames,
                    $programName,
                    $startLabel,
                    $durationLabel
                );
                $result['supervisors_notified'] = (int) ($notify['sent'] ?? 0);
                foreach (($notify['errors'] ?? []) as $err) {
                    $result['errors'][] = 'Supervisor notify: '.$err;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = 'Supervisor notify: '.$e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Resolve directory refs (user:… / customer:…) into ERP supervisor users.
     *
     * @return array{refs:string[], primary_user_id:?int, user_ids:int[]}
     */
    public function resolveSupervisorSelection(array $refs, $legacySupervisorId = null)
    {
        $refs = array_values(array_unique(array_filter(array_map('strval', $refs))));
        $userIds = [];
        $normalizedRefs = [];

        foreach ($refs as $ref) {
            $erpId = $this->ensureErpSupervisorFromDirectoryRef($ref);
            if ($erpId) {
                $userIds[] = $erpId;
                $normalizedRefs[] = 'user:'.$erpId;
                // Keep original customer/user ref too for display if different
                if ($ref !== 'user:'.$erpId && ! in_array($ref, $normalizedRefs, true)) {
                    $normalizedRefs[] = $ref;
                }
            }
        }

        if ($legacySupervisorId) {
            $legacy = (int) $legacySupervisorId;
            if ($legacy > 0) {
                $userIds[] = $legacy;
                if (! in_array('user:'.$legacy, $normalizedRefs, true)) {
                    $normalizedRefs[] = 'user:'.$legacy;
                }
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        $normalizedRefs = array_values(array_unique(array_filter($normalizedRefs)));

        return [
            'refs' => $normalizedRefs,
            'primary_user_id' => $userIds[0] ?? null,
            'user_ids' => $userIds,
        ];
    }

    /**
     * Ensure a directory person (User or Customer) exists as an ERP user who can supervise.
     */
    protected function ensureErpSupervisorFromDirectoryRef($ref)
    {
        $ref = (string) $ref;
        if (strpos($ref, 'user:') === 0) {
            $id = (int) substr($ref, 5);
            $user = \App\User::where('is_deleted', false)->find($id);
            if (! $user) {
                return null;
            }
            $this->ensureSupervisorAccess($user);

            return (int) $user->id;
        }

        $name = null;
        $email = null;
        $phone = null;

        if (strpos($ref, 'customer:') === 0) {
            $customer = \App\Customer::find((int) substr($ref, 9));
            if (! $customer) {
                return null;
            }
            $name = $customer->name ?: $customer->company_name;
            $email = trim((string) $customer->email);
            $phone = $customer->phone_number;
            if ($email === '') {
                $email = 'c'.$customer->id.'@customers.beyondtechworld.com';
            }
        } elseif (strpos($ref, 'beyond:') === 0) {
            $beyond = \App\BeyondUser::find(substr($ref, 7));
            if (! $beyond) {
                return null;
            }
            $name = $beyond->name;
            $email = trim((string) $beyond->email);
            $phone = $beyond->phone;
            if ($email === '') {
                return null;
            }
        } else {
            return null;
        }

        $emailKey = strtolower($email);
        $user = \App\User::where('is_deleted', false)
            ->whereRaw('LOWER(email) = ?', [$emailKey])
            ->first();

        if (! $user && $phone) {
            $user = $this->findErpUserByPhone($phone);
        }

        $supervisorRole = \App\Roles::where('is_active', true)->where('name', 'Internship Supervisor')->first()
            ?: \Spatie\Permission\Models\Role::where('name', 'Internship Supervisor')->where('guard_name', 'web')->first();

        $warehouseId = optional(\App\Warehouse::where('is_active', true)->first())->id;
        $billerId = optional(\App\Biller::where('is_active', true)->first())->id;

        if ($user) {
            $user->name = $name ?: $user->name;
            $user->phone = $phone ?: $user->phone;
            $user->is_active = 1;
            $user->save();
            $this->ensureSupervisorAccess($user);

            return (int) $user->id;
        }

        if (! $supervisorRole) {
            return null;
        }

        $password = 'Bt@'.random_int(100000, 999999);
        $payload = [
            'name' => $name ?: 'Supervisor',
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt($password),
            'role_id' => $supervisorRole->id,
            'warehouse_id' => $warehouseId,
            'biller_id' => $billerId,
            'is_active' => 1,
            'is_deleted' => 0,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'must_set_password')) {
            $payload['must_set_password'] = 1;
        }
        $user = \App\User::create($payload);
        Log::info('Created Internship Supervisor ERP user '.$user->id.' from '.$ref.' (must set password via WhatsApp OTP)');

        return (int) $user->id;
    }

    /**
     * Grant internship supervisor access without demoting Admins.
     */
    public function ensureSupervisorAccess(\App\User $user)
    {
        if ((int) $user->role_id <= 2) {
            return;
        }

        $supervisorPerms = [
            'internship_module',
            'internship.dashboard.view',
            'internship.supervise',
            'internship.submissions.view',
            'internship.submissions.grade',
            'internship.submissions.request_revision',
            'internship.enrolments.view',
            'internship.reports.view',
        ];

        $supervisorRole = \App\Roles::where('is_active', true)->where('name', 'Internship Supervisor')->first()
            ?: \Spatie\Permission\Models\Role::where('name', 'Internship Supervisor')->where('guard_name', 'web')->first();

        $role = \Spatie\Permission\Models\Role::find($user->role_id);
        if (! $role) {
            if ($supervisorRole) {
                $user->role_id = $supervisorRole->id;
                $user->save();
            }

            return;
        }

        $roleName = strtolower(trim((string) $role->name));
        if (in_array($roleName, ['intern', 'customer', ''], true) && $supervisorRole) {
            $user->role_id = $supervisorRole->id;
            $user->save();

            return;
        }

        try {
            if ($role->hasPermissionTo('internship.supervise')) {
                return;
            }
        } catch (\Throwable $e) {
            // permission may not exist yet
        }

        foreach ($supervisorPerms as $name) {
            try {
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $role->givePermissionTo($name);
            } catch (\Throwable $e) {
            }
        }
    }

    protected function findErpUserByPhone($phone)
    {
        try {
            $formatted = app(\App\Services\BeyondWasenderService::class)->formatPhone($phone);
        } catch (\Throwable $e) {
            $formatted = preg_replace('/\D/', '', (string) $phone);
        }
        $digits = preg_replace('/\D/', '', (string) $formatted);
        if (strlen($digits) < 8) {
            return null;
        }
        $tail = substr($digits, -9);

        return \App\User::where('is_deleted', false)
            ->where('is_active', 1)
            ->where(function ($q) use ($formatted, $digits, $tail) {
                $q->where('phone', $formatted)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', '+'.$digits)
                    ->orWhereRaw(
                        "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                        [$tail]
                    );
            })
            ->first();
    }

    /**
     * Assign one application to an internship program (creates/updates enrolment).
     */
    public function assignApplicationToInternship(Application $application, array $opts)
    {
        if (! class_exists(\App\Services\Internship\InternshipProgramService::class)) {
            return null;
        }

        $programId = (int) ($opts['program_id'] ?? $application->internship_program_id ?? 0);
        $program = \App\InternshipProgram::where('id', $programId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->first();
        if (! $program) {
            throw new \RuntimeException('Internship program not found or not published.');
        }

        $duration = Application::normalizeInternshipDurationDays(
            $opts['planned_duration_days'] ?? $application->internship_duration_days ?? 90,
            90
        );
        $startDay = max(1, min(180, (int) ($opts['start_curriculum_day'] ?? 1)));
        $startDate = $opts['start_date'] ?? now()->toDateString();
        $supervisorBundle = $this->resolveSupervisorSelection(
            $opts['supervisor_refs'] ?? [],
            $opts['supervisor_id'] ?? null
        );
        $supervisorId = $supervisorBundle['primary_user_id'];
        $supervisorRefs = $supervisorBundle['refs'];

        $application->internship_program_id = $program->id;
        $application->internship_duration_days = $duration;
        if (! empty($opts['mark_selected'])
            && in_array($application->status, [Application::STATUS_AWAITING, 'new', 'pending'], true)) {
            $application->status = Application::STATUS_SELECTED;
        }
        $application->save();

        $erpUser = $this->ensureErpInternUser($application);
        if (! $erpUser) {
            return null;
        }

        $existing = \App\InternshipEnrolment::where('student_user_id', $erpUser->id)
            ->where('program_id', $program->id)
            ->whereIn('status', ['pending', 'active', 'paused'])
            ->first();

        $service = app(\App\Services\Internship\InternshipProgramService::class);

        if ($existing) {
            $update = [
                'start_date' => $startDate,
                'supervisor_id' => $supervisorId,
                'supervisor_refs' => $supervisorRefs,
                'notes' => trim(($existing->notes ? $existing->notes."\n" : '').'Updated from Job Board assign '.$application->reference_number),
            ];
            if (! $existing->assignments()->exists()) {
                $update['start_curriculum_day'] = $startDay;
                $update['planned_duration_days'] = $duration;
            }
            if (! $existing->application_id) {
                $existing->application_id = $application->id;
                $existing->save();
            }
            $service->updatePlacement($existing, $update);
            if ($existing->fresh()->status === 'active') {
                $service->reconcileReleases($existing->id);
            }

            return $existing->fresh();
        }

        $enrolment = $service->enroll([
            'student_user_id' => $erpUser->id,
            'program_id' => $program->id,
            'application_id' => $application->id,
            'supervisor_id' => $supervisorId,
            'supervisor_refs' => $supervisorRefs,
            'start_date' => $startDate,
            'planned_duration_days' => $duration,
            'start_curriculum_day' => $startDay,
            'notes' => 'Assigned from Job Board applicants · '.$application->reference_number.' ('.$duration.' days from day '.$startDay.')',
        ]);
        $service->reconcileReleases($enrolment->id);

        return $enrolment;
    }

    /**
     * When an internship applicant is accepted, enrol them in the program they chose.
     */
    public function enrolInInternshipProgram(Application $application)
    {
        if (! $application->internship_program_id) {
            return null;
        }

        try {
            return $this->assignApplicationToInternship($application, [
                'program_id' => $application->internship_program_id,
                'planned_duration_days' => $application->internship_duration_days ?: 180,
                'start_date' => now()->toDateString(),
                'start_curriculum_day' => 1,
                'supervisor_id' => null,
                'mark_selected' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Internship auto-enrol failed for application '.$application->id.': '.$e->getMessage());

            return null;
        }
    }

    public function ensureErpInternUser(Application $application, $plainPassword = 'system')
    {
        $email = strtolower(trim((string) $application->email));
        if ($email === '') {
            return null;
        }

        $user = \App\User::where('is_deleted', false)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $internRole = \App\Roles::where('is_active', true)->where('name', 'Intern')->first()
            ?: \Spatie\Permission\Models\Role::where('name', 'Intern')->where('guard_name', 'web')->first();

        $warehouseId = optional(\App\Warehouse::where('is_active', true)->first())->id;
        $billerId = optional(\App\Biller::where('is_active', true)->first())->id;

        if ($user) {
            if ($internRole && (int) $user->role_id !== (int) $internRole->id) {
                // Only promote applicant/customer-style users; don't demote admins.
                if ((int) $user->role_id >= 4 || ! $user->role_id) {
                    $user->role_id = $internRole->id;
                }
            }
            $user->name = $application->full_name ?: $user->name;
            $user->phone = $application->whatsapp_number ?: $application->phone ?: $user->phone;
            $user->is_active = 1;
            if ($warehouseId && ! $user->warehouse_id) {
                $user->warehouse_id = $warehouseId;
            }
            if ($billerId && ! $user->biller_id) {
                $user->biller_id = $billerId;
            }
            $user->save();
        } else {
            if (! $internRole) {
                return null;
            }
            $password = $plainPassword ?: 'system';
            $user = \App\User::create([
                'name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->whatsapp_number ?: $application->phone,
                'password' => bcrypt($password),
                'role_id' => $internRole->id,
                'warehouse_id' => $warehouseId,
                'biller_id' => $billerId,
                'is_active' => 1,
                'is_deleted' => 0,
            ]);
            Log::info('Created Intern ERP user for application '.$application->id.' password '.$password);
        }

        // Link numeric ERP user id on applications when column expects it (may already hold Beyond UUID).
        try {
            if (is_numeric($application->user_id) || empty($application->user_id)) {
                $application->user_id = $user->id;
                $application->save();
            }
        } catch (\Throwable $e) {
        }

        return $user;
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
            $this->notifier->hiredAdmission($application, $application->job);
        }
        $this->enrolInInternshipProgram($application);

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
