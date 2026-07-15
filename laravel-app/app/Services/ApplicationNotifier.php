<?php

namespace App\Services;

use App\Application;
use App\JobPosting;
use Illuminate\Support\Facades\Log;

class ApplicationNotifier
{
    protected $whatsapp;

    public function __construct(BeyondWasenderService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function notifyPhone(Application $application)
    {
        return $application->whatsapp_number ?: $application->phone;
    }

    public function send(Application $application, $message)
    {
        $phone = $this->notifyPhone($application);
        if (! $phone) {
            return ['success' => false, 'error' => 'No WhatsApp number'];
        }

        $result = $this->whatsapp->sendText($phone, $message);
        if (empty($result['success'])) {
            Log::warning('Application WhatsApp failed', [
                'application_id' => $application->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }

        return $result;
    }

    public function underReview(Application $application, JobPosting $job)
    {
        $kind = $job->isInternship() ? 'internship' : 'job';
        $message = "Hello {$application->full_name},\n\n"
            ."Your {$kind} application for *{$job->title}* at Beyond Enterprise is now *under review*.\n"
            ."Reference: {$application->reference_number}\n\n"
            ."We will notify you on WhatsApp at every stage. Please keep this number available.";

        return $this->send($application, $message);
    }

    public function selected(Application $application, JobPosting $job, $agreementUrl)
    {
        $kind = $job->isInternship() ? 'internship' : 'employment';
        $message = "Hello {$application->full_name},\n\n"
            ."Congratulations! You have been *selected* for the {$kind} role *{$job->title}*.\n"
            ."Reference: {$application->reference_number}\n\n"
            ."Please review and sign your agreement here:\n{$agreementUrl}\n\n"
            ."After signing, you will receive confirmation on WhatsApp.";

        return $this->send($application, $message);
    }

    public function rejected(Application $application, JobPosting $job)
    {
        $reason = $application->rejection_reason
            ? "\nReason: {$application->rejection_reason}"
            : '';
        $message = "Hello {$application->full_name},\n\n"
            ."Thank you for applying for *{$job->title}* at Beyond Enterprise.\n"
            ."Reference: {$application->reference_number}\n\n"
            ."After careful review, we are unable to proceed with your application at this time.{$reason}\n\n"
            ."We wish you the best in your future opportunities.";

        return $this->send($application, $message);
    }

    public function agreementSigned(Application $application, JobPosting $job)
    {
        $kind = $job->isInternship() ? 'internship' : 'employment';
        $hours = $job->isInternship()
            ? "Working hours: *7:30 AM – 4:00 PM*. You must complete daily timesheets and at least *40 hours per week*."
            : "Working hours: *7:30 AM – 4:00 PM*. You must complete daily timesheets and at least *40 hours per week*.";

        $message = "Hello {$application->full_name},\n\n"
            ."Your {$kind} agreement for *{$job->title}* has been signed and received.\n"
            ."Reference: {$application->reference_number}\n\n"
            ."{$hours}\n"
            ."Failure to complete assigned tasks may result in termination.\n\n"
            ."Welcome to Beyond Enterprise.";

        return $this->send($application, $message);
    }
}
