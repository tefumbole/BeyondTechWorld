<?php

namespace App\Travel;

use Carbon\Carbon;

class TravelClock
{
    public static function zone()
    {
        return config('travel.timezone', 'Africa/Douala');
    }

    public static function today()
    {
        return Carbon::now(self::zone())->toDateString();
    }

    public static function at($date, $time)
    {
        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, self::zone())->utc();
    }

    public static function localTime($utc)
    {
        return Carbon::parse($utc, 'UTC')->timezone(self::zone())->format('H:i');
    }

    public static function passengerLine($departure)
    {
        $labels = [
            'scheduled' => 'Scheduled',
            'loading' => 'Loading',
            'boarding' => 'Boarding',
            'delayed' => 'Delayed',
            'departed' => 'Departed',
            'arrived' => 'Arrived',
            'cancelled' => 'Cancelled',
        ];
        $status = isset($labels[$departure->status]) ? $labels[$departure->status] : $departure->status;
        if (! in_array($departure->status, ['scheduled', 'loading', 'boarding', 'delayed'], true)) {
            return $status;
        }
        $moment = $departure->estimated_departure_at ?: $departure->scheduled_at;
        $minutes = Carbon::now()->diffInMinutes(Carbon::parse($moment, 'UTC'), false);
        if ($minutes > 0) {
            return $status.' · '.intdiv($minutes, 60).'h '.($minutes % 60).'m to scheduled departure';
        }

        return $status.' · Scheduled departure time passed';
    }
}
