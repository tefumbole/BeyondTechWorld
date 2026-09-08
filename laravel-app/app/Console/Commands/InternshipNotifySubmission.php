<?php

namespace App\Console\Commands;

use App\InternshipSubmission;
use App\Services\Internship\InternshipProgramService;
use Illuminate\Console\Command;

class InternshipNotifySubmission extends Command
{
    protected $signature = 'internship:notify-submission {id : Submission id} {--force : Resend even if already notified}';

    protected $description = 'WhatsApp intern and supervisors that a submission is ready to grade';

    public function handle(InternshipProgramService $service)
    {
        $submission = InternshipSubmission::find((int) $this->argument('id'));
        if (! $submission) {
            $this->error('Submission not found.');

            return 1;
        }

        $result = $service->notifySubmissionReceived($submission, (bool) $this->option('force'));
        $this->info('Student notified='.($result['student'] ? 'yes' : 'no').' supervisors='.$result['supervisors']);

        return $result['supervisors'] > 0 || $result['student'] ? 0 : 1;
    }
}
