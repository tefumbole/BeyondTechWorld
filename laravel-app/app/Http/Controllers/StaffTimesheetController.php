<?php

namespace App\Http\Controllers;

use App\Services\TimesheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffTimesheetController extends Controller
{
    protected $timesheet;

    public function __construct(TimesheetService $timesheet)
    {
        $this->timesheet = $timesheet;
    }

    public function index(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $month = $request->query('month', now()->format('Y-m'));

        // Portal entries are keyed by be_user_id (not POS user_id).
        $entries = $this->timesheet->entriesForMonth($user->id, $month, false);
        $summary = $this->timesheet->summarize($entries);
        $activities = $this->timesheet->activitiesForPortal($user->id);

        return view('beyond.timesheet.index', compact('user', 'month', 'entries', 'summary', 'activities'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'activity_id' => 'nullable|string',
            'hours' => 'required|numeric|min:0|max:24',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::guard('beyond')->user();
        $this->timesheet->addEntry($user->id, $validated);

        return redirect()
            ->to('/staff/timesheet?month='.substr($validated['entry_date'], 0, 7))
            ->with('status', 'Timesheet entry saved.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'activity_id' => 'nullable|string',
            'hours' => 'required|numeric|min:0|max:24',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::guard('beyond')->user();
        $updated = $this->timesheet->updateEntry($user->id, $id, $validated);

        if (! $updated) {
            return back()->withErrors(['entry' => 'Entry not found for your account.']);
        }

        return redirect()
            ->to('/staff/timesheet?month='.substr($validated['entry_date'], 0, 7))
            ->with('status', 'Timesheet entry updated.');
    }

    public function destroy(Request $request, $id)
    {
        $user = Auth::guard('beyond')->user();
        $this->timesheet->deleteEntry($user->id, $id);

        return back()->with('status', 'Timesheet entry removed.');
    }
}
