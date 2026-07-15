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

    public function fill(Request $request)
    {
        $this->authorizeEmployee();
        $user = Auth::user();
        $activities = $this->timesheet->activities($user->id);
        $mine = $this->timesheet->activitiesForOwner($user->id);
        $activities = $activities->merge($mine)->unique('id')->values();
        $entries = $this->timesheet->entriesRecent($user->id);

        return view('timesheet.employee.fill', compact('activities', 'entries'));
    }

    public function storeEntry(Request $request)
    {
        $this->authorizeEmployee();
        $data = $request->validate([
            'entry_date' => 'required|date',
            'activity_id' => 'required|string',
            'hours' => 'required|numeric|min:0.25|max:24',
            'notes' => 'nullable|string|max:2000',
        ]);
        $this->timesheet->addEntryAdmin(Auth::user(), $data);

        return back()->with('message', 'Time entry saved.');
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
        $updated = $this->timesheet->updateEntryAdmin(Auth::id(), $id, $data);
        if (! $updated) {
            return back()->with('not_permitted', 'Entry not found.');
        }

        return back()->with('message', 'Entry updated.');
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

        return view('timesheet.employee.working_week', compact('ww', 'summary'));
    }

    public function saveWorkingWeek(Request $request)
    {
        $this->authorizeEmployee();
        $this->timesheet->saveWorkingWeek(Auth::id(), $request->all());

        return back()->with('message', 'Working week saved.');
    }
}
