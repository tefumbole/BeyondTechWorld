<?php

namespace App\Console\Commands;

use App\Services\Internship\InternshipProgramService;
use Illuminate\Console\Command;

class InternshipTimesheetReminders extends Command
{
    protected $signature = 'internship:timesheet-reminders';

    protected $description = 'Remind interns once per day on WhatsApp about a working day with no timesheet entry';

    public function handle(InternshipProgramService $service)
    {
        $n = $service->remindMissingTimesheets();
        $this->info("Sent {$n} timesheet reminder(s).");

        return 0;
    }
}
