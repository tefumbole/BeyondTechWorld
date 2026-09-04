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
        if (self::roleLooksLikeSupervisor($name) || in_array($name, ['admin', 'owner'], true)) {
            return false;
        }

        if (self::hasInternRoleName($name)) {
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

    protected static function hasInternRoleName($name)
    {
        $name = strtolower(trim((string) $name));

        return $name === 'intern' || $name === 'student' || $name === 'internship student';
    }

    protected static function roleLooksLikeSupervisor($name)
    {
        $name = strtolower(trim((string) $name));
        if ($name === '') {
            return false;
        }

        return strpos($name, 'supervisor') !== false
            || $name === 'internship administrator';
    }

    /**
     * Staff whose /admin "Dashboard" should be the intern task home (not the sales board).
     */
    public static function shouldUseInternHome(User $user)
    {
        return self::appliesTo($user);
    }

    /**
     * Staff whose /admin "Dashboard" should be the supervisor intern home.
     */
    public static function shouldUseSupervisorHome(User $user)
    {
        if (! $user || (int) $user->role_id <= 2) {
            return false;
        }
        if (self::appliesTo($user)) {
            return false;
        }

        return self::canSuperviseInternships($user);
    }

    /** URL for the sidebar Dashboard item — always the /admin home, never a module. */
    public static function dashboardUrl(User $user)
    {
        return url('/admin');
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
     * Day + hours summary (e.g. "Mon–Fri 08:00–17:00") from configured week or application JSON data.
     *
     * @param  User|null  $user
     * @param  array|null  $fallbackData  WorkingWeekForm-style array
     */
    /**
     * Full week for admin/supervisor review: slots plus the source of the data.
     *
     * @return array{slots:array,source:?string,label:?string,configured:bool}
     */
    public static function workingWeekInspect($user = null, array $fallbackData = null)
    {
        $slots = [];
        $source = null;
        if ($user instanceof User && self::workingWeekConfigured($user)) {
            $row = WorkingWeek::where('user_id', $user->id)->first();
            if ($row) {
                $slots = self::workingWeekRowToSlots($row);
                $source = 'user';
            }
        }
        if (! $slots && is_array($fallbackData) && $fallbackData) {
            $slots = self::workingWeekArrayToSlots($fallbackData);
            $source = $slots ? 'application' : null;
        }

        return [
            'slots' => $slots,
            'source' => $source,
            'label' => $slots ? self::formatWorkingWeekDetail($slots) : null,
            'configured' => $source === 'user',
        ];
    }

    public static function workingWeekDetailLabel($user = null, array $fallbackData = null)
    {
        if ($user instanceof User && self::workingWeekConfigured($user)) {
            $row = WorkingWeek::where('user_id', $user->id)->first();
            if ($row) {
                return self::formatWorkingWeekDetail(self::workingWeekRowToSlots($row));
            }
        }

        if (is_array($fallbackData) && $fallbackData) {
            return self::formatWorkingWeekDetail(self::workingWeekArrayToSlots($fallbackData));
        }

        return null;
    }

    /**
     * @param  WorkingWeek  $row
     * @return array<int, array{day:string,start:string,end:string}>
     */
    protected static function workingWeekRowToSlots(WorkingWeek $row)
    {
        $short = [
            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        ];
        $slots = [];
        foreach (WorkingWeek::days() as $day) {
            if (! $row->{$day}) {
                continue;
            }
            $slots[] = [
                'day' => $short[$day],
                'start' => substr((string) ($row->{$day.'_start'} ?: '08:00'), 0, 5),
                'end' => substr((string) ($row->{$day.'_end'} ?: '17:00'), 0, 5),
            ];
        }

        return $slots;
    }

    /**
     * @param  array  $data
     * @return array<int, array{day:string,start:string,end:string}>
     */
    protected static function workingWeekArrayToSlots(array $data)
    {
        $short = [
            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        ];
        $slots = [];
        foreach (WorkingWeek::days() as $day) {
            $on = ! empty($data[$day])
                && (filter_var($data[$day], FILTER_VALIDATE_BOOLEAN)
                    || $data[$day] === 1
                    || $data[$day] === '1'
                    || $data[$day] === true);
            if (! $on) {
                continue;
            }
            $slots[] = [
                'day' => $short[$day],
                'start' => substr((string) ($data[$day.'_start'] ?? '08:00'), 0, 5),
                'end' => substr((string) ($data[$day.'_end'] ?? '17:00'), 0, 5),
            ];
        }

        return $slots;
    }

    /**
     * @param  array<int, array{day:string,start:string,end:string}>  $slots
     */
    protected static function formatWorkingWeekDetail(array $slots)
    {
        if (! $slots) {
            return 'No days';
        }

        $days = array_column($slots, 'day');
        $starts = array_values(array_unique(array_column($slots, 'start')));
        $ends = array_values(array_unique(array_column($slots, 'end')));
        $hours = (count($starts) === 1 && count($ends) === 1)
            ? ($starts[0].'–'.$ends[0])
            : null;

        if ($days === ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] && $hours) {
            return 'Mon–Fri '.$hours;
        }

        if ($hours) {
            return implode(', ', $days).' '.$hours;
        }

        $parts = [];
        foreach ($slots as $slot) {
            $parts[] = $slot['day'].' '.$slot['start'].'–'.$slot['end'];
        }

        return implode('; ', $parts);
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

    /**
     * Date the intern should open on the fill form: overdue working day, today
     * if it is a working day, otherwise the next working day. Never a day off.
     *
     * @return string|null Y-m-d
     */
    public static function timesheetFillDate(User $user)
    {
        $missing = self::missingTimesheetDate($user);
        if ($missing) {
            return $missing;
        }

        if (! self::workingWeekConfigured($user)) {
            return null;
        }

        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            return null;
        }

        $day = Carbon::today();
        for ($i = 0; $i < 14; $i++) {
            if (self::isWorkingDayOnWeek($ww, $day)) {
                return $day->toDateString();
            }
            $day->addDay();
        }

        return null;
    }

    /**
     * @param  string|\Carbon\Carbon|null  $date
     */
    public static function isUserWorkingDate(User $user, $date)
    {
        if (! $date || ! self::workingWeekConfigured($user)) {
            return false;
        }
        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            return false;
        }
        try {
            $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        } catch (\Throwable $e) {
            return false;
        }

        return self::isWorkingDayOnWeek($ww, $carbon);
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

        return url('/admin');
    }

    /** Owner/Admin or staff who manage all internship programmes/enrolments. */
    public static function isInternshipAdmin(User $user)
    {
        if ((int) $user->role_id <= 2) {
            return true;
        }

        try {
            $role = Role::find($user->role_id);
            if (! $role) {
                return false;
            }

            return $role->hasPermissionTo('internship.programs.view')
                || $role->hasPermissionTo('internship.enrolments.create')
                || $role->hasPermissionTo('internship.programs.import');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function canSuperviseInternships(User $user)
    {
        try {
            $role = Role::find($user->role_id);
            if (! $role) {
                return false;
            }

            return $role->hasPermissionTo('internship.supervise')
                || $role->hasPermissionTo('internship.submissions.grade');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Non-admin supervisors should only see their assigned interns. */
    public static function shouldScopeSupervisees(User $user)
    {
        return (int) $user->role_id > 2 && ! self::isInternshipAdmin($user);
    }

    /** After login: pure supervisors land on Supervisor home (not /admin). */
    public static function supervisorPostLoginRedirect(User $user)
    {
        if (self::appliesTo($user)) {
            return null;
        }
        if (self::canSuperviseInternships($user) && ! self::isInternshipAdmin($user)) {
            return url('/admin');
        }

        return null;
    }
}
