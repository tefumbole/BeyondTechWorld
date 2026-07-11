<?php

namespace App\Services;

use App\TimesheetActivity;
use App\TimesheetEntry;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TimesheetService
{
    public function activities()
    {
        return TimesheetActivity::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Entries for a user within a given month (Y-m). Defaults to current month.
     */
    public function entriesForMonth($userId, $month = null)
    {
        $date = $month ? Carbon::createFromFormat('Y-m', $month) : Carbon::now();
        $start = $date->copy()->startOfMonth()->toDateString();
        $end = $date->copy()->endOfMonth()->toDateString();

        return TimesheetEntry::with('activity')
            ->where('be_user_id', $userId)
            ->whereBetween('entry_date', [$start, $end])
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->get();
    }

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
            'id' => (string) Str::uuid(),
            'be_user_id' => $userId,
            'activity_id' => $data['activity_id'] ?? null,
            'activity_name' => $activityName,
            'entry_date' => $data['entry_date'],
            'hours' => $data['hours'],
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
        ]);
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

    public function deleteEntry($userId, $id)
    {
        return TimesheetEntry::where('be_user_id', $userId)->where('id', $id)->delete();
    }

    /**
     * Monthly summary stats for a collection of entries.
     */
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
}
