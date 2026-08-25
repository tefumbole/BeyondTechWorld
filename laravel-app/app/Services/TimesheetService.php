<?php

namespace App\Services;

use App\BeyondUser;
use App\InternshipEnrolment;
use App\Support\InternshipReportQr;
use App\TimesheetActivity;
use App\TimesheetCategory;
use App\TimesheetEntry;
use App\User;
use App\WorkingWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TimesheetService
{
    public function categories()
    {
        return TimesheetCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function allCategories()
    {
        return TimesheetCategory::orderBy('name')->get();
    }

    public function storeCategory(array $data)
    {
        return TimesheetCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#3b82f6',
            'is_active' => true,
        ]);
    }

    public function updateCategory(TimesheetCategory $cat, array $data)
    {
        $cat->fill([
            'name' => $data['name'] ?? $cat->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $cat->description,
            'color' => $data['color'] ?? $cat->color,
        ]);
        $cat->save();

        return $cat;
    }

    public function deleteCategory($id)
    {
        return TimesheetCategory::where('id', $id)->delete();
    }

    public function activities($userId = null, $category = null)
    {
        if (! $userId) {
            return collect();
        }

        return $this->activitiesForOwner($userId, $category)->where('is_active', true)->values();
    }

    public function activitiesForOwner($userId, $category = null)
    {
        $q = TimesheetActivity::with('categoryRel')
            ->where('owner_user_id', $userId)
            ->orderBy('name');
        if ($category && $category !== 'all') {
            $q->where(function ($w) use ($category) {
                $w->where('category', $category)->orWhere('category_id', $category);
            });
        }

        return $q->get();
    }

    /** Activities this Beyond portal user created — not other people's. */
    public function activitiesForPortal($beUserId)
    {
        return TimesheetActivity::with('categoryRel')
            ->where('is_active', true)
            ->where('owner_be_user_id', $beUserId)
            ->orderBy('name')
            ->get();
    }

    public function ownedActivity($userId, $activityId)
    {
        if (! $userId || ! $activityId) {
            return null;
        }

        return TimesheetActivity::where('id', $activityId)
            ->where('owner_user_id', $userId)
            ->first();
    }

    public function storeActivity($userId, array $data)
    {
        $catName = $data['category'] ?? null;
        $color = $data['color'] ?? '#003D82';
        if (! empty($data['category_id'])) {
            $cat = TimesheetCategory::find($data['category_id']);
            if ($cat) {
                $catName = $cat->name;
                $color = $cat->color ?: $color;
            }
        }

        return TimesheetActivity::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'category' => $catName,
            'color' => $color,
            'is_active' => true,
            'owner_user_id' => $userId,
        ]);
    }

    public function updateActivity($userId, $id, array $data)
    {
        $a = TimesheetActivity::where('id', $id)->where('owner_user_id', $userId)->first();
        if (! $a) {
            return null;
        }
        $color = $data['color'] ?? $a->color;
        if (array_key_exists('category_id', $data)) {
            if (! empty($data['category_id'])) {
                $cat = TimesheetCategory::find($data['category_id']);
                if ($cat) {
                    $data['category'] = $cat->name;
                    $color = $cat->color ?: $color;
                }
            } else {
                $data['category'] = null;
            }
        }
        $a->fill([
            'name' => $data['name'] ?? $a->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $a->description,
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $a->category_id,
            'category' => array_key_exists('category', $data) ? $data['category'] : $a->category,
            'color' => $color,
        ]);
        $a->save();

        return $a;
    }

    public function deleteActivity($userId, $id)
    {
        return TimesheetActivity::where('id', $id)->where('owner_user_id', $userId)->delete();
    }

    public function entriesForMonth($userId, $month = null, $viaAdmin = true)
    {
        $date = $month ? Carbon::createFromFormat('Y-m', $month) : Carbon::now();
        $start = $date->copy()->startOfMonth()->toDateString();
        $end = $date->copy()->endOfMonth()->toDateString();

        $q = TimesheetEntry::with('activity')
            ->whereBetween('entry_date', [$start, $end])
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at');

        if ($viaAdmin) {
            $q->where('user_id', $userId);
        } else {
            $q->where('be_user_id', $userId);
        }

        return $q->get();
    }

    public function entriesRecent($userId, $limit = 40)
    {
        return TimesheetEntry::where('user_id', $userId)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function addEntryAdmin($user, array $data)
    {
        $activityName = $data['activity_name'] ?? null;
        if (! empty($data['activity_id'])) {
            $activity = TimesheetActivity::find($data['activity_id']);
            if ($activity) {
                $activityName = $activity->name;
            }
        }

        $entry = TimesheetEntry::create([
            'user_id' => $user->id,
            'employee_name' => $user->name,
            'be_user_id' => null,
            'activity_id' => $data['activity_id'] ?? null,
            'activity_name' => $activityName,
            'assignment_id' => ! empty($data['assignment_id']) ? (int) $data['assignment_id'] : null,
            'entry_date' => $data['entry_date'],
            'hours' => $data['hours'],
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
        ]);

        $this->refreshDayBalance($user->id, $data['entry_date']);

        return $entry->fresh();
    }

    /** Keep portal staff path working */
    public function addEntry($userId, array $data)
    {
        $activityName = $data['activity_name'] ?? null;
        if (! empty($data['activity_id'])) {
            $activity = TimesheetActivity::find($data['activity_id']);
            if ($activity) {
                $activityName = $activity->name;
            }
        }
        $beUser = BeyondUser::find($userId);

        return TimesheetEntry::create([
            'be_user_id' => $userId,
            'user_id' => null,
            'employee_name' => $beUser ? ($beUser->name ?: $beUser->email) : null,
            'activity_id' => $data['activity_id'] ?? null,
            'activity_name' => $activityName,
            'entry_date' => $data['entry_date'],
            'hours' => $data['hours'],
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
        ]);
    }

    public function updateEntryAdmin($userId, $id, array $data)
    {
        $entry = TimesheetEntry::where('user_id', $userId)->where('id', $id)->first();
        if (! $entry) {
            return null;
        }
        $activityName = $entry->activity_name;
        if (! empty($data['activity_id'])) {
            $activity = TimesheetActivity::find($data['activity_id']);
            $activityName = $activity ? $activity->name : $activityName;
        }
        $oldDate = $entry->entry_date instanceof Carbon
            ? $entry->entry_date->toDateString()
            : (string) $entry->entry_date;
        $entry->update([
            'activity_id' => $data['activity_id'] ?? $entry->activity_id,
            'activity_name' => $activityName,
            'assignment_id' => array_key_exists('assignment_id', $data)
                ? (! empty($data['assignment_id']) ? (int) $data['assignment_id'] : null)
                : $entry->assignment_id,
            'entry_date' => $data['entry_date'] ?? $entry->entry_date,
            'hours' => $data['hours'] ?? $entry->hours,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $entry->notes,
        ]);
        $newDate = $entry->entry_date instanceof Carbon
            ? $entry->entry_date->toDateString()
            : (string) $entry->entry_date;
        $this->refreshDayBalance($userId, $newDate);
        if ($oldDate && $oldDate !== $newDate) {
            $this->refreshDayBalance($userId, $oldDate);
        }

        return $entry->fresh();
    }

    public function updateEntry($userId, $id, array $data)
    {
        $entry = TimesheetEntry::where('be_user_id', $userId)->where('id', $id)->first();
        if (! $entry) {
            return null;
        }
        $activityName = $entry->activity_name;
        if (! empty($data['activity_id'])) {
            $activity = TimesheetActivity::find($data['activity_id']);
            $activityName = $activity ? $activity->name : $activityName;
        }
        $entry->update([
            'activity_id' => $data['activity_id'] ?? $entry->activity_id,
            'activity_name' => $activityName,
            'entry_date' => $data['entry_date'] ?? $entry->entry_date,
            'hours' => $data['hours'] ?? $entry->hours,
            'notes' => $data['notes'] ?? $entry->notes,
        ]);

        return $entry;
    }

    public function deleteEntryAdmin($userId, $id)
    {
        $entry = TimesheetEntry::where('user_id', $userId)->where('id', $id)->first();
        if (! $entry) {
            return 0;
        }
        $date = $entry->entry_date instanceof Carbon
            ? $entry->entry_date->toDateString()
            : (string) $entry->entry_date;
        $deleted = $entry->delete();
        $this->refreshDayBalance($userId, $date);

        return $deleted;
    }

    public function deleteEntry($userId, $id)
    {
        return TimesheetEntry::where('be_user_id', $userId)->where('id', $id)->delete();
    }

    public function summarize($entries)
    {
        $totalHours = (float) $entries->sum('hours');
        $daysLogged = $entries->pluck('entry_date')->map(function ($d) {
            return $d instanceof Carbon ? $d->toDateString() : (string) $d;
        })->unique()->count();

        $byActivity = $entries->groupBy(function ($e) {
            return $e->activity_name ?: 'Uncategorized';
        })->map(function ($group) {
            return round((float) $group->sum('hours'), 2);
        })->sort()->reverse();

        return [
            'total_hours' => round($totalHours, 2),
            'days_logged' => $daysLogged,
            'entries_count' => $entries->count(),
            'avg_per_day' => $daysLogged ? round($totalHours / $daysLogged, 1) : 0,
            'by_activity' => $byActivity,
        ];
    }

    public function getOrCreateWorkingWeek($userId)
    {
        $row = WorkingWeek::where('user_id', $userId)->first();
        if ($row) {
            return $row;
        }

        return WorkingWeek::create(['user_id' => $userId]);
    }

    public function saveWorkingWeek($userId, array $data)
    {
        $row = $this->getOrCreateWorkingWeek($userId);
        $payload = [
            'lunch_break_minutes' => (int) ($data['lunch_break_minutes'] ?? 60),
        ];
        foreach (WorkingWeek::days() as $day) {
            $payload[$day] = ! empty($data[$day]);
            $payload[$day . '_start'] = $data[$day . '_start'] ?? $row->{$day . '_start'};
            $payload[$day . '_end'] = $data[$day . '_end'] ?? $row->{$day . '_end'};
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('be_working_week', 'configured_at')) {
            $payload['configured_at'] = now();
        }
        $row->fill($payload);
        $row->save();

        return $row;
    }

    public function hasEntryOnDate($userId, $date)
    {
        return TimesheetEntry::where('user_id', $userId)
            ->whereDate('entry_date', $date)
            ->exists();
    }

    /**
     * Ensure the intern has at least one activity they can log against.
     */
    public function ensureInternshipActivity($userId)
    {
        $owned = TimesheetActivity::where('owner_user_id', $userId)->where('is_active', true)->first();
        if ($owned) {
            return $owned;
        }

        return TimesheetActivity::create([
            'name' => 'Daily internship work',
            'description' => 'End-of-day hours for Beyond Enterprise internship tasks',
            'category' => 'Internship',
            'color' => '#0b3f90',
            'is_active' => true,
            'owner_user_id' => $userId,
        ]);
    }

    /**
     * Lunch is only deducted on days longer than 5 hours (e.g. Sat 09:00–12:00 stays 3h).
     */
    public function lunchMinutesForSpan($spanMinutes, $lunchMinutes)
    {
        $lunch = (int) $lunchMinutes;
        if ($lunch <= 0 || $spanMinutes <= 5 * 60) {
            return 0;
        }

        return $lunch;
    }

    public function dayHours(WorkingWeek $ww, $day)
    {
        if (! $ww->{$day}) {
            return 0.0;
        }
        $start = $ww->{$day . '_start'} ?: '08:00';
        $end = $ww->{$day . '_end'} ?: '17:00';
        try {
            $s = Carbon::createFromFormat('H:i', substr($start, 0, 5));
            $e = Carbon::createFromFormat('H:i', substr($end, 0, 5));
        } catch (\Exception $ex) {
            return (float) $ww->expected_hours_per_day;
        }
        $mins = $s->diffInMinutes($e, false);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        $mins -= $this->lunchMinutesForSpan($mins, $ww->lunch_break_minutes);
        if ($mins < 0) {
            $mins = 0;
        }

        return round($mins / 60, 2);
    }

    public function expectedHoursForDate($userId, $date)
    {
        $day = strtolower(Carbon::parse($date)->format('l'));
        $ww = WorkingWeek::where('user_id', $userId)->first();
        if (! $ww) {
            return in_array($day, ['saturday', 'sunday'], true) ? 0.0 : 8.0;
        }

        return $this->dayHours($ww, $day);
    }

    public function hoursLoggedOnDate($userId, $date, $exceptEntryId = null)
    {
        $q = TimesheetEntry::where('user_id', $userId)->whereDate('entry_date', $date);
        if ($exceptEntryId) {
            $q->where('id', '!=', $exceptEntryId);
        }

        return round((float) $q->sum('hours'), 2);
    }

    public function hoursByDate($userId)
    {
        $map = [];
        TimesheetEntry::where('user_id', $userId)->get(['entry_date', 'hours'])->each(function ($e) use (&$map) {
            $d = $e->entry_date instanceof Carbon
                ? $e->entry_date->toDateString()
                : Carbon::parse($e->entry_date)->toDateString();
            $map[$d] = round(($map[$d] ?? 0) + (float) $e->hours, 2);
        });

        return $map;
    }

    public function expectedByWeekday($userId)
    {
        $out = [];
        $ww = WorkingWeek::where('user_id', $userId)->first();
        foreach (WorkingWeek::days() as $day) {
            $out[$day] = $ww ? $this->dayHours($ww, $day) : (in_array($day, ['saturday', 'sunday'], true) ? 0.0 : 8.0);
        }

        return $out;
    }

    public function dayBalance($userId, $date, $exceptEntryId = null)
    {
        $expected = $this->expectedHoursForDate($userId, $date);
        $logged = $this->hoursLoggedOnDate($userId, $date, $exceptEntryId);
        $remaining = max(0, round($expected - $logged, 2));
        $overtime = max(0, round($logged - $expected, 2));

        return [
            'date' => Carbon::parse($date)->toDateString(),
            'expected' => $expected,
            'logged' => $logged,
            'remaining' => $remaining,
            'overtime' => $overtime,
            'complete' => $expected > 0 && $remaining <= 0.009 && $overtime <= 0.009,
        ];
    }

    public function balanceMessage(array $balance)
    {
        $logged = number_format((float) $balance['logged'], 2);
        $expected = number_format((float) $balance['expected'], 2);
        if ($balance['expected'] <= 0 && $balance['logged'] > 0) {
            return $logged.'h logged on a non-working day. Supervisor will need to approve overtime.';
        }
        if ($balance['overtime'] > 0.009) {
            return number_format((float) $balance['overtime'], 2).'h overtime on this day. Supervisor will need to approve overtime.';
        }
        if ($balance['remaining'] > 0.009) {
            return number_format((float) $balance['remaining'], 2).'h still remaining to complete this working day ('.$logged.' of '.$expected.'h).';
        }

        return 'Working day complete ('.$logged.' of '.$expected.'h).';
    }

    public function weekScore($userId, $weekStart = null)
    {
        $start = $weekStart
            ? Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
        $ww = WorkingWeek::where('user_id', $userId)->first();
        $expected = $ww ? $this->weeklyExpectedHours($ww) : 40.0;
        $logged = round((float) TimesheetEntry::where('user_id', $userId)
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->sum('hours'), 2);
        $days = [];
        foreach (range(0, 6) as $i) {
            $d = $start->copy()->addDays($i);
            $dayKey = strtolower($d->format('l'));
            $dayExpected = $ww ? $this->dayHours($ww, $dayKey) : ($i < 5 ? 8.0 : 0.0);
            $dayLogged = $this->hoursLoggedOnDate($userId, $d->toDateString());
            $days[] = [
                'date' => $d->toDateString(),
                'day' => $dayKey,
                'expected' => $dayExpected,
                'logged' => $dayLogged,
                'remaining' => max(0, round($dayExpected - $dayLogged, 2)),
                'overtime' => max(0, round($dayLogged - $dayExpected, 2)),
            ];
        }

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'expected' => $expected,
            'logged' => $logged,
            'remaining' => max(0, round($expected - $logged, 2)),
            'overtime' => max(0, round($logged - $expected, 2)),
            'met' => $logged + 0.009 >= $expected && $expected > 0,
            'days' => $days,
        ];
    }

    public function refreshDayBalance($userId, $date)
    {
        if (! $userId || ! $date) {
            return $this->dayBalance($userId, $date ?: date('Y-m-d'));
        }
        $balance = $this->dayBalance($userId, $date);
        $hasOtCol = Schema::hasColumn('be_timesheet_entries', 'overtime_hours');
        $hasFlagCol = Schema::hasColumn('be_timesheet_entries', 'requires_ot_approval');
        $entries = TimesheetEntry::where('user_id', $userId)->whereDate('entry_date', $date)->get();
        foreach ($entries as $e) {
            if ($hasOtCol) {
                $e->overtime_hours = $balance['overtime'];
            }
            if ($hasFlagCol) {
                $e->requires_ot_approval = $balance['overtime'] > 0.009;
            }
            if (! in_array($e->status, ['approved', 'rejected'], true)) {
                $e->status = $balance['overtime'] > 0.009 ? 'overtime_pending' : 'submitted';
            }
            $e->save();
        }

        return $balance;
    }

    public function weeklyExpectedHours(WorkingWeek $ww)
    {
        $total = 0.0;
        foreach (WorkingWeek::days() as $day) {
            $total += $this->dayHours($ww, $day);
        }

        return round($total, 2);
    }

    public function workingDaysCount(WorkingWeek $ww)
    {
        $n = 0;
        foreach (WorkingWeek::days() as $day) {
            if ($ww->{$day}) {
                $n++;
            }
        }

        return $n;
    }

    public function adminEntries($from = null, $to = null, $userId = null, $month = null)
    {
        $q = TimesheetEntry::with(['approver', 'assignment.task'])
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at');
        if ($month) {
            $date = Carbon::createFromFormat('Y-m', $month);
            $q->whereBetween('entry_date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ]);
        } else {
            if ($from) {
                $q->where('entry_date', '>=', $from);
            }
            if ($to) {
                $q->where('entry_date', '<=', $to);
            }
        }
        $this->applyEmployeeFilter($q, $userId);

        return $q->paginate(50);
    }

    public function report($from, $to, $userId = null)
    {
        $q = TimesheetEntry::query()
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date');
        $this->applyEmployeeFilter($q, $userId);
        $rows = $q->get();
        $byEmployee = $rows->groupBy(function ($e) {
            return $e->employee_name
                ?: ($e->user_id ? ('User #'.$e->user_id) : ($e->be_user_id ? ('Portal #'.substr($e->be_user_id, 0, 8)) : 'Unknown'));
        })->map(function ($group) {
            return [
                'hours' => round((float) $group->sum('hours'), 2),
                'entries' => $group->count(),
            ];
        });

        return [
            'rows' => $rows,
            'total_hours' => round((float) $rows->sum('hours'), 2),
            'by_employee' => $byEmployee,
        ];
    }

    /**
     * Summary + detailed timesheet report for one person or everyone in a period.
     */
    public function fullReport($from, $to, $userId = null)
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();
        $q = TimesheetEntry::query()
            ->with(['assignment.task'])
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date')
            ->orderBy('created_at');
        $this->applyEmployeeFilter($q, $userId);
        $rows = $q->get();

        $identities = [];
        if ($userId && $userId !== 'all') {
            $identities[] = $this->normalizeIdentity($userId);
        } else {
            foreach ($rows as $row) {
                $id = $this->entryIdentity($row);
                if ($id) {
                    $identities[$id] = $id;
                }
            }
            $identities = array_values($identities);
        }

        $people = [];
        $byActivity = [];
        $bucketLogged = [];
        $bucketExpected = [];
        $useWeeks = Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 45;

        foreach ($identities as $identity) {
            $personRows = $rows->filter(function ($row) use ($identity) {
                return $this->entryIdentity($row) === $identity;
            })->values();
            $profile = $this->personProfile($identity, optional($personRows->first())->employee_name);
            $days = $this->periodDaySeries($identity, $from, $to);
            $logged = round((float) $personRows->sum('hours'), 2);
            $expected = round((float) collect($days)->sum('expected'), 2);
            $personActivities = [];
            foreach ($personRows as $entry) {
                $act = $entry->activity_name ?: 'Uncategorized';
                $personActivities[$act] = round(($personActivities[$act] ?? 0) + (float) $entry->hours, 2);
                $byActivity[$act] = round(($byActivity[$act] ?? 0) + (float) $entry->hours, 2);
            }
            arsort($personActivities);

            foreach ($days as $day) {
                $key = $useWeeks
                    ? Carbon::parse($day['date'])->startOfWeek(Carbon::MONDAY)->format('M j')
                    : $day['label'];
                $bucketLogged[$key] = round(($bucketLogged[$key] ?? 0) + $day['logged'], 2);
                $bucketExpected[$key] = round(($bucketExpected[$key] ?? 0) + $day['expected'], 2);
            }

            $duration = $profile['duration'];
            $verifyUrl = InternshipReportQr::url([
                'name' => $profile['name'],
                'duration' => $duration['label'] ?? ($from.' – '.$to),
                'matricule' => $profile['matricule'],
            ]);

            $people[] = [
                'identity' => $identity,
                'user_id' => $profile['user_id'],
                'is_intern' => $profile['is_intern'],
                'name' => $profile['name'],
                'matricule' => $profile['matricule'],
                'program' => $profile['program'],
                'duration' => $duration,
                'logged' => $logged,
                'expected' => $expected,
                'remaining' => max(0, round($expected - $logged, 2)),
                'overtime' => max(0, round($logged - $expected, 2)),
                'activity_count' => count($personActivities),
                'entry_count' => $personRows->count(),
                'by_activity' => $personActivities,
                'days' => $days,
                'rows' => $personRows,
                'verify_url' => $verifyUrl,
            ];
        }

        usort($people, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        arsort($byActivity);

        $chartPeople = ['labels' => [], 'logged' => [], 'expected' => []];
        foreach ($people as $person) {
            $chartPeople['labels'][] = $person['name'];
            $chartPeople['logged'][] = $person['logged'];
            $chartPeople['expected'][] = $person['expected'];
        }

        return [
            'from' => $from,
            'to' => $to,
            'scope' => ($userId && $userId !== 'all') ? 'one' : 'all',
            'total_logged' => round((float) $rows->sum('hours'), 2),
            'total_expected' => round((float) collect($people)->sum('expected'), 2),
            'total_activities' => count($byActivity),
            'total_entries' => $rows->count(),
            'people_count' => count($people),
            'by_activity' => $byActivity,
            'chart_hours' => [
                'labels' => array_keys($bucketLogged),
                'logged' => array_values($bucketLogged),
                'expected' => array_values($bucketExpected),
            ],
            'chart_people' => $chartPeople,
            'people' => $people,
            'interns' => array_values(array_filter($people, function ($p) {
                return ! empty($p['is_intern']);
            })),
            'rows' => $rows,
            'by_employee' => collect($people)->mapWithKeys(function ($p) {
                return [$p['name'] => ['hours' => $p['logged'], 'entries' => $p['entry_count']]];
            }),
            'total_hours' => round((float) $rows->sum('hours'), 2),
        ];
    }

    protected function normalizeIdentity($userId)
    {
        if (strpos((string) $userId, 'be:') === 0 || strpos((string) $userId, 'pos:') === 0) {
            return (string) $userId;
        }

        return 'pos:'.(int) $userId;
    }

    protected function entryIdentity($entry)
    {
        if (! empty($entry->user_id)) {
            return 'pos:'.$entry->user_id;
        }
        if (! empty($entry->be_user_id)) {
            return 'be:'.$entry->be_user_id;
        }

        return null;
    }

    protected function personProfile($identity, $hintName = null)
    {
        $name = $hintName ?: 'Unknown';
        $userId = null;
        $isIntern = false;
        $enrolment = null;
        $program = null;
        $beyond = null;
        $user = null;

        if (strpos($identity, 'be:') === 0) {
            $beyond = BeyondUser::find(substr($identity, 3));
            if ($beyond) {
                $name = $beyond->name ?: ($beyond->email ?: $name);
            }
        } else {
            $userId = (int) (strpos($identity, 'pos:') === 0 ? substr($identity, 4) : $identity);
            $user = User::find($userId);
            if ($user) {
                $name = $user->name ?: $name;
            }
            if ($user && Schema::hasTable('internship_enrolments')) {
                $enrolment = InternshipEnrolment::with(['program', 'application'])
                    ->where('student_user_id', $user->id)
                    ->orderByDesc('id')
                    ->first();
                if ($enrolment) {
                    $isIntern = true;
                    $program = optional($enrolment->program)->displayName();
                    $appName = trim((string) optional($enrolment->application)->full_name);
                    if ($appName !== '') {
                        $name = $appName;
                    }
                }
            }
        }

        return [
            'user_id' => $userId,
            'name' => $name,
            'is_intern' => $isIntern,
            'matricule' => $this->matriculeFor($user, $beyond, $enrolment),
            'program' => $program,
            'duration' => $this->internshipDuration($enrolment),
            'enrolment' => $enrolment,
        ];
    }

    protected function matriculeFor($user, $beyond, $enrolment)
    {
        if ($enrolment) {
            $year = $enrolment->start_date
                ? Carbon::parse($enrolment->start_date)->format('Y')
                : date('Y');

            return 'BTW-'.$year.'-'.str_pad((string) $enrolment->id, 5, '0', STR_PAD_LEFT);
        }
        if ($user) {
            return 'BTW-EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
        }
        if ($beyond) {
            return 'BTW-PRT-'.strtoupper(substr((string) $beyond->id, 0, 8));
        }

        return '—';
    }

    protected function internshipDuration($enrolment)
    {
        if (! $enrolment) {
            return null;
        }
        $start = $enrolment->start_date
            ? Carbon::parse($enrolment->start_date)
            : Carbon::today();
        $days = $enrolment->plannedDurationDays();
        $end = $start->copy()->addDays(max(0, $days - 1));

        return [
            'days' => $days,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $days.' day'.($days === 1 ? '' : 's')
                .' ('.$start->format('j M Y').' – '.$end->format('j M Y').')',
        ];
    }

    protected function periodDaySeries($identity, $from, $to)
    {
        $loggedMap = $this->hoursByDateForIdentity($identity, $from, $to);
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $days[] = [
                'date' => $date,
                'label' => $d->format('M j'),
                'expected' => $this->expectedHoursForIdentity($identity, $date),
                'logged' => $loggedMap[$date] ?? 0.0,
            ];
        }

        return $days;
    }

    protected function hoursByDateForIdentity($identity, $from, $to)
    {
        $q = TimesheetEntry::query()->whereBetween('entry_date', [$from, $to]);
        $this->applyEmployeeFilter($q, $identity);
        $map = [];
        foreach ($q->get(['entry_date', 'hours']) as $entry) {
            $date = Carbon::parse($entry->entry_date)->toDateString();
            $map[$date] = round(($map[$date] ?? 0) + (float) $entry->hours, 2);
        }

        return $map;
    }

    protected function workingWeekForIdentity($identity)
    {
        if (strpos((string) $identity, 'be:') === 0) {
            return WorkingWeek::where('be_user_id', substr($identity, 3))->first();
        }
        $userId = strpos((string) $identity, 'pos:') === 0
            ? (int) substr($identity, 4)
            : (int) $identity;

        return WorkingWeek::where('user_id', $userId)->first();
    }

    public function expectedHoursForIdentity($identity, $date)
    {
        $day = strtolower(Carbon::parse($date)->format('l'));
        $ww = $this->workingWeekForIdentity($identity);
        if (! $ww) {
            return in_array($day, ['saturday', 'sunday'], true) ? 0.0 : 8.0;
        }

        return $this->dayHours($ww, $day);
    }

    protected function hoursLoggedOnIdentityDate($identity, $date)
    {
        $q = TimesheetEntry::whereDate('entry_date', $date);
        $this->applyEmployeeFilter($q, $identity);

        return round((float) $q->sum('hours'), 2);
    }

    public function overtimeReport($from, $to, $userId = null)
    {
        $q = TimesheetEntry::query()->whereBetween('entry_date', [$from, $to]);
        $this->applyEmployeeFilter($q, $userId);
        $entries = $q->get();

        $grouped = [];
        foreach ($entries as $e) {
            if ($e->user_id) {
                $identity = 'pos:'.$e->user_id;
                $name = $e->employee_name ?: ('User #'.$e->user_id);
            } elseif ($e->be_user_id) {
                $identity = 'be:'.$e->be_user_id;
                $name = $e->employee_name ?: ('Portal #'.substr($e->be_user_id, 0, 8));
            } else {
                continue;
            }
            $weekStart = Carbon::parse($e->entry_date)->startOfWeek(Carbon::MONDAY)->toDateString();
            $key = $identity.'|'.$weekStart;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'identity' => $identity,
                    'user_id' => $e->user_id,
                    'be_user_id' => $e->be_user_id,
                    'employee_name' => $name,
                    'week_start' => $weekStart,
                    'total_hours' => 0.0,
                ];
            }
            $grouped[$key]['total_hours'] += (float) $e->hours;
        }

        $out = [];
        foreach ($grouped as $row) {
            if (! empty($row['user_id'])) {
                $ww = WorkingWeek::where('user_id', $row['user_id'])->first();
            } else {
                $ww = WorkingWeek::where('be_user_id', $row['be_user_id'])->first();
            }
            $expected = $ww ? $this->weeklyExpectedHours($ww) : 40.0;
            $ot = max(0, round($row['total_hours'] - $expected, 2));
            $out[] = [
                'employee_name' => $row['employee_name'],
                'user_id' => $row['identity'],
                'week_start' => $row['week_start'],
                'total_hours' => round($row['total_hours'], 2),
                'expected_hours' => $expected,
                'overtime_hours' => $ot,
            ];
        }
        usort($out, function ($a, $b) {
            return strcmp($b['week_start'], $a['week_start']);
        });

        return $out;
    }

    /**
     * Filter by POS user (pos:123 / bare id) or portal Beyond user (be:uuid).
     */
    protected function applyEmployeeFilter($q, $userId)
    {
        if (! $userId || $userId === 'all') {
            return;
        }
        if (strpos($userId, 'be:') === 0) {
            $q->where('be_user_id', substr($userId, 3));

            return;
        }
        if (strpos($userId, 'pos:') === 0) {
            $q->where('user_id', (int) substr($userId, 4));

            return;
        }
        $q->where('user_id', $userId);
    }

    public function employeeOptions()
    {
        $out = collect();
        $internIds = [];
        if (Schema::hasTable('internship_enrolments')) {
            $internIds = InternshipEnrolment::query()
                ->whereNotNull('student_user_id')
                ->pluck('student_user_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->unique()
                ->flip()
                ->all();
        }

        User::where('is_deleted', false)->orderBy('name')->limit(300)->get(['id', 'name'])
            ->each(function ($u) use ($out, $internIds) {
                $kind = isset($internIds[(int) $u->id]) ? 'Student' : 'Staff';
                $out->push((object) [
                    'id' => 'pos:'.$u->id,
                    'name' => $u->name.' ('.$kind.')',
                ]);
            });

        $beIds = TimesheetEntry::whereNotNull('be_user_id')->distinct()->pluck('be_user_id');
        if ($beIds->isNotEmpty()) {
            BeyondUser::whereIn('id', $beIds)->orderBy('name')->get(['id', 'name', 'email'])
                ->each(function ($u) use ($out) {
                    $label = $u->name ?: $u->email ?: substr($u->id, 0, 8);
                    $out->push((object) [
                        'id' => 'be:'.$u->id,
                        'name' => $label.' (Staff)',
                    ]);
                });
        }

        return $out->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function updateEntryStatus($id, $status, $adminId = null, $note = null)
    {
        $entry = TimesheetEntry::findOrFail($id);
        $entry->status = $status;
        if (in_array($status, ['approved', 'rejected'], true)) {
            $entry->approved_by = $adminId;
            $entry->approved_at = now();
        } else {
            $entry->approved_by = null;
            $entry->approved_at = null;
        }
        if ($note !== null) {
            $entry->review_note = $note !== '' ? $note : null;
        }
        $entry->save();

        return $entry;
    }

    public function deleteEntryById($id)
    {
        return TimesheetEntry::where('id', $id)->delete();
    }
}
