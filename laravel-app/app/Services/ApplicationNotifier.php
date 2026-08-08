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

        return $this->send($application, $message, [
            'title' => 'Application received',
            'message' => 'Your application for '.$job->title.' has been received and is under review.',
            'details' => $job->isInternship() ? 'Type: Internship' : 'Type: Job',
        ]);
    }

    public function selected(Application $application, JobPosting $job, $agreementUrl)
    {
        $message = WhatsAppMessage::applicationSelected(
            $application->full_name,
            $job->title,
            $application->reference_number,
            $agreementUrl,
            $job->isInternship()
        );

        return $this->send($application, $message, [
            'title' => 'Congratulations',
            'message' => 'You have been selected for '.$job->title.'. Please sign your agreement.',
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
            'details' => 'Working hours 7:30 AM – 4:00 PM',
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
                $durationLabel
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
