<?php

namespace App\Console\Commands;

use App\Services\TaskService;
use Illuminate\Console\Command;

class ProcessTaskSchedules extends Command
{
    protected $signature = 'tasks:process';
    protected $description = 'Send scheduled task notifications and due reminders';

    public function handle(TaskService $tasks)
    {
        $sent = $tasks->processScheduledSends();
        $reminders = $tasks->processReminders();
        $this->info("Scheduled sends: {$sent}; reminders: {$reminders}");

        return 0;
    }
}
