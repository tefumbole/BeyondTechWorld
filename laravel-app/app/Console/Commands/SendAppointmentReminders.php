<?php

namespace App\Console\Commands;

use App\Services\Appointment\AppointmentService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'whatsapp:appointment-reminders';

    protected $description = 'Send configured appointment reminders';

    public function handle(AppointmentService $appointments)
    {
        if (! config('services.calendar.reminders_enabled')) {
            $this->info('Appointment reminders are off.');

            return 0;
        }
        $count = $appointments->sendDueReminders();
        $this->info($count.' reminder(s) sent.');

        return 0;
    }
}
