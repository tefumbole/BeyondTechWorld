<?php

namespace App\Http\Controllers;

use App\EventAssignment;
use App\EventTimesheet;
use App\Services\EventTimesheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffEventTimesheetController extends Controller
{
    public function myEvents()
    {
        $user = Auth::guard('beyond')->user();
        $assignments = EventAssignment::with(['event', 'workerProfile'])
            ->whereHas('workerProfile', function ($q) use ($user) {
                $q->where('be_user_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->get();

        return view('beyond.my_events', compact('user', 'assignments'));
    }

    public function show($assignmentId, EventTimesheetService $service)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->findAssignmentForUser($assignmentId, $user);
        $timesheet = $service->findOrCreateForAssignment($assignment);
        $timesheet->load('entries');

        return view('beyond.event_timesheet', compact('user', 'assignment', 'timesheet'));
    }

    public function storeEntry(Request $request, $assignmentId, EventTimesheetService $service)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->findAssignmentForUser($assignmentId, $user);
        $timesheet = $service->findOrCreateForAssignment($assignment);

        if ($timesheet->status === EventTimesheet::STATUS_SUBMITTED) {
            return back()->withErrors(['entry' => 'Timesheet already submitted.']);
        }

        $data = $request->validate([
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0|max:24',
            'notes' => 'nullable|string|max:500',
        ]);

        $service->addEntry($timesheet, $data);

        return back()->with('status', 'Day logged.');
    }

    public function submit($assignmentId, EventTimesheetService $service)
    {
        $user = Auth::guard('beyond')->user();
        $assignment = $this->findAssignmentForUser($assignmentId, $user);
        $timesheet = $service->findOrCreateForAssignment($assignment);

        if ($timesheet->entries()->count() === 0) {
            return back()->withErrors(['timesheet' => 'Add at least one work day before submitting.']);
        }

        $service->submit($timesheet);

        return redirect()->route('staff.my-events')->with('status', 'Timesheet submitted for approval.');
    }

    protected function findAssignmentForUser($assignmentId, $user)
    {
        return EventAssignment::with('event')
            ->where('id', $assignmentId)
            ->whereHas('workerProfile', function ($q) use ($user) {
                $q->where('be_user_id', $user->id)->orWhere('user_id', $user->id);
            })
            ->firstOrFail();
    }
}
