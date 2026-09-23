<?php

namespace App\Services\Attendance;

use App\HrmSetting;
use App\WorkingWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendancePolicyService
{
    public function officeRequiresLocation()
    {
        return filter_var(config('services.whatsapp.attendance_office_location_required', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function fieldRequiresLocation()
    {
        return filter_var(config('services.whatsapp.attendance_field_location_required', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Existing HRM flag: 1 on time, 0 late. No penalty is applied here.
     */
    public function punctualityStatus(Carbon $at, $expectedTime)
    {
        $expected = trim((string) $expectedTime);
        if ($expected === '') {
            return 1;
        }
        $clock = strtotime($at->format('H:i:s'));
        $due = strtotime(strlen($expected) === 5 ? $expected.':00' : $expected);
        if ($clock === false || $due === false) {
            return 1;
        }

        return $clock <= $due ? 1 : 0;
    }

    public function officeExpectedCheckin()
    {
        if (! Schema::hasTable('hrm_settings')) {
            return null;
        }
        $row = HrmSetting::query()->orderByDesc('id')->first();

        return $row ? $row->checkin : null;
    }

    public function workingWeek($userId)
    {
        if (! $userId || ! Schema::hasTable('be_working_week')) {
            return null;
        }

        return WorkingWeek::where('user_id', $userId)->first();
    }

    public function scheduledToday($userId, Carbon $at)
    {
        $week = $this->workingWeek($userId);
        if (! $week) {
            return ['known' => false, 'scheduled' => true, 'start' => null];
        }
        $day = strtolower($at->format('l'));
        $on = (bool) $week->{$day};

        return [
            'known' => true,
            'scheduled' => $on,
            'start' => $on ? ($week->{$day.'_start'} ?: null) : null,
            'week' => $week,
        ];
    }
}
