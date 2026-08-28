<?php

namespace App\Console\Commands;

use App\Services\TaskService;
use Illuminate\Console\Command;

class ProcessTaskSchedules extends Command
{
    protected $signature = 'tasks:process {--flush : Keep sending until pending assignee/CC WhatsApps are drained}';
    protected $description = 'Send scheduled/queued task WhatsApp notifications and due reminders';

    public function handle(TaskService $tasks)
    {
        $started = time();
        $sent = 0;
        do {
            $n = $tasks->processScheduledSends(8);
            $sent += $n;
            if (! $this->option('flush') || $n < 1) {
                break;
            }
        } while ((time() - $started) < 600);

        $reminders = $tasks->processReminders();
        $this->info("WhatsApp sends: {$sent}; reminders: {$reminders}");

        return 0;
    }
}
