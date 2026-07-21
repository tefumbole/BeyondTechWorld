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
}
