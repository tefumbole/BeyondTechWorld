<?php

namespace App\Http\Controllers;

use App\Services\TimesheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class TimesheetEmployeeController extends Controller
{
    protected $timesheet;
    protected $all_permission = [];

    public function __construct(TimesheetService $timesheet)
    {
        $this->timesheet = $timesheet;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeEmployee()
    {
        if (in_array('timesheets_module', $this->all_permission, true)
            || in_array('timesheets.employee', $this->all_permission, true)
            || in_array('timesheets.view', $this->all_permission, true)
            || in_array('timesheets.admin', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Timesheets.');
    }

    public function activities(Request $request)
    {
        $this->authorizeEmployee();
        $user = Auth::user();
        $categories = $this->timesheet->categories();
        $filter = $request->get('category', 'all');
        $items = $this->timesheet->activitiesForOwner($user->id, $filter);

        return view('timesheet.employee.activities', compact('items', 'categories', 'filter'));
    }

    public function storeActivity(Request $request)
    {
        $this->authorizeEmployee();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|string',
            'description' => 'nullable|string|max:2000',
        ]);
        $this->timesheet->storeActivity(Auth::id(), $data);

        return back()->with('message', 'Activity created.');
    }

    public function updateActivity(Request $request, $id)
    {
        $this->authorizeEmployee();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|string',
            'description' => 'nullable|string|max:2000',
        ]);
        $updated = $this->timesheet->updateActivity(Auth::id(), $id, $data);
        if (! $updated) {
            return back()->with('not_permitted', 'Activity not found.');
        }

        return back()->with('message', 'Activity updated.');
    }

    public function destroyActivity($id)
    {
        $this->authorizeEmployee();
        $this->timesheet->deleteActivity(Auth::id(), $id);

        return back()->with('message', 'Activity deleted.');
    }

    /**
     * Task assignment the logged-in intern owns, or null when absent/foreign.
     *
     * @return \App\InternshipTaskAssignment|null
     */
    protected function ownAssignment($assignmentId, $userId)
    {
        if (! $assignmentId) {
            return null;
        }

        $assignment = \App\InternshipTaskAssignment::with(['task', 'enrolment'])->find($assignmentId);
        if (! $assignment || (int) optional($assignment->enrolment)->student_user_id !== (int) $userId) {
            return null;
        }

        return $assignment;
    }

    public function fill(Request $request)
    {
        $this->authorizeEmployee();
        $user = Auth::user();
        if (\App\Support\InternCompliance::appliesTo($user)) {
            $this->timesheet->ensureInternshipActivity($user->id);
        }
        $activities = $this->timesheet->activities($user->id);
        $entries = $this->timesheet->entriesRecent($user->id);
        $prefillDate = $request->get('date', date('Y-m-d'));
        $internPrompt = (bool) $request->get('intern') || \App\Support\InternCompliance::appliesTo($user);
        $assignment = $this->ownAssignment($request->get('assignment'), $user->id);
        $expectedByWeekday = $this->timesheet->expectedByWeekday($user->id);
        $hoursByDate = $this->timesheet->hoursByDate($user->id);
        $weekScore = $this->timesheet->weekScore($user->id);
        $dayBalance = $this->timesheet->dayBalance($user->id, $prefillDate);

        return view('timesheet.employee.fill', compact(
            'activities', 'entries', 'prefillDate', 'internPrompt', 'assignment',
            'expectedByWeekday', 'hoursByDate', 'weekScore', 'dayBalance'
        ));
    }

    public function storeEntry(Request $request)
    {
        $this->authorizeEmployee();
        $data = $request->validate([
            'entry_date' => 'required|date',
            'activity_id' => 'required|string',
            'hours' => 'required|numeric|min:0.25|max:24',
            'notes' => 'nullable|string|max:2000',
            'assignment_id' => 'nullable|integer',
        ]);
        $assignment = $this->ownAssignment($data['assignment_id'] ?? null, Auth::id());
        $data['assignment_id'] = $assignment ? $assignment->id : null;
        if (! $this->timesheet->ownedActivity(Auth::id(), $data['activity_id'])) {
            return back()->withInput()->with('not_permitted', 'Choose an activity you created.');
        }
        $this->timesheet->addEntryAdmin(Auth::user(), $data);
        $balance = $this->timesheet->dayBalance(Auth::id(), $data['entry_date']);
        $saved = $this->timesheet->balanceMessage($balance);

        if (\App\Support\InternCompliance::appliesTo(Auth::user())) {
            $stillMissing = \App\Support\InternCompliance::missingTimesheetDate(Auth::user());
            if (! $stillMissing) {
                return redirect()->route('internship.student.dashboard')
                    ->with('message', 'Timesheet saved. '.$saved);
            }

            return redirect()->route('timesheet.fill', ['date' => $stillMissing, 'intern' => 1])
                ->with('message', $saved.' Please also log hours for '.$stillMissing.'.');
        }

        return back()->with('message', $saved);
    }

    public function updateEntry(Request $request, $id)
    {
        $this->authorizeEmployee();
        $data = $request->validate([
            'entry_date' => 'required|date',
            'activity_id' => 'required|string',
            'hours' => 'required|numeric|min:0.25|max:24',
            'notes' => 'nullable|string|max:2000',
        ]);
        if (! $this->timesheet->ownedActivity(Auth::id(), $data['activity_id'])) {
            return back()->withInput()->with('not_permitted', 'Choose an activity you created.');
        }
        $updated = $this->timesheet->updateEntryAdmin(Auth::id(), $id, $data);
        if (! $updated) {
            return back()->with('not_permitted', 'Entry not found.');
        }
        $balance = $this->timesheet->dayBalance(Auth::id(), $data['entry_date']);

        return back()->with('message', $this->timesheet->balanceMessage($balance));
    }

    public function destroyEntry($id)
    {
        $this->authorizeEmployee();
        $this->timesheet->deleteEntryAdmin(Auth::id(), $id);

        return back()->with('message', 'Entry deleted.');
    }

    public function workingWeek()
    {
        $this->authorizeEmployee();
        $ww = $this->timesheet->getOrCreateWorkingWeek(Auth::id());
        $summary = [
            'working_days' => $this->timesheet->workingDaysCount($ww),
            'expected' => $this->timesheet->weeklyExpectedHours($ww),
            'day_hours' => [],
        ];
        foreach (\App\WorkingWeek::days() as $day) {
            $summary['day_hours'][$day] = $this->timesheet->dayHours($ww, $day);
        }
        $internSetup = \App\Support\InternCompliance::appliesTo(Auth::user())
            && ! \App\Support\InternCompliance::workingWeekConfigured(Auth::user());
        $weekScore = $this->timesheet->weekScore(Auth::id());

        return view('timesheet.employee.working_week', compact('ww', 'summary', 'internSetup', 'weekScore'));
    }

    public function saveWorkingWeek(Request $request)
    {
        $this->authorizeEmployee();
        $this->timesheet->saveWorkingWeek(Auth::id(), $request->all());

        if (\App\Support\InternCompliance::appliesTo(Auth::user())) {
            $this->timesheet->ensureInternshipActivity(Auth::id());

            // Working Week just configured — release Task 1 immediately if due.
            try {
                $enrolment = \App\InternshipEnrolment::where('student_user_id', Auth::id())
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first();
                if ($enrolment) {
                    app(\App\Services\Internship\InternshipProgramService::class)
                        ->tryReleaseNext($enrolment, \Carbon\Carbon::today(), true);
                }
            } catch (\Throwable $e) {
                \Log::warning('Working week post-save task release failed: '.$e->getMessage());
            }

            $missing = \App\Support\InternCompliance::missingTimesheetDate(Auth::user());
            if ($missing) {
                return redirect()->route('timesheet.fill', ['date' => $missing, 'intern' => 1])
                    ->with('message', 'Working week saved. Please fill your timesheet for '.$missing.'.');
            }

            return redirect()->route('internship.student.dashboard')
                ->with('message', 'Working week saved. Your internship task will appear when released.');
        }

        return back()->with('message', 'Working week saved.');
    }
}
