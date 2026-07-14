<?php

namespace App\Services;

use App\EventAssignment;
use App\EventTimesheet;
use App\EventTimesheetEntry;
use Illuminate\Support\Facades\Auth;

class EventTimesheetService
{
    public function findOrCreateForAssignment(EventAssignment $assignment)
    {
        $existing = EventTimesheet::where('assignment_id', $assignment->id)
            ->whereIn('status', [EventTimesheet::STATUS_DRAFT, EventTimesheet::STATUS_REJECTED])
            ->first();

        if ($existing) {
            return $existing;
        }

        return EventTimesheet::create([
            'event_id' => $assignment->event_id,
            'assignment_id' => $assignment->id,
            'worker_profile_id' => $assignment->worker_profile_id,
            'status' => EventTimesheet::STATUS_DRAFT,
            'period_start' => $assignment->work_start_date,
            'period_end' => $assignment->work_end_date,
        ]);
    }

    public function addEntry(EventTimesheet $timesheet, array $data)
    {
        $entry = EventTimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => $data['work_date'],
            'hours' => $data['hours'] ?? 8,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->recalculateTotals($timesheet);

        return $entry;
    }

    public function recalculateTotals(EventTimesheet $timesheet)
    {
        $entries = $timesheet->entries()->get();
        $timesheet->update([
            'total_hours' => round($entries->sum('hours'), 2),
            'total_days' => $entries->count(),
        ]);
    }

    public function submit(EventTimesheet $timesheet)
    {
        $timesheet->update([
            'status' => EventTimesheet::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);
        $timesheet->assignment->update(['timesheet_status' => 'submitted']);

        return $timesheet->fresh();
    }

    public function approve(EventTimesheet $timesheet)
    {
        $timesheet->update([
            'status' => EventTimesheet::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);
        $timesheet->assignment->update(['timesheet_status' => 'approved']);

        return $timesheet->fresh();
    }

    public function reject(EventTimesheet $timesheet, $reason)
    {
        $timesheet->update([
            'status' => EventTimesheet::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
        $timesheet->assignment->update(['timesheet_status' => 'rejected']);

        return $timesheet->fresh();
    }
}
