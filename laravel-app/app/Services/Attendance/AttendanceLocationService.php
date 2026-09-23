<?php

namespace App\Services\Attendance;

use App\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceLocationService
{
    public function maxAgeMinutes()
    {
        $minutes = (int) config('services.whatsapp.attendance_location_max_age_minutes', 15);

        return $minutes > 0 ? $minutes : 15;
    }

    public function defaultRadius()
    {
        $radius = (int) config('services.whatsapp.attendance_geofence_radius_meters', 150);

        return $radius > 0 ? $radius : 150;
    }

    public function validCoordinates($latitude, $longitude)
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
    }

    public function fresh($sharedAt)
    {
        if (! $sharedAt) {
            return false;
        }
        $at = $sharedAt instanceof Carbon ? $sharedAt : Carbon::parse($sharedAt);

        return $at->greaterThanOrEqualTo(Carbon::now()->subMinutes($this->maxAgeMinutes()));
    }

    /**
     * Metres between two WGS84 points. Deterministic haversine. Not an AI estimate.
     */
    public function distanceMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earth = 6371000;
        $lat1 = deg2rad((float) $lat1);
        $lat2 = deg2rad((float) $lat2);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad((float) $lon2 - (float) $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos($lat1) * cos($lat2) * sin($dLon / 2) * sin($dLon / 2);

        return (int) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @return array{status:string,distance_meters:?int,allowed_radius_meters:?int}
     */
    public function verify($latitude, $longitude, $expectedLat, $expectedLng, $radius)
    {
        if (! $this->validCoordinates($expectedLat, $expectedLng)) {
            return [
                'status' => 'UNVERIFIED',
                'distance_meters' => null,
                'allowed_radius_meters' => null,
            ];
        }
        $allowed = (int) $radius > 0 ? (int) $radius : $this->defaultRadius();
        $distance = $this->distanceMeters($latitude, $longitude, $expectedLat, $expectedLng);

        return [
            'status' => $distance <= $allowed ? 'LOCATION_VERIFIED' : 'LOCATION_REVIEW_REQUIRED',
            'distance_meters' => $distance,
            'allowed_radius_meters' => $allowed,
        ];
    }

    public function forgetExpiredCoordinates()
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasColumn('attendances', 'latitude')) {
            return 0;
        }
        $days = (int) config('services.whatsapp.attendance_location_retention_days', 90);
        if ($days < 1) {
            return 0;
        }
        $cutoff = Carbon::now()->subDays($days);

        return Attendance::whereNotNull('latitude')
            ->where(function ($q) use ($cutoff) {
                $q->where('location_at', '<', $cutoff)->orWhere(function ($q2) use ($cutoff) {
                    $q2->whereNull('location_at')->where('created_at', '<', $cutoff);
                });
            })
            ->update([
                'latitude' => null,
                'longitude' => null,
                'location_accuracy' => null,
            ]);
    }
}
