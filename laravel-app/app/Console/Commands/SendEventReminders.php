<?php

namespace App\Console\Commands;

use App\Services\EventReminderService;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature = 'events:process-reminders';

    protected $description = 'Send due event reminders via WhatsApp';

    public function handle(EventReminderService $service)
    {
        $service->processDueReminders();
        $this->info('Event reminders processed.');

        return 0;
    }
}
