<?php

namespace App\Http\Controllers;

use App\EventAssignment;
use App\EventTimesheet;
use App\Services\EventTimesheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventTimesheetController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->can('event_timesheets.view');

        $query = EventTimesheet::with(['event', 'workerProfile.customer', 'assignment'])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $timesheets = $query->paginate(25);

        return view('events.timesheets.index', compact('timesheets'));
    }

    public function approve($id, EventTimesheetService $service)
    {
        $this->can('event_timesheets.approve');

        $timesheet = EventTimesheet::findOrFail($id);
        if ($timesheet->status !== EventTimesheet::STATUS_SUBMITTED) {
            return back()->withErrors(['timesheet' => 'Only submitted timesheets can be approved.']);
        }

        $service->approve($timesheet);

        return back()->with('message', 'Timesheet approved.');
    }

    public function reject(Request $request, $id, EventTimesheetService $service)
    {
        $this->can('event_timesheets.approve');

        $timesheet = EventTimesheet::findOrFail($id);
        $data = $request->validate(['rejection_reason' => 'required|string|max:500']);
        $service->reject($timesheet, $data['rejection_reason']);

        return back()->with('message', 'Timesheet rejected.');
    }
}
