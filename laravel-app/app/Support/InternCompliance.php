<?php

namespace App\Support;

use App\InternshipEnrolment;
use App\Services\TimesheetService;
use App\User;
use App\WorkingWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Intern onboarding rules: configure working week, then log end-of-day timesheets.
 */
class InternCompliance
{
    public static function appliesTo(User $user)
    {
        if (! $user || (int) $user->role_id <= 2) {
            return false;
        }

        $role = Role::find($user->role_id);
        if (! $role) {
            return false;
        }

        $name = strtolower(trim((string) $role->name));
        if (in_array($name, ['internship supervisor', 'internship administrator', 'admin', 'owner'], true)) {
            return false;
        }

        if ($name === 'intern') {
            return true;
        }

        try {
            if (! $role->hasPermissionTo('internship.student')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return InternshipEnrolment::where('student_user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->exists();
    }

    public static function workingWeekConfigured(User $user)
    {
        if (! Schema::hasTable('be_working_week')) {
            return true;
        }

        $row = WorkingWeek::where('user_id', $user->id)->first();
        if (! $row) {
            return false;
        }

        if (Schema::hasColumn('be_working_week', 'configured_at')) {
            return ! empty($row->configured_at);
        }

        // Fallback before migration: any saved week counts.
        return true;
    }

    /**
     * Short label of the student's configured working days (e.g. "Mon–Fri"), or null if missing.
     */
    public static function workingWeekLabel(User $user)
    {
        if (! self::workingWeekConfigured($user)) {
            return null;
        }

        $row = WorkingWeek::where('user_id', $user->id)->first();
        if (! $row) {
            return null;
        }

        $short = [
            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        ];
        $active = [];
        foreach (WorkingWeek::days() as $day) {
            if ($row->{$day}) {
                $active[] = $short[$day];
            }
        }
        if (! $active) {
            return 'No days';
        }
        if ($active === ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']) {
            return 'Mon–Fri';
        }

        return implode(', ', $active);
    }

    /**
     * Most recent working day (today after EOD, or a past day within 7 days) missing a timesheet entry.
     *
     * @return string|null Y-m-d
     */
    public static function missingTimesheetDate(User $user)
    {
        if (! self::workingWeekConfigured($user)) {
            return null;
        }

        /** @var TimesheetService $svc */
        $svc = app(TimesheetService::class);
        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            return null;
        }

        $today = Carbon::today();
        for ($i = 0; $i < 7; $i++) {
            $day = $today->copy()->subDays($i);
            if (! self::isWorkingDayOnWeek($ww, $day)) {
                continue;
            }
            if ($i === 0 && ! self::isPastEndOfDay($ww, $day)) {
                continue;
            }
            if (! $svc->hasEntryOnDate($user->id, $day->toDateString())) {
                return $day->toDateString();
            }
        }

        return null;
    }

    public static function isWorkingDayOnWeek(WorkingWeek $ww, Carbon $date)
    {
        $day = strtolower($date->format('l'));

        return (bool) $ww->{$day};
    }

    public static function isPastEndOfDay(WorkingWeek $ww, Carbon $date)
    {
        $day = strtolower($date->format('l'));
        $end = $ww->{$day.'_end'} ?: '17:00';
        try {
            $endAt = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString().' '.substr($end, 0, 5));
        } catch (\Throwable $e) {
            $endAt = $date->copy()->setTime(17, 0, 0);
        }

        // Soften: allow filling from 1 hour before scheduled end.
        return now()->greaterThanOrEqualTo($endAt->copy()->subHour());
    }

    public static function postLoginRedirect(User $user)
    {
        if (! self::appliesTo($user)) {
            return null;
        }

        if (! self::workingWeekConfigured($user)) {
            return route('timesheet.working-week');
        }

        $missing = self::missingTimesheetDate($user);
        if ($missing) {
            return route('timesheet.fill', ['date' => $missing, 'intern' => 1]);
        }

        return route('internship.student.dashboard');
    }
}
