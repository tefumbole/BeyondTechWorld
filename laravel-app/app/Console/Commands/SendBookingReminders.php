<?php

namespace App\Console\Commands;

use App\Http\Controllers\BookingReminderController;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send scheduled booking reminders via WhatsApp';

    public function handle()
    {
        BookingReminderController::sendDueReminders();
        $this->info('Booking reminders processed.');

        return 0;
    }
}
