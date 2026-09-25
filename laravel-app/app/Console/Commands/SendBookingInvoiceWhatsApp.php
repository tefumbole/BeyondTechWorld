<?php

namespace App\Console\Commands;

use App\Booking;
use App\Http\Controllers\BookingController;
use Illuminate\Console\Command;

class SendBookingInvoiceWhatsApp extends Command
{
    protected $signature = 'bookings:send-invoice
                            {query : Booking id, reference_no, or customer name fragment}
                            {--dry-run : Show target without sending}';

    protected $description = 'WhatsApp the booking invoice PDF to the client and CC contacts';

    public function handle()
    {
        $query = trim((string) $this->argument('query'));
        $bookings = Booking::with(['customer', 'bookingProduct.product', 'biller'])
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

        $booking = $bookings->first();
        $this->info(sprintf(
            'Target: #%d %s — %s — cc=%s',
            $booking->id,
            $booking->reference_no,
            optional($booking->customer)->name ?: 'no customer',
            $booking->cc_customer_ids ?: '-'
        ));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing sent.');
            return 0;
        }

        app(BookingController::class)->deliverPostSignatureReceipt($booking->id);
        $this->info('Booking invoice WhatsApped to client and CC contacts.');

        return 0;
    }
}
