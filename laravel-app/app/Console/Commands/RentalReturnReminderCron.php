<?php

namespace App\Console\Commands;

use App\BookingProduct;
use App\GeneralSetting;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RentalReturnReminderCron extends Command
{
    protected $signature = 'rental:return-reminders';

    protected $description = 'Send WhatsApp reminders 5 hours before rental return and late-return penalty notices';

    public function handle()
    {
        $controller = new Controller();
        $generalSetting = GeneralSetting::first();
        $company = $generalSetting->site_title ?? 'Our Company';
        $now = Carbon::now();

        $upcomingLines = BookingProduct::with(['booking.customer', 'product'])
            ->where('is_return', false)
            ->whereNull('return_reminder_sent_at')
            ->whereNotNull('end')
            ->whereBetween('end', [$now->copy()->addHours(4)->addMinutes(50), $now->copy()->addHours(5)->addMinutes(10)])
            ->get();

        foreach ($upcomingLines as $line) {
            $customer = optional($line->booking)->customer;
            if (!$customer || empty($customer->phone_number)) {
                continue;
            }

            $productName = optional($line->product)->name ?? 'Equipment';
            $returnAt = Carbon::parse($line->end)->format('d M Y, H:i');

            $msg = "*Rental Return Reminder*\n\n";
            $msg .= "Dear {$customer->name},\n\n";
            $msg .= "This is a reminder from {$company} that your rented equipment must be returned in approximately 5 hours.\n\n";
            $msg .= "Equipment: {$productName}\n";
            $msg .= "Return date/time: {$returnAt}\n";
            $msg .= "Booking Ref: " . optional($line->booking)->reference_no . "\n\n";
            $msg .= "Please ensure timely return to avoid late penalties as stated in your rental agreement.";

            try {
                $controller->wpMessage($customer->phone_number, $msg);
                $line->update(['return_reminder_sent_at' => $now]);
            } catch (\Exception $e) {
                $this->error('Reminder failed for booking product #' . $line->id . ': ' . $e->getMessage());
            }
        }

        $lateLines = BookingProduct::with(['booking.customer', 'product'])
            ->where('is_return', false)
            ->whereNull('late_notice_sent_at')
            ->whereNotNull('end')
            ->where('end', '<', $now)
            ->get();

        foreach ($lateLines as $line) {
            $customer = optional($line->booking)->customer;
            if (!$customer || empty($customer->phone_number)) {
                continue;
            }

            $productName = optional($line->product)->name ?? 'Equipment';
            $returnAt = Carbon::parse($line->end)->format('d M Y, H:i');
            $dailyRate = number_format((float) $line->net_unit_price, 2);

            $msg = \App\Support\WhatsAppMessage::lateReturnNotice(
                $customer->name,
                $company,
                $productName,
                $returnAt,
                optional($line->booking)->reference_no,
                $dailyRate
            );

            try {
                $controller->wpMessage($customer->phone_number, $msg);
                $line->update(['late_notice_sent_at' => $now]);
            } catch (\Exception $e) {
                $this->error('Late notice failed for booking product #' . $line->id . ': ' . $e->getMessage());
            }
        }

        $this->info('Rental return reminders processed.');
        return 0;
    }
}
