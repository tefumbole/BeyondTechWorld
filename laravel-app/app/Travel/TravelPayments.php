<?php

namespace App\Travel;

use Illuminate\Support\Facades\DB;

class TravelPayments
{
    public function collect($bookingId)
    {
        $booking = DB::table('travel_bookings')->where('id', $bookingId)->first();
        if (! $booking || $booking->payment_status !== 'pending') {
            throw new TravelException('payment', 'This booking is not awaiting payment.');
        }
        if (config('travel.sandbox')) {
            return [
                'sandbox' => true,
                'reference' => $booking->payment_reference,
            ];
        }

        return app(\App\Services\CampayPayoutService::class)->collect(
            $booking->payer_phone ?: $booking->phone,
            (int) $booking->amount_xaf,
            $booking->payment_reference,
            'Epic Travels '.$booking->reference
        );
    }

    public function collectParcel($parcelId, $phone)
    {
        $parcel = DB::table('travel_parcels')->where('id', $parcelId)->first();
        $payment = DB::table('travel_payments')
            ->where('parcel_id', $parcelId)
            ->where('status', 'pending')
            ->first();
        if (! $parcel || ! $payment || $parcel->payment_status !== 'pending') {
            throw new TravelException('payment', 'This parcel is not awaiting payment.');
        }
        if (config('travel.sandbox')) {
            return [
                'sandbox' => true,
                'reference' => $payment->reference,
                'amount' => (int) $payment->amount_xaf,
            ];
        }

        return app(\App\Services\CampayPayoutService::class)->collect(
            $phone,
            (int) $payment->amount_xaf,
            $payment->reference,
            'Epic Travels parcel '.$parcel->tracking_ref
        );
    }

    public function finalize(array $payload)
    {
        $reference = isset($payload['reference']) ? (string) $payload['reference'] : '';

        return DB::transaction(function () use ($payload, $reference) {
            $payment = DB::table('travel_payments')->where('reference', $reference)->lockForUpdate()->first();
            if (! $payment) {
                throw new TravelException('payment', 'Unknown payment.');
            }
            if ($payment->status !== 'pending') {
                return [
                    'ok' => true,
                    'duplicate' => true,
                    'status' => $payment->status,
                    'booking_id' => $payment->booking_id,
                    'parcel_id' => $payment->parcel_id,
                ];
            }
            $status = strtoupper((string) (isset($payload['status']) ? $payload['status'] : ''));
            $success = in_array($status, ['SUCCESSFUL', 'SUCCESS', 'PAID'], true);
            if (! $success) {
                DB::table('travel_payments')->where('id', $payment->id)->update([
                    'status' => 'failed',
                    'failure_reason' => $status !== '' ? $status : 'FAILED',
                    'finalized_at' => now(),
                    'updated_at' => now(),
                ]);
                app(TravelCheckout::class)->releaseHold($payment);

                return ['ok' => true, 'duplicate' => false, 'status' => 'failed'];
            }
            $amount = (int) (isset($payload['amount']) ? $payload['amount'] : 0);
            $currency = strtoupper((string) (isset($payload['currency']) ? $payload['currency'] : ''));
            if ($amount !== (int) $payment->amount_xaf || $currency !== 'XAF') {
                return ['ok' => false, 'duplicate' => false, 'status' => 'pending', 'error' => 'amount'];
            }
            if ($payment->parcel_id) {
                return $this->payParcel($payment, $payload);
            }
            $booking = DB::table('travel_bookings')->where('id', $payment->booking_id)->lockForUpdate()->first();
            $seat = DB::table('travel_seats')->where('id', $booking->seat_id)->lockForUpdate()->first();
            $checkout = app(TravelCheckout::class);
            $result = $checkout->claim($checkout->releaseIfExpired($seat), $booking);
            DB::table('travel_payments')->where('id', $payment->id)->update([
                'status' => 'paid',
                'provider_reference' => isset($payload['provider_reference']) ? $payload['provider_reference'] : null,
                'account_name' => isset($payload['account_name']) ? $payload['account_name'] : null,
                'finalized_at' => now(),
                'updated_at' => now(),
            ]);
            $this->sendMini($booking->phone, $booking->passenger_name, $payment->amount_xaf, 'Bus ticket '.$booking->reference, $booking->reference);

            return [
                'ok' => true,
                'duplicate' => false,
                'status' => $result,
                'booking_id' => $booking->id,
            ];
        });
    }

    protected function payParcel($payment, array $payload)
    {
        $parcel = DB::table('travel_parcels')->where('id', $payment->parcel_id)->lockForUpdate()->first();
        DB::table('travel_payments')->where('id', $payment->id)->update([
            'status' => 'paid',
            'provider_reference' => isset($payload['provider_reference']) ? $payload['provider_reference'] : null,
            'account_name' => isset($payload['account_name']) ? $payload['account_name'] : null,
            'finalized_at' => now(),
            'updated_at' => now(),
        ]);
        if ($parcel && $parcel->payment_status === 'pending') {
            DB::table('travel_parcels')->where('id', $parcel->id)->update([
                'payment_status' => 'paid',
                'status' => 'accepted',
                'updated_at' => now(),
            ]);
            app(TravelParcels::class)->acceptanceNotice($parcel);
            $this->sendMini($parcel->sender_phone, $parcel->sender_name, $payment->amount_xaf, 'Parcel '.$parcel->tracking_ref, $parcel->tracking_ref);
        }

        return ['ok' => true, 'duplicate' => false, 'status' => 'paid', 'parcel_id' => $payment->parcel_id];
    }

    protected function sendMini($phone, $name, $amount, $reason, $reference)
    {
        $phone = (string) $phone;
        $name = (string) $name;
        $reason = (string) $reason;
        $reference = (string) $reference;
        $amount = (int) $amount;
        app()->terminating(function () use ($phone, $name, $amount, $reason, $reference) {
            app(\App\Services\MomoMiniReceipt::class)->send([
                'phone' => $phone,
                'name' => $name,
                'amount' => $amount,
                'reason' => $reason,
                'reference' => $reference,
                'date' => date('d-m-Y H:i'),
            ]);
        });
    }
}
