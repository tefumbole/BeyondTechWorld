<?php

namespace App\Travel;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TravelParcels
{
    protected $next = [
        'accepted' => 'assigned',
        'assigned' => 'loaded',
        'loaded' => 'in_transit',
        'in_transit' => 'received',
        'received' => 'ready',
    ];

    public function accept(array $input)
    {
        $tenantId = (int) $input['cloud_tenant_id'];
        $senderPhone = TravelPhone::normalize($input['sender_phone']);
        $receiverPhone = TravelPhone::normalize($input['receiver_phone']);
        $departure = DB::table('travel_departures')
            ->where('cloud_tenant_id', $tenantId)
            ->where('id', $input['departure_id'])
            ->first();
        if (! $departure || ! in_array($departure->status, ['scheduled', 'loading', 'boarding', 'delayed'], true)) {
            throw new TravelException('parcel', 'Choose a departure that is still at the station.');
        }
        if ((int) $departure->origin_station_id !== (int) $input['station_id']) {
            throw new TravelException('station', 'Accept the parcel at the origin station.');
        }
        $input['origin_station_id'] = $departure->origin_station_id;
        $input['destination_station_id'] = $departure->destination_station_id;
        $fee = $this->fee($tenantId, $input);

        return DB::transaction(function () use ($input, $tenantId, $senderPhone, $receiverPhone, $fee) {
            $departure = DB::table('travel_departures')
                ->where('cloud_tenant_id', $tenantId)
                ->where('id', $input['departure_id'])
                ->lockForUpdate()
                ->first();
            if (! $departure || ! in_array($departure->status, ['scheduled', 'loading', 'boarding', 'delayed'], true)) {
                throw new TravelException('parcel', 'Choose a departure that is still at the station.');
            }
            $paidNow = $input['channel'] === 'cash';
            $reference = $this->reference();
            $parcelId = DB::table('travel_parcels')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'tracking_ref' => $reference,
                'origin_station_id' => $departure->origin_station_id,
                'destination_station_id' => $departure->destination_station_id,
                'departure_id' => $departure->id,
                'sender_name' => trim($input['sender_name']),
                'sender_phone' => $senderPhone,
                'receiver_name' => trim($input['receiver_name']),
                'receiver_phone' => $receiverPhone,
                'description' => trim($input['description']),
                'weight_grams' => isset($input['weight_grams']) ? (int) $input['weight_grams'] : null,
                'package_count' => max(1, (int) (isset($input['package_count']) ? $input['package_count'] : 1)),
                'fee_xaf' => $fee['amount'],
                'tariff_source' => $fee['source'],
                'status' => $paidNow ? 'accepted' : 'pending_payment',
                'payment_status' => $paidNow ? 'paid' : 'pending',
                'accepted_by' => $input['user_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $paymentRef = ($paidNow ? 'pcash' : 'ptv').bin2hex(random_bytes(6));
            DB::table('travel_payments')->insert([
                'cloud_tenant_id' => $tenantId,
                'parcel_id' => $parcelId,
                'reference' => $paymentRef,
                'amount_xaf' => $fee['amount'],
                'currency' => 'XAF',
                'status' => $paidNow ? 'paid' : 'pending',
                'channel' => $input['channel'],
                'finalized_at' => $paidNow ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->custody($tenantId, $parcelId, $input['station_id'], $input['user_id'], $paidNow ? 'accepted' : 'pending_payment', null);
            if ($paidNow) {
                $this->addParcelCash($tenantId, $input['user_id'], $input['station_id'], $fee['amount']);
                $parcel = DB::table('travel_parcels')->where('id', $parcelId)->first();
                $this->acceptanceNotice($parcel);
            }
            TravelAudit::write($tenantId, $input['user_id'], 'parcel.accepted', 'parcel', $parcelId, [
                'tracking_ref' => $reference,
                'tariff_source' => $fee['source'],
            ]);

            return $parcelId;
        });
    }

    public function scan($tenantId, $parcelId, $event, $stationId, $userId)
    {
        return DB::transaction(function () use ($tenantId, $parcelId, $event, $stationId, $userId) {
            $parcel = DB::table('travel_parcels')
                ->where('cloud_tenant_id', $tenantId)
                ->where('id', $parcelId)
                ->lockForUpdate()
                ->first();
            if (! $parcel) {
                throw new TravelException('parcel', 'That parcel was not found.');
            }
            $expected = isset($this->next[$parcel->status]) ? $this->next[$parcel->status] : null;
            if ($event !== $expected) {
                throw new TravelException('parcel', 'That scan is not the next step for this parcel.');
            }
            if (in_array($event, ['received', 'ready'], true) && (int) $stationId !== (int) $parcel->destination_station_id) {
                throw new TravelException('station', 'Only the destination station can receive this parcel.');
            }
            DB::table('travel_parcels')->where('id', $parcel->id)->update([
                'status' => $event,
                'updated_at' => now(),
            ]);
            $this->custody($tenantId, $parcel->id, $stationId, $userId, $event, null);
            if ($event === 'ready') {
                app(TravelNotifier::class)->queue(
                    $tenantId,
                    $parcel->receiver_phone,
                    'Epic Travels parcel '.$parcel->tracking_ref.' is ready for pickup at the destination station. Bring this reference.',
                    'parcel-ready:'.$parcel->id
                );
            }

            return $parcel->id;
        });
    }

    public function collect($tenantId, $parcelId, $method, $proof, $signature, $stationId, $userId)
    {
        $signature = trim((string) $signature);
        if (strlen($signature) < 20 || strlen($signature) > 200000) {
            throw new TravelException('signature', 'Add the receiver signature.');
        }

        return DB::transaction(function () use ($tenantId, $parcelId, $method, $proof, $signature, $stationId, $userId) {
            $parcel = DB::table('travel_parcels')
                ->where('cloud_tenant_id', $tenantId)
                ->where('id', $parcelId)
                ->lockForUpdate()
                ->first();
            if (! $parcel || $parcel->status === 'collected') {
                throw new TravelException('handover', 'This parcel was already handed over.');
            }
            if ($parcel->status !== 'ready' || (int) $stationId !== (int) $parcel->destination_station_id) {
                throw new TravelException('handover', 'Collection is open only at the destination, after the parcel is marked ready.');
            }
            if ($method === 'otp') {
                $ok = TravelOtp::consume($tenantId, $parcel->receiver_phone, 'parcel:'.$parcel->id, $proof);
                if (! $ok) {
                    throw new TravelException('otp', 'That pickup code is wrong, expired, or already used.');
                }
                $hint = null;
            } elseif ($method === 'id') {
                $proof = trim((string) $proof);
                if (strlen($proof) < 4) {
                    throw new TravelException('id', 'Enter the document reference you checked.');
                }
                $hint = substr($proof, -4);
            } else {
                throw new TravelException('handover', 'Choose OTP or an ID check.');
            }
            try {
                DB::table('travel_handovers')->insert([
                    'cloud_tenant_id' => $tenantId,
                    'parcel_id' => $parcel->id,
                    'station_id' => $stationId,
                    'user_id' => $userId,
                    'method' => $method,
                    'id_hint' => $hint,
                    'signature' => $signature,
                    'collected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                throw new TravelException('handover', 'This parcel was already handed over.');
            }
            DB::table('travel_parcels')->where('id', $parcel->id)->update([
                'status' => 'collected',
                'updated_at' => now(),
            ]);
            $this->custody($tenantId, $parcel->id, $stationId, $userId, 'collected', $method);
            TravelAudit::write($tenantId, $userId, 'parcel.collected', 'parcel', $parcel->id, ['method' => $method]);

            return $parcel->id;
        });
    }

    public function acceptanceNotice($parcel)
    {
        app(TravelNotifier::class)->queue(
            $parcel->cloud_tenant_id,
            $parcel->sender_phone,
            'Epic Travels accepted parcel '.$parcel->tracking_ref.'. Keep this reference to track it.',
            'parcel-accept:'.$parcel->id
        );
    }

    public function publicStatus($tenantId, $reference)
    {
        $parcel = DB::table('travel_parcels')
            ->where('cloud_tenant_id', $tenantId)
            ->where('tracking_ref', strtoupper(trim((string) $reference)))
            ->first();
        if (! $parcel) {
            return null;
        }
        $origin = DB::table('travel_stations')->where('id', $parcel->origin_station_id)->value('name');
        $destination = DB::table('travel_stations')->where('id', $parcel->destination_station_id)->value('name');
        $labels = [
            'pending_payment' => 'Waiting for payment',
            'accepted' => 'Accepted',
            'assigned' => 'Assigned',
            'loaded' => 'Loaded',
            'in_transit' => 'In transit',
            'received' => 'Received at destination',
            'ready' => 'Ready for pickup',
            'collected' => 'Collected',
        ];

        $via = $parcel->departure_id
            ? DB::table('travel_departures')->where('id', $parcel->departure_id)->value('via')
            : null;

        return [
            'reference' => $parcel->tracking_ref,
            'from' => $origin,
            'to' => $destination,
            'via' => $via,
            'status' => isset($labels[$parcel->status]) ? $labels[$parcel->status] : $parcel->status,
        ];
    }

    protected function fee($tenantId, array $input)
    {
        $weight = isset($input['weight_grams']) ? (int) $input['weight_grams'] : 0;
        $rule = DB::table('travel_tariffs')
            ->where('cloud_tenant_id', $tenantId)
            ->where('origin_station_id', $input['origin_station_id'])
            ->where('destination_station_id', $input['destination_station_id'])
            ->where('active', 1)
            ->where('min_grams', '<=', $weight)
            ->where(function ($query) use ($weight) {
                $query->whereNull('max_grams')->orWhere('max_grams', '>=', $weight);
            })
            ->orderByDesc('min_grams')
            ->first();
        if ($rule) {
            return ['amount' => (int) $rule->fee_xaf, 'source' => 'tariff'];
        }
        $manual = (int) (isset($input['fee_xaf']) ? $input['fee_xaf'] : 0);
        if ($manual < 1) {
            throw new TravelException('tariff', 'No tariff is saved for this route. Enter the fee the agency charges.');
        }

        return ['amount' => $manual, 'source' => 'manual'];
    }

    protected function addParcelCash($tenantId, $userId, $stationId, $amount)
    {
        $till = DB::table('travel_tills')
            ->where('cloud_tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();
        if (! $till) {
            DB::table('travel_tills')->insert([
                'cloud_tenant_id' => $tenantId,
                'user_id' => $userId,
                'station_id' => $stationId,
                'opened_at' => now(),
                'expected_cash' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }
        DB::table('travel_tills')->where('id', $till->id)->update([
            'expected_cash' => (int) $till->expected_cash + $amount,
            'updated_at' => now(),
        ]);
    }

    protected function custody($tenantId, $parcelId, $stationId, $userId, $event, $note)
    {
        DB::table('travel_custody')->insert([
            'cloud_tenant_id' => $tenantId,
            'parcel_id' => $parcelId,
            'station_id' => $stationId,
            'user_id' => $userId,
            'event' => $event,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function reference()
    {
        do {
            $reference = 'EP-P'.strtoupper(bin2hex(random_bytes(5)));
        } while (DB::table('travel_parcels')->where('tracking_ref', $reference)->exists());

        return $reference;
    }
}
