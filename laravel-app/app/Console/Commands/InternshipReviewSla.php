<?php

namespace App\Console\Commands;

use App\Services\Internship\InternshipProgramService;
use Illuminate\Console\Command;

class InternshipReviewSla extends Command
{
    protected $signature = 'internship:review-sla {--remind-only}';

    protected $description = 'Nudge supervisors about waiting submissions and auto-accept those past the review SLA';

    public function handle(InternshipProgramService $service)
    {
        $reminders = $service->remindPendingReviews();
        $this->info("Sent {$reminders} review reminder(s).");

        if ($this->option('remind-only')) {
            return 0;
        }

        $accepted = $service->autoAcceptOverdueSubmissions();
        $this->info("Auto-accepted {$accepted} overdue submission(s).");

        return 0;
    }
}
