<?php

namespace App\Console\Commands;

use App\Services\AnnouncementService;
use Illuminate\Console\Command;

class ProcessAnnouncementSchedules extends Command
{
    protected $signature = 'announcements:process';
    protected $description = 'Send scheduled WhatsApp announcements and due reminders';

    public function handle(AnnouncementService $service)
    {
        $sent = $service->processScheduledSends();
        $reminders = $service->processReminders();
        $this->info("Scheduled sends: {$sent}; reminders: {$reminders}");

        return 0;
    }
}
