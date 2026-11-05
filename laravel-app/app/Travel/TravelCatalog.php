<?php

namespace App\Travel;

use Illuminate\Support\Facades\DB;

class TravelCatalog
{
    public static function seedStations($tenantId)
    {
        foreach (config('travel.stations', []) as $station) {
            $name = is_array($station) ? $station['name'] : $station;
            $kind = is_array($station) && isset($station['kind']) ? $station['kind'] : 'terminal';
            $region = is_array($station) && ! empty($station['region']) ? $station['region'] : null;
            $existing = DB::table('travel_stations')
                ->where('cloud_tenant_id', $tenantId)
                ->where('name', $name)
                ->first();
            if ($existing) {
                DB::table('travel_stations')->where('id', $existing->id)->update([
                    'kind' => $kind,
                    'region' => $region,
                    'updated_at' => now(),
                ]);
                continue;
            }
            DB::table('travel_stations')->insert([
                'cloud_tenant_id' => $tenantId,
                'name' => $name,
                'kind' => $kind,
                'region' => $region,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public static function stations($tenantId)
    {
        return DB::table('travel_stations')
            ->where('cloud_tenant_id', $tenantId)
            ->where('active', 1)
            ->where(function ($query) {
                $query->where('kind', 'terminal')->orWhereNull('kind');
            })
            ->orderBy('name')
            ->get();
    }

    public static function stationByName($tenantId, $name)
    {
        return DB::table('travel_stations')
            ->where('cloud_tenant_id', $tenantId)
            ->where('name', $name)
            ->first();
    }

    public static function saveRoute($tenantId, $originId, $destinationId, $minutes, $confirmed)
    {
        if ((int) $originId === (int) $destinationId) {
            throw new TravelException('route', 'Choose two different stations.');
        }
        $minutes = (int) $minutes;
        if ($minutes < 1) {
            throw new TravelException('route', 'Enter the journey duration in minutes.');
        }
        $existing = DB::table('travel_routes')
            ->where('cloud_tenant_id', $tenantId)
            ->where('origin_station_id', $originId)
            ->where('destination_station_id', $destinationId)
            ->first();
        $payload = [
            'duration_minutes' => $minutes,
            'duration_confirmed' => $confirmed ? 1 : 0,
            'enabled' => 1,
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('travel_routes')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }
        $payload['cloud_tenant_id'] = $tenantId;
        $payload['origin_station_id'] = $originId;
        $payload['destination_station_id'] = $destinationId;
        $payload['created_at'] = now();

        return (int) DB::table('travel_routes')->insertGetId($payload);
    }

    public static function publishAgencyRoutes($tenantId)
    {
        self::seedStations($tenantId);
        $minutes = (int) config('travel.journey_minutes', 540);
        foreach (config('travel.corridors', []) as $corridor) {
            foreach ($corridor['to'] as $destination) {
                self::saveCorridor($tenantId, $corridor['from'], $destination, $corridor['stop'], $minutes);
                self::saveCorridor($tenantId, $destination, $corridor['from'], $corridor['stop'], $minutes);
            }
        }
    }

    public static function saveCorridor($tenantId, $from, $to, $stop, $minutes)
    {
        $origin = self::stationByName($tenantId, $from);
        $destination = self::stationByName($tenantId, $to);
        $stopStation = self::stationByName($tenantId, $stop);
        if (! $origin || ! $destination || ! $stopStation) {
            throw new TravelException('route', 'A station on this corridor is missing.');
        }
        $routeId = self::saveRoute($tenantId, $origin->id, $destination->id, $minutes, true);
        DB::table('travel_route_stops')->where('route_id', $routeId)->delete();
        DB::table('travel_route_stops')->insert([
            'cloud_tenant_id' => $tenantId,
            'route_id' => $routeId,
            'station_id' => $stopStation->id,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $routeId;
    }

    public static function viaLabel($routeId)
    {
        $names = DB::table('travel_route_stops')
            ->join('travel_stations', 'travel_stations.id', '=', 'travel_route_stops.station_id')
            ->where('travel_route_stops.route_id', $routeId)
            ->orderBy('travel_route_stops.position')
            ->pluck('travel_stations.name');

        return $names->implode(', ');
    }

    public static function durationLabel($minutes)
    {
        $minutes = (int) $minutes;
        if ($minutes === 540) {
            return 'About 9 hours';
        }
        if ($minutes < 1) {
            return '';
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        if ($rest === 0) {
            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        return $hours.'h '.$rest.'m';
    }

    /**
     * Staging departures on the confirmed corridors.
     */
    public static function stagingFixture($tenantId, $date = null)
    {
        self::publishAgencyRoutes($tenantId);
        $date = $date ?: TravelClock::today();
        $dispatch = app(TravelDispatch::class);
        $plan = [
            ['Bamenda', 'Douala', 'morning', 'vip', 'EP-VIP', 'Sample VIP driver'],
            ['Bamenda', 'Douala', 'evening', 'vip', 'EP-VIP-E', 'Sample VIP evening'],
            ['Bamenda', 'Douala', 'morning', 'regular', 'EP-REG', 'Sample regular driver'],
            ['Douala', 'Bamenda', 'morning', 'vip', 'EP-VIP-R', 'Sample return driver'],
        ];
        foreach ($plan as $row) {
            $dispatch->schedule([
                'cloud_tenant_id' => $tenantId,
                'from' => $row[0],
                'to' => $row[1],
                'date' => $date,
                'shift' => $row[2],
                'class' => $row[3],
                'bus' => $row[4],
                'driver' => $row[5],
            ]);
        }
    }

    public static function search($tenantId, $from, $to, $date)
    {
        $origin = self::stationByName($tenantId, $from);
        $destination = self::stationByName($tenantId, $to);
        if (! $origin || ! $destination) {
            return [];
        }
        $rows = DB::table('travel_departures')
            ->where('cloud_tenant_id', $tenantId)
            ->where('origin_station_id', $origin->id)
            ->where('destination_station_id', $destination->id)
            ->where('operating_date', $date)
            ->whereIn('status', ['scheduled', 'loading', 'boarding', 'delayed'])
            ->orderBy('scheduled_at')
            ->get();
        $trips = [];
        foreach ($rows as $row) {
            $row->available = self::availableSeats($row->id);
            $row->class_label = config('travel.classes.'.$row->class.'.label', $row->class);
            $row->duration_label = self::durationLabel($row->duration_minutes);
            $trips[] = $row;
        }

        return $trips;
    }

    public static function availableSeats($departureId)
    {
        return DB::table('travel_seats')
            ->where('departure_id', $departureId)
            ->where(function ($query) {
                $query->where('status', 'free')
                    ->orWhere(function ($held) {
                        $held->where('status', 'held')->where('hold_expires_at', '<', now());
                    });
            })
            ->count();
    }

    public static function money($amount)
    {
        return number_format((int) $amount, 0, '.', ',').' XAF';
    }
}
