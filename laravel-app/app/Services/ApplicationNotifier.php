<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use App\Services\Messaging\NotificationRouter;
use App\Support\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class ApplicationNotifier
{
    protected $router;

    public function __construct(NotificationRouter $router)
    {
        $this->router = $router;
    }

    public function notifyPhone(Application $application)
    {
        return $application->whatsapp_number ?: $application->phone;
    }

    public function send(Application $application, $message, array $statusVars = [])
    {
        $phone = $this->notifyPhone($application);
        if (! $phone) {
            return ['success' => false, 'error' => 'No WhatsApp number'];
        }

        if (empty($statusVars['name'])) {
            $statusVars['name'] = $application->full_name ?: 'Client';
        }
        if (empty($statusVars['reference'])) {
            $statusVars['reference'] = $application->reference_number ?: '-';
        }

        $result = $this->router->sendWhatsAppText($phone, $message, $statusVars);
        if (empty($result['success'])) {
            Log::warning('Application WhatsApp failed', [
                'application_id' => $application->id,
                'error' => $result['error'] ?? 'unknown',
                'provider' => $result['provider'] ?? null,
            ]);
        }

        return $result;
    }

    public function underReview(Application $application, JobPosting $job)
    {
        $message = WhatsAppMessage::applicationUnderReview(
            $application->full_name,
            $job->title,
            $application->reference_number,
            $job->isInternship()
        );

        $result = $this->send($application, $message, [
            'title' => 'Application received',
            'message' => 'Your application for '.$job->title.' has been received and is under review.',
            'details' => $job->isInternship() ? 'Type: Internship' : 'Type: Job',
        ]);

        $applicationId = $application->id;
        $jobId = $job->id;
        app()->terminating(function () use ($applicationId, $jobId) {
            try {
                $application = Application::find($applicationId);
                $job = JobPosting::find($jobId);
                if ($application && $job) {
                    app(self::class)->notifyAdminsOfNewApplication($application, $job);
                }
            } catch (\Throwable $e) {
                Log::warning('Admin new-application notify failed', [
                    'application_id' => $applicationId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $result;
    }

    /**
     * WhatsApp admins (role_id <= 2) with a phone when a new application arrives.
     * Login link redirects to the application review page after sign-in.
     */
    public function notifyAdminsOfNewApplication(Application $application, JobPosting $job)
    {
        $path = route('jobs.applications.show', $application->id, false);
        $loginUrl = url('/login?redirect='.rawurlencode($path));

        $admins = \App\User::where('is_deleted', false)
            ->where('is_active', 1)
            ->where('role_id', '<=', 2)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        $sent = 0;
        foreach ($admins as $admin) {
            $phone = trim((string) $admin->phone);
            if ($phone === '') {
                continue;
            }
            $message = WhatsAppMessage::applicationUnderReviewAdmin(
                $admin->name,
                $application->full_name,
                $job->title,
                $application->reference_number,
                $loginUrl,
                $job->isInternship(),
                $this->notifyPhone($application)
            );
            // Wasender account protection: wait after applicant message and between admins.
            usleep(5500000);
            $send = $this->router->sendWhatsAppText($phone, $message, [
                'title' => 'New application',
                'message' => ($application->full_name ?: 'Applicant').' applied for '.$job->title,
                'details' => $application->reference_number ?: '-',
            ]);
            if (! empty($send['success'])) {
                $sent++;
            } else {
                Log::warning('Admin application WhatsApp failed', [
                    'application_id' => $application->id,
                    'admin_id' => $admin->id,
                    'error' => $send['error'] ?? 'unknown',
                ]);
            }
        }

        return ['sent' => $sent];
    }

    /**
     * Tell admins a signed candidate ended up without a placement, so nobody is
     * left waiting for tasks that will never be released.
     *
     * @return array{sent:int}
     */
    public function notifyAdminsOfPlacementIssue(Application $application, $reason)
    {
        $path = route('jobs.applications.show', $application->id, false);
        $loginUrl = url('/login?redirect='.rawurlencode($path));

        $admins = \App\User::where('is_deleted', false)
            ->where('is_active', 1)
            ->where('role_id', '<=', 2)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        $sent = 0;
        foreach ($admins as $admin) {
            $message = WhatsAppMessage::internshipPlacementIssueAdmin(
                $admin->name,
                $application->full_name,
                $application->reference_number,
                $reason,
                $loginUrl
            );
            usleep(5500000);
            $send = $this->router->sendWhatsAppText(trim((string) $admin->phone), $message, [
                'title' => 'Placement needs attention',
                'message' => ($application->full_name ?: 'Applicant').' signed the offer but has no placement.',
                'details' => $application->reference_number ?: '-',
            ]);
            if (! empty($send['success'])) {
                $sent++;
            } else {
                Log::warning('Placement-issue admin WhatsApp failed', [
                    'application_id' => $application->id,
                    'admin_id' => $admin->id,
                    'error' => $send['error'] ?? 'unknown',
                ]);
            }
        }

        return ['sent' => $sent];
    }

    public function selected(Application $application, JobPosting $job, $agreementUrl)
    {
        $offerPortal = $job->isInternship() && $application->needsOfferPortal();
        $message = WhatsAppMessage::applicationSelected(
            $application->full_name,
            $job->title,
            $application->reference_number,
            $agreementUrl,
            $job->isInternship(),
            $offerPortal
        );

        return $this->send($application, $message, [
            'title' => 'Congratulations',
            'message' => $offerPortal
                ? 'You have been selected for '.$job->title.'. Accept your offer and set up your account.'
                : 'You have been selected for '.$job->title.'. Please sign your agreement.',
            'details' => $agreementUrl ?: '-',
        ]);
    }

    public function rejected(Application $application, JobPosting $job)
    {
        $message = WhatsAppMessage::applicationRejected(
            $application->full_name,
            $job->title,
            $application->reference_number,
            $application->rejection_reason
        );

        return $this->send($application, $message, [
            'title' => 'Application update',
            'message' => 'We are unable to proceed with your application for '.$job->title.' at this time.',
            'details' => $application->rejection_reason ?: '-',
        ]);
    }

    public function documentsUpdateRequested(Application $application, $job, $updateUrl, array $missingLabels = [], $note = null)
    {
        $title = $job ? $job->title : 'your internship';
        $message = WhatsAppMessage::applicationDocumentsUpdateRequested(
            $application->full_name,
            $title,
            $application->reference_number,
            $updateUrl,
            $missingLabels,
            $note
        );

        return $this->send($application, $message, [
            'title' => 'Documents needed',
            'message' => 'Please upload missing documents for '.$title,
            'details' => implode(', ', $missingLabels) ?: '-',
        ]);
    }

    public function agreementSigned(Application $application, JobPosting $job)
    {
        $message = WhatsAppMessage::applicationAgreementSigned(
            $application->full_name,
            $job->title,
            $application->reference_number,
            $job->isInternship()
        );

        return $this->send($application, $message, [
            'title' => 'Agreement signed',
            'message' => 'Your agreement for '.$job->title.' has been signed and received.',
            'details' => $job->isInternship() ? 'Working hours per Working Week' : 'Working hours 7:30 AM – 4:00 PM',
        ]);
    }

    /**
     * Hired / admission letter via Twilio Content SID (or Wasender text equivalent).
     */
    public function hiredAdmission(Application $application, JobPosting $job, $mediaUrl = null)
    {
        $phone = $this->notifyPhone($application);
        if (! $phone) {
            return ['success' => false, 'error' => 'No WhatsApp number'];
        }

        $program = $job->title ?: 'Beyond Enterprise';
        $department = $job->department
            ?: ($job->isInternship() ? 'Internship Programme' : 'Employment Programme');
        $year = date('Y').'/'.(date('Y') + 1);

        $result = $this->router->sendWhatsAppAdmission($phone, $program, $department, $year, $mediaUrl);
        if (empty($result['success'])) {
            Log::warning('Application admission WhatsApp failed', [
                'application_id' => $application->id,
                'error' => $result['error'] ?? 'unknown',
                'provider' => $result['provider'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * WhatsApp supervisors that they have been assigned intern(s).
     *
     * @param  array  $supervisorRefs  e.g. user:12, customer:5
     * @param  array  $internNames
     * @param  string  $program
     * @param  string  $startDate
     * @param  string  $durationLabel
     * @return array{sent:int,skipped:int,errors:string[]}
     */
    public function notifySupervisorsAssigned(array $supervisorRefs, array $internNames, $program, $startDate, $durationLabel)
    {
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => []];
        $contacts = $this->resolveSupervisorContacts($supervisorRefs);
        if (empty($contacts)) {
            $result['errors'][] = 'No supervisor phone numbers found.';

            return $result;
        }

        foreach ($contacts as $contact) {
            $phone = $contact['phone'] ?? '';
            if ($phone === '') {
                $result['skipped']++;
                $result['errors'][] = ($contact['name'] ?? 'Supervisor').': no phone.';
                continue;
            }
            $message = WhatsAppMessage::internshipSupervisorAssigned(
                $contact['name'] ?? 'Supervisor',
                $internNames,
                $program,
                $startDate,
                $durationLabel,
                url('/staff-otp-login')
            );
            $send = $this->router->sendWhatsAppText($phone, $message, [
                'title' => 'Internship supervision assigned',
                'message' => 'You have been assigned to supervise intern(s).',
                'details' => implode(', ', $internNames),
            ]);
            if (! empty($send['success'])) {
                $result['sent']++;
            } else {
                $result['skipped']++;
                $result['errors'][] = ($contact['name'] ?? $phone).': '.($send['error'] ?? 'send failed');
                Log::warning('Supervisor assignment WhatsApp failed', [
                    'phone' => $phone,
                    'error' => $send['error'] ?? 'unknown',
                ]);
            }
        }

        return $result;
    }

    /**
     * @param  array  $refs
     * @return array<int, array{name:string,phone:string}>
     */
    protected function resolveSupervisorContacts(array $refs)
    {
        $out = [];
        $seenPhones = [];
        foreach ($refs as $ref) {
            $ref = (string) $ref;
            $name = '';
            $phone = '';
            if (strpos($ref, 'user:') === 0) {
                $user = \App\User::where('is_deleted', false)->find((int) substr($ref, 5));
                if ($user) {
                    $name = $user->name ?: 'Supervisor';
                    $phone = $user->phone ?: '';
                }
            } elseif (strpos($ref, 'customer:') === 0) {
                $customer = \App\Customer::find((int) substr($ref, 9));
                if ($customer) {
                    $name = $customer->name ?: ($customer->company_name ?: 'Supervisor');
                    $phone = $customer->phone_number ?: '';
                }
            }
            $phoneKey = preg_replace('/\D/', '', (string) $phone);
            if ($phoneKey === '' || isset($seenPhones[$phoneKey])) {
                continue;
            }
            $seenPhones[$phoneKey] = true;
            $out[] = ['name' => $name, 'phone' => $phone];
        }

        return $out;
    }
}
