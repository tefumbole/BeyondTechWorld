<?php

namespace App\Travel;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TravelCheckout
{
    public function hold($tenantId, $departureId, $seatNo, $name, $phone, $otp, $payerPhone = null)
    {
        $phone = TravelPhone::normalize($phone);
        $payer = $payerPhone ? TravelPhone::normalize($payerPhone) : $phone;
        $name = trim((string) $name);
        if ($name === '') {
            throw new TravelException('passenger', 'Enter the passenger name.');
        }
        $purpose = 'ticket:'.$departureId.':'.$seatNo;

        return DB::transaction(function () use ($tenantId, $departureId, $seatNo, $name, $phone, $otp, $payer, $purpose) {
            if (! TravelOtp::consume($tenantId, $phone, $purpose, $otp)) {
                throw new TravelException('otp', 'That code is wrong, expired, or already used.');
            }
            $departure = $this->lockDeparture($tenantId, $departureId);
            $this->assertSellable($departure);
            $seat = $this->releaseIfExpired($this->lockSeat($departure->id, $seatNo));
            if ($seat->status !== 'free') {
                throw new TravelException('seat', 'That seat is no longer available.');
            }
            $reference = $this->reference('EP-T');
            $paymentRef = 'tv'.bin2hex(random_bytes(8));
            $bookingId = DB::table('travel_bookings')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'departure_id' => $departure->id,
                'seat_id' => $seat->id,
                'requested_seat' => $seatNo,
                'reference' => $reference,
                'passenger_name' => $name,
                'phone' => $phone,
                'payer_phone' => $payer,
                'amount_xaf' => (int) $departure->fare_xaf,
                'channel' => 'momo',
                'payment_reference' => $paymentRef,
                'payment_status' => 'pending',
                'ticket_status' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('travel_seats')->where('id', $seat->id)->update([
                'status' => 'held',
                'booking_id' => $bookingId,
                'hold_expires_at' => now()->addMinutes((int) config('travel.hold_minutes', 5)),
                'updated_at' => now(),
            ]);
            DB::table('travel_payments')->insert([
                'cloud_tenant_id' => $tenantId,
                'booking_id' => $bookingId,
                'reference' => $paymentRef,
                'amount_xaf' => (int) $departure->fare_xaf,
                'currency' => 'XAF',
                'status' => 'pending',
                'channel' => 'momo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $bookingId;
        });
    }

    public function sellCash($tenantId, $departureId, $seatNo, $name, $phone, $userId, $stationId)
    {
        $phone = TravelPhone::normalize($phone);
        $name = trim((string) $name);
        if ($name === '') {
            throw new TravelException('passenger', 'Enter the passenger name.');
        }

        return DB::transaction(function () use ($tenantId, $departureId, $seatNo, $name, $phone, $userId, $stationId) {
            $departure = $this->lockDeparture($tenantId, $departureId);
            if ((int) $departure->origin_station_id !== (int) $stationId) {
                throw new TravelException('station', 'Sell this ticket from the origin station.');
            }
            $this->assertSellable($departure);
            $seat = $this->releaseIfExpired($this->lockSeat($departure->id, $seatNo));
            if ($seat->status !== 'free') {
                throw new TravelException('seat', 'That seat is no longer available.');
            }
            $reference = $this->reference('EP-T');
            $paymentRef = 'cash'.bin2hex(random_bytes(6));
            $bookingId = DB::table('travel_bookings')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'departure_id' => $departure->id,
                'seat_id' => $seat->id,
                'requested_seat' => $seatNo,
                'reference' => $reference,
                'passenger_name' => $name,
                'phone' => $phone,
                'payer_phone' => $phone,
                'amount_xaf' => (int) $departure->fare_xaf,
                'channel' => 'cash',
                'payment_reference' => $paymentRef,
                'payment_status' => 'paid',
                'ticket_status' => 'issued',
                'sold_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('travel_seats')->where('id', $seat->id)->update([
                'status' => 'sold',
                'booking_id' => $bookingId,
                'hold_expires_at' => null,
                'updated_at' => now(),
            ]);
            DB::table('travel_payments')->insert([
                'cloud_tenant_id' => $tenantId,
                'booking_id' => $bookingId,
                'reference' => $paymentRef,
                'amount_xaf' => (int) $departure->fare_xaf,
                'currency' => 'XAF',
                'status' => 'paid',
                'channel' => 'cash',
                'finalized_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->addCash($tenantId, $userId, $stationId, (int) $departure->fare_xaf);
            $this->ticketMessage($tenantId, $bookingId);
            TravelAudit::write($tenantId, $userId, 'ticket.cash', 'booking', $bookingId, ['seat' => $seatNo]);

            return $bookingId;
        });
    }

    public function voidCash($tenantId, $bookingId, $userId)
    {
        return DB::transaction(function () use ($tenantId, $bookingId, $userId) {
            $booking = DB::table('travel_bookings')
                ->where('cloud_tenant_id', $tenantId)
                ->where('id', $bookingId)
                ->lockForUpdate()
                ->first();
            if (! $booking || $booking->channel !== 'cash' || $booking->payment_status !== 'paid') {
                throw new TravelException('void', 'Only a paid cash ticket can be voided here.');
            }
            if ($booking->ticket_status === 'boarded') {
                throw new TravelException('void', 'A boarded passenger cannot be voided.');
            }
            $departure = DB::table('travel_departures')->where('id', $booking->departure_id)->lockForUpdate()->first();
            if (in_array($departure->status, ['departed', 'arrived'], true)) {
                throw new TravelException('void', 'This departure has already left.');
            }
            if ($booking->seat_id) {
                DB::table('travel_seats')
                    ->where('id', $booking->seat_id)
                    ->where('booking_id', $booking->id)
                    ->update([
                        'status' => 'free',
                        'booking_id' => null,
                        'hold_expires_at' => null,
                        'updated_at' => now(),
                    ]);
            }
            DB::table('travel_bookings')->where('id', $booking->id)->update([
                'payment_status' => 'void',
                'ticket_status' => 'void',
                'updated_at' => now(),
            ]);
            DB::table('travel_payments')->where('reference', $booking->payment_reference)->update([
                'status' => 'void',
                'updated_at' => now(),
            ]);
            $till = DB::table('travel_tills')
                ->where('cloud_tenant_id', $tenantId)
                ->where('user_id', $booking->sold_by)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();
            if ($till) {
                DB::table('travel_tills')->where('id', $till->id)->update([
                    'expected_cash' => max(0, (int) $till->expected_cash - (int) $booking->amount_xaf),
                    'updated_at' => now(),
                ]);
            }
            TravelAudit::write($tenantId, $userId, 'ticket.void', 'booking', $booking->id, []);

            return $booking->id;
        });
    }

    public function board($tenantId, $reference, $stationId, $userId)
    {
        return DB::transaction(function () use ($tenantId, $reference, $stationId, $userId) {
            $booking = DB::table('travel_bookings')
                ->where('cloud_tenant_id', $tenantId)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();
            if (! $booking) {
                throw new TravelException('ticket', 'No ticket matches that reference.');
            }
            if ($booking->ticket_status === 'boarded') {
                throw new TravelException('ticket', 'This ticket was already scanned.');
            }
            if ($booking->ticket_status !== 'issued' || $booking->payment_status !== 'paid') {
                throw new TravelException('ticket', 'This ticket is not valid for boarding.');
            }
            $departure = DB::table('travel_departures')->where('id', $booking->departure_id)->first();
            if ((int) $departure->origin_station_id !== (int) $stationId || $departure->status !== 'boarding') {
                throw new TravelException('ticket', 'Boarding is open only at the origin while the trip is boarding.');
            }
            DB::table('travel_bookings')->where('id', $booking->id)->update([
                'ticket_status' => 'boarded',
                'boarded_at' => now(),
                'updated_at' => now(),
            ]);
            TravelAudit::write($tenantId, $userId, 'ticket.boarded', 'booking', $booking->id, []);

            return $booking->id;
        });
    }

    public function noShow($tenantId, $bookingId, $userId)
    {
        return DB::transaction(function () use ($tenantId, $bookingId, $userId) {
            $booking = DB::table('travel_bookings')->where('cloud_tenant_id', $tenantId)->where('id', $bookingId)->lockForUpdate()->first();
            $departure = $booking ? DB::table('travel_departures')->where('id', $booking->departure_id)->first() : null;
            if (! $booking || ! $departure || $departure->status !== 'departed' || $booking->ticket_status !== 'issued') {
                throw new TravelException('ticket', 'A no-show is recorded only after departure, for a ticket that was not scanned.');
            }
            DB::table('travel_bookings')->where('id', $booking->id)->update([
                'ticket_status' => 'noshow',
                'updated_at' => now(),
            ]);
            TravelAudit::write($tenantId, $userId, 'ticket.noshow', 'booking', $booking->id, []);

            return $booking->id;
        });
    }

    public function releaseIfExpired($seat)
    {
        if ($seat->status === 'held' && $seat->hold_expires_at && Carbon::now()->greaterThan(Carbon::parse($seat->hold_expires_at))) {
            DB::table('travel_seats')->where('id', $seat->id)->update([
                'status' => 'free',
                'booking_id' => null,
                'hold_expires_at' => null,
                'updated_at' => now(),
            ]);
            $seat->status = 'free';
            $seat->booking_id = null;
            $seat->hold_expires_at = null;
        }

        return $seat;
    }

    public function claim($seat, $booking)
    {
        $activeHold = $seat->status === 'held'
            && (int) $seat->booking_id === (int) $booking->id
            && $seat->hold_expires_at
            && Carbon::now()->lessThanOrEqualTo(Carbon::parse($seat->hold_expires_at));
        if ($activeHold || $seat->status === 'free') {
            DB::table('travel_seats')->where('id', $seat->id)->update([
                'status' => 'sold',
                'booking_id' => $booking->id,
                'hold_expires_at' => null,
                'updated_at' => now(),
            ]);
            DB::table('travel_bookings')->where('id', $booking->id)->where('ticket_status', 'none')->update([
                'seat_id' => $seat->id,
                'payment_status' => 'paid',
                'ticket_status' => 'issued',
                'updated_at' => now(),
            ]);
            $this->ticketMessage($booking->cloud_tenant_id, $booking->id);

            return 'issued';
        }
        DB::table('travel_bookings')->where('id', $booking->id)->update([
            'payment_status' => 'unallocated',
            'seat_id' => null,
            'ticket_status' => 'none',
            'updated_at' => now(),
        ]);
        app(TravelNotifier::class)->queue(
            $booking->cloud_tenant_id,
            $booking->phone,
            'Epic Travels received payment for '.$booking->reference.', but seat '.$booking->requested_seat.' was no longer free. Our desk will contact you. We will not move you to a different seat.',
            'unallocated:'.$booking->id
        );

        return 'unallocated';
    }

    public function releaseHold($payment)
    {
        if (! $payment->booking_id) {
            return;
        }
        $booking = DB::table('travel_bookings')->where('id', $payment->booking_id)->first();
        if (! $booking || ! $booking->seat_id || $booking->ticket_status !== 'none') {
            return;
        }
        DB::table('travel_seats')
            ->where('id', $booking->seat_id)
            ->where('booking_id', $booking->id)
            ->where('status', 'held')
            ->update([
                'status' => 'free',
                'booking_id' => null,
                'hold_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    protected function ticketMessage($tenantId, $bookingId)
    {
        $booking = DB::table('travel_bookings')->where('id', $bookingId)->first();
        $departure = DB::table('travel_departures')->where('id', $booking->departure_id)->first();
        app(TravelNotifier::class)->queue(
            $tenantId,
            $booking->phone,
            'Epic Travels ticket '.$booking->reference.'. Seat '.$booking->requested_seat.' on '.$departure->operating_date.' at '.$departure->depart_local.'.'
            .($departure->via ? ' Stops at '.$departure->via.'.' : '')
            .' Show this reference at the station.',
            'ticket:'.$booking->id
        );
    }

    protected function addCash($tenantId, $userId, $stationId, $amount)
    {
        $till = DB::table('travel_tills')
            ->where('cloud_tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();
        if (! $till) {
            $id = DB::table('travel_tills')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'user_id' => $userId,
                'station_id' => $stationId,
                'opened_at' => now(),
                'expected_cash' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            TravelAudit::write($tenantId, $userId, 'till.opened', 'till', $id, []);

            return;
        }
        DB::table('travel_tills')->where('id', $till->id)->update([
            'expected_cash' => (int) $till->expected_cash + $amount,
            'updated_at' => now(),
        ]);
    }

    public function closeTill($tenantId, $userId, $declared)
    {
        return DB::transaction(function () use ($tenantId, $userId, $declared) {
            $till = DB::table('travel_tills')
                ->where('cloud_tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();
            if (! $till) {
                throw new TravelException('till', 'There is no open till.');
            }
            DB::table('travel_tills')->where('id', $till->id)->update([
                'closed_at' => now(),
                'declared_cash' => (int) $declared,
                'updated_at' => now(),
            ]);
            TravelAudit::write($tenantId, $userId, 'till.closed', 'till', $till->id, [
                'expected' => (int) $till->expected_cash,
                'declared' => (int) $declared,
            ]);

            return $till->id;
        });
    }

    protected function lockDeparture($tenantId, $departureId)
    {
        $departure = DB::table('travel_departures')
            ->where('cloud_tenant_id', $tenantId)
            ->where('id', $departureId)
            ->lockForUpdate()
            ->first();
        if (! $departure) {
            throw new TravelException('departure', 'That departure was not found.');
        }

        return $departure;
    }

    protected function lockSeat($departureId, $seatNo)
    {
        $seat = DB::table('travel_seats')
            ->where('departure_id', $departureId)
            ->where('seat_no', (int) $seatNo)
            ->lockForUpdate()
            ->first();
        if (! $seat) {
            throw new TravelException('seat', 'That seat is not on this bus.');
        }

        return $seat;
    }

    protected function assertSellable($departure)
    {
        if (! in_array($departure->status, ['scheduled', 'loading', 'boarding', 'delayed'], true)) {
            throw new TravelException('departure', 'This departure is not open for sale.');
        }
    }

    protected function reference($prefix)
    {
        do {
            $reference = $prefix.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (DB::table('travel_bookings')->where('reference', $reference)->exists());

        return $reference;
    }
}
