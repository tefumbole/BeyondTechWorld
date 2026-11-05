<?php

namespace App\Travel;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TravelDispatch
{
    public function schedule(array $input)
    {
        $tenantId = (int) $input['cloud_tenant_id'];
        $class = $input['class'];
        $shift = $input['shift'];
        $spec = config('travel.classes.'.$class);
        if (! $spec || ! isset($spec[$shift])) {
            throw new TravelException('schedule', 'Choose VIP or Regular, and a morning or evening shift.');
        }
        $origin = TravelCatalog::stationByName($tenantId, $input['from']);
        $destination = TravelCatalog::stationByName($tenantId, $input['to']);
        if (! $origin || ! $destination || (int) $origin->id === (int) $destination->id) {
            throw new TravelException('schedule', 'Choose two different stations.');
        }
        $route = DB::table('travel_routes')
            ->where('cloud_tenant_id', $tenantId)
            ->where('origin_station_id', $origin->id)
            ->where('destination_station_id', $destination->id)
            ->where('enabled', 1)
            ->first();
        if (! $route || ! $route->duration_minutes) {
            throw new TravelException('schedule', 'This direction is not an enabled route.');
        }
        $start = TravelClock::at($input['date'], $spec[$shift]);
        $blockUntil = $start->copy()->addMinutes((int) $route->duration_minutes + (int) config('travel.turnaround_minutes', 60));

        return DB::transaction(function () use ($input, $tenantId, $class, $shift, $spec, $origin, $destination, $route, $start, $blockUntil) {
            $vehicle = $this->vehicle($tenantId, $input['bus'], $class, (int) $spec['capacity']);
            $driver = $this->driver($tenantId, $input['driver']);
            DB::table('travel_vehicles')->where('id', $vehicle->id)->lockForUpdate()->first();
            DB::table('travel_drivers')->where('id', $driver->id)->lockForUpdate()->first();
            $this->guardOverlap($tenantId, $vehicle->id, $driver->id, $start, $blockUntil);
            $id = DB::table('travel_departures')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'route_id' => $route->id,
                'origin_station_id' => $origin->id,
                'destination_station_id' => $destination->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'class' => $class,
                'shift' => $shift,
                'operating_date' => $input['date'],
                'depart_local' => $spec[$shift],
                'duration_minutes' => (int) $route->duration_minutes,
                'via' => TravelCatalog::viaLabel($route->id),
                'scheduled_at' => $start->toDateTimeString(),
                'estimated_departure_at' => $start->toDateTimeString(),
                'block_until' => $blockUntil->toDateTimeString(),
                'status' => 'scheduled',
                'fare_xaf' => (int) $spec['fare'],
                'capacity' => (int) $spec['capacity'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->seats($tenantId, $id, (int) $spec['capacity']);
            TravelAudit::write($tenantId, isset($input['user_id']) ? $input['user_id'] : null, 'departure.scheduled', 'departure', $id, [
                'from' => $origin->name,
                'to' => $destination->name,
                'bus' => $vehicle->code,
                'driver' => $driver->name,
            ]);

            return $id;
        });
    }

    public function changeStatus($tenantId, $departureId, $status, $reason, $userId)
    {
        $allowed = [
            'scheduled' => ['loading', 'boarding', 'delayed', 'cancelled'],
            'loading' => ['boarding', 'delayed', 'cancelled'],
            'boarding' => ['departed', 'delayed', 'cancelled'],
            'delayed' => ['loading', 'boarding', 'departed', 'cancelled'],
            'departed' => ['arrived'],
        ];

        return DB::transaction(function () use ($tenantId, $departureId, $status, $reason, $userId, $allowed) {
            $departure = DB::table('travel_departures')
                ->where('cloud_tenant_id', $tenantId)
                ->where('id', $departureId)
                ->lockForUpdate()
                ->first();
            if (! $departure) {
                throw new TravelException('departure', 'That departure was not found.');
            }
            $next = isset($allowed[$departure->status]) ? $allowed[$departure->status] : [];
            if (! in_array($status, $next, true)) {
                throw new TravelException('status', 'That status change is not available.');
            }
            if (in_array($status, ['delayed', 'cancelled'], true) && trim((string) $reason) === '') {
                throw new TravelException('status', 'Enter a reason.');
            }
            $update = ['status' => $status, 'updated_at' => now()];
            if ($status === 'delayed') {
                $update['delay_reason'] = $reason;
                $update['estimated_departure_at'] = Carbon::parse($departure->estimated_departure_at ?: $departure->scheduled_at, 'UTC')
                    ->addMinutes(30)
                    ->toDateTimeString();
            }
            if ($status === 'departed') {
                $update['actual_departure_at'] = now();
            }
            if ($status === 'arrived') {
                $update['actual_arrival_at'] = now();
            }
            if ($status === 'cancelled') {
                $update['cancel_reason'] = $reason;
                $this->cancelBookings($tenantId, $departure);
            }
            DB::table('travel_departures')->where('id', $departure->id)->update($update);
            TravelAudit::write($tenantId, $userId, 'departure.'.$status, 'departure', $departure->id, [
                'reason' => $reason,
            ]);

            return $departure->id;
        });
    }

    protected function cancelBookings($tenantId, $departure)
    {
        DB::table('travel_seats')
            ->where('departure_id', $departure->id)
            ->where('status', 'held')
            ->update([
                'status' => 'free',
                'booking_id' => null,
                'hold_expires_at' => null,
                'updated_at' => now(),
            ]);
        $paid = DB::table('travel_bookings')
            ->where('departure_id', $departure->id)
            ->where('payment_status', 'paid')
            ->get();
        foreach ($paid as $booking) {
            DB::table('travel_bookings')->where('id', $booking->id)->update([
                'payment_status' => 'refund_review',
                'updated_at' => now(),
            ]);
            app(TravelNotifier::class)->queue(
                $tenantId,
                $booking->phone,
                'Epic Travels cancelled '.$booking->reference.'. Our desk will contact you about a refund or another seat.',
                'cancel:'.$booking->id
            );
        }
    }

    protected function guardOverlap($tenantId, $vehicleId, $driverId, $start, $end)
    {
        $rows = DB::table('travel_departures')
            ->where('cloud_tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'arrived'])
            ->where(function ($query) use ($vehicleId, $driverId) {
                $query->where('vehicle_id', $vehicleId)->orWhere('driver_id', $driverId);
            })
            ->lockForUpdate()
            ->get();
        foreach ($rows as $row) {
            $otherStart = Carbon::parse($row->scheduled_at, 'UTC');
            $otherEnd = Carbon::parse($row->block_until, 'UTC');
            if ($start->lt($otherEnd) && $end->gt($otherStart)) {
                throw new TravelException('overlap', 'That bus or driver is already assigned, including turnaround.');
            }
        }
    }

    protected function vehicle($tenantId, $code, $class, $capacity)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            throw new TravelException('vehicle', 'Enter the bus number.');
        }
        $row = DB::table('travel_vehicles')
            ->where('cloud_tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
        if ($row) {
            if ($row->class !== $class || (int) $row->capacity !== $capacity) {
                throw new TravelException('vehicle', 'This bus is registered as a different class.');
            }

            return $row;
        }
        $id = DB::table('travel_vehicles')->insertGetId([
            'cloud_tenant_id' => $tenantId,
            'code' => $code,
            'class' => $class,
            'capacity' => $capacity,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('travel_vehicles')->where('id', $id)->first();
    }

    protected function driver($tenantId, $name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new TravelException('driver', 'Enter the driver.');
        }
        $row = DB::table('travel_drivers')
            ->where('cloud_tenant_id', $tenantId)
            ->whereRaw('lower(name) = ?', [strtolower($name)])
            ->first();
        if ($row) {
            return $row;
        }
        $id = DB::table('travel_drivers')->insertGetId([
            'cloud_tenant_id' => $tenantId,
            'name' => $name,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('travel_drivers')->where('id', $id)->first();
    }

    protected function seats($tenantId, $departureId, $capacity)
    {
        $now = now();
        $rows = [];
        for ($number = 1; $number <= $capacity; $number++) {
            $rows[] = [
                'cloud_tenant_id' => $tenantId,
                'departure_id' => $departureId,
                'seat_no' => $number,
                'accessible' => $number === 1 ? 1 : 0,
                'status' => 'free',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 40) as $chunk) {
            DB::table('travel_seats')->insert($chunk);
        }
    }
}
