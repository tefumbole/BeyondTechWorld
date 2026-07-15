<?php

namespace App\Services;

use App\TimesheetActivity;
use App\TimesheetCategory;
use App\TimesheetEntry;
use App\User;
use App\WorkingWeek;
use Carbon\Carbon;
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
        $q = TimesheetActivity::where('is_active', true)->orderBy('name');
        if ($userId) {
            $q->where(function ($w) use ($userId) {
                $w->whereNull('owner_user_id')->orWhere('owner_user_id', $userId);
            });
        }
        if ($category && $category !== 'all') {
            $q->where(function ($w) use ($category) {
                $w->where('category', $category)->orWhere('category_id', $category);
            });
        }

        return $q->get();
    }

    public function activitiesForOwner($userId, $category = null)
    {
        $q = TimesheetActivity::where('owner_user_id', $userId)->orderBy('name');
        if ($category && $category !== 'all') {
            $q->where(function ($w) use ($category) {
                $w->where('category', $category)->orWhere('category_id', $category);
            });
        }

        return $q->get();
    }

    public function storeActivity($userId, array $data)
    {
        $catName = $data['category'] ?? null;
        if (! empty($data['category_id'])) {
            $cat = TimesheetCategory::find($data['category_id']);
            $catName = $cat ? $cat->name : $catName;
        }

        return TimesheetActivity::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'category' => $catName,
            'color' => $data['color'] ?? '#003D82',
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
        if (! empty($data['category_id'])) {
            $cat = TimesheetCategory::find($data['category_id']);
            $data['category'] = $cat ? $cat->name : ($data['category'] ?? $a->category);
        }
        $a->fill([
            'name' => $data['name'] ?? $a->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $a->description,
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $a->category_id,
            'category' => $data['category'] ?? $a->category,
            'color' => $data['color'] ?? $a->color,
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

        return TimesheetEntry::create([
            'user_id' => $user->id,
            'employee_name' => $user->name,
            'be_user_id' => null,
            'activity_id' => $data['activity_id'] ?? null,
            'activity_name' => $activityName,
            'entry_date' => $data['entry_date'],
            'hours' => $data['hours'],
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
        ]);
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

        return TimesheetEntry::create([
            'be_user_id' => $userId,
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
        $entry->update([
            'activity_id' => $data['activity_id'] ?? $entry->activity_id,
            'activity_name' => $activityName,
            'entry_date' => $data['entry_date'] ?? $entry->entry_date,
            'hours' => $data['hours'] ?? $entry->hours,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $entry->notes,
        ]);

        return $entry;
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
        return TimesheetEntry::where('user_id', $userId)->where('id', $id)->delete();
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
        $row->fill($payload);
        $row->save();

        return $row;
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
        $mins -= (int) $ww->lunch_break_minutes;
        if ($mins < 0) {
            $mins = 0;
        }

        return round($mins / 60, 2);
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
        $q = TimesheetEntry::query()->orderByDesc('entry_date')->orderByDesc('created_at');
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
        if ($userId && $userId !== 'all') {
            $q->where('user_id', $userId);
        }

        return $q->paginate(50);
    }

    public function report($from, $to, $userId = null)
    {
        $q = TimesheetEntry::query()
            ->whereBetween('entry_date', [$from, $to])
            ->orderBy('entry_date');
        if ($userId && $userId !== 'all') {
            $q->where('user_id', $userId);
        }
        $rows = $q->get();
        $byEmployee = $rows->groupBy(function ($e) {
            return $e->employee_name ?: ('User #' . $e->user_id);
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

    public function overtimeReport($from, $to, $userId = null)
    {
        $q = TimesheetEntry::query()->whereBetween('entry_date', [$from, $to]);
        if ($userId && $userId !== 'all') {
            $q->where('user_id', $userId);
        }
        $entries = $q->get();

        $grouped = [];
        foreach ($entries as $e) {
            if (! $e->user_id) {
                continue;
            }
            $weekStart = Carbon::parse($e->entry_date)->startOfWeek(Carbon::MONDAY)->toDateString();
            $key = $e->user_id . '|' . $weekStart;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'user_id' => $e->user_id,
                    'employee_name' => $e->employee_name ?: ('User #' . $e->user_id),
                    'week_start' => $weekStart,
                    'total_hours' => 0.0,
                ];
            }
            $grouped[$key]['total_hours'] += (float) $e->hours;
        }

        $out = [];
        foreach ($grouped as $row) {
            $ww = WorkingWeek::where('user_id', $row['user_id'])->first();
            $expected = $ww ? $this->weeklyExpectedHours($ww) : 40.0;
            $ot = max(0, round($row['total_hours'] - $expected, 2));
            $out[] = [
                'employee_name' => $row['employee_name'],
                'user_id' => $row['user_id'],
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

    public function employeeOptions()
    {
        return User::where('is_deleted', false)->orderBy('name')->limit(300)->get(['id', 'name']);
    }

    public function updateEntryStatus($id, $status)
    {
        $entry = TimesheetEntry::findOrFail($id);
        $entry->status = $status;
        $entry->save();

        return $entry;
    }

    public function deleteEntryById($id)
    {
        return TimesheetEntry::where('id', $id)->delete();
    }
}
