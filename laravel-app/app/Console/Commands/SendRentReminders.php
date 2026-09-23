<?php

namespace App\Console\Commands;

use App\Services\Property\RentReminderService;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'property:rent-reminders {--date=}';

    protected $description = 'Record one rent reminder per obligation and reminder type';

    public function handle(RentReminderService $reminders)
    {
        $sent = $reminders->run($this->option('date'));
        $this->info('Rent reminders recorded: '.$sent);

        return 0;
    }
}
