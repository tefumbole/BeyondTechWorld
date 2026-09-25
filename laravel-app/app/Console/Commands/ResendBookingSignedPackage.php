<?php

namespace App\Console\Commands;

use App\Booking;
use App\Http\Controllers\BookingController;
use Illuminate\Console\Command;

class ResendBookingSignedPackage extends Command
{
    protected $signature = 'bookings:resend-signed-package
                            {query : Booking id, reference_no, or customer name fragment}
                            {--dry-run : List matching bookings without sending}';

    protected $description = 'Re-send signed contract PDF + booking invoice to client and CC contacts after signature';

    public function handle()
    {
        $query = trim((string) $this->argument('query'));
        if ($query === '') {
            $this->error('Provide a booking id, reference, or customer name.');
            return 1;
        }

        $bookings = Booking::with(['customer', 'contract'])
            ->where(function ($q) use ($query) {
                if (ctype_digit($query)) {
                    $q->orWhere('id', (int) $query);
                }
                $q->orWhere('reference_no', 'like', '%' . $query . '%')
                    ->orWhereHas('customer', function ($cq) use ($query) {
                        $cq->where('name', 'like', '%' . $query . '%');
                    });
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($bookings->isEmpty()) {
            $this->error('No booking matched: ' . $query);
            return 1;
        }

        if ($bookings->count() > 1 && !ctype_digit($query)) {
            $this->warn('Multiple bookings matched — resending the most recent. Use booking id to be exact.');
            foreach ($bookings as $b) {
                $signed = optional($b->contract)->signed_at ? 'signed' : 'unsigned';
                $this->line(sprintf(
                    '  #%d %s — %s — %s — cc:%s',
                    $b->id,
                    $b->reference_no,
                    optional($b->customer)->name ?: 'no customer',
                    $signed,
                    $b->cc_customer_ids ?: '-'
                ));
            }
        }

        $booking = $bookings->first();
        $this->info(sprintf(
            'Target: #%d %s — %s — signed_at=%s — cc=%s',
            $booking->id,
            $booking->reference_no,
            optional($booking->customer)->name ?: 'no customer',
            optional($booking->contract)->signed_at ?: 'null',
            $booking->cc_customer_ids ?: '-'
        ));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing sent.');
            return 0;
        }

        app(BookingController::class)->resendPostSignaturePackage($booking->id);
        $this->info('Signed package + invoice queued/sent to client and CC contacts.');

        return 0;
    }
}
