<?php

namespace App\Http\Controllers;

use App\Services\TimesheetService;
use App\TimesheetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class TimesheetAdminController extends Controller
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

    protected function authorizeAdmin()
    {
        if (in_array('timesheets_module', $this->all_permission, true)
            || in_array('timesheets.admin', $this->all_permission, true)
            || in_array('timesheets.manage', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Timesheet Admin.');
    }

    public function report(Request $request)
    {
        $this->authorizeAdmin();
        $from = $request->get('from', now()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $userId = $request->get('user_id', 'all');
        $employees = $this->timesheet->employeeOptions();
        $report = null;
        if ($request->has('generate') || $request->has('from')) {
            $report = $this->timesheet->report($from, $to, $userId);
        }

        return view('timesheet.admin.report', compact('from', 'to', 'userId', 'employees', 'report'));
    }

    public function overtime(Request $request)
    {
        $this->authorizeAdmin();
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $userId = $request->get('user_id', 'all');
        $employees = $this->timesheet->employeeOptions();
        $rows = $this->timesheet->overtimeReport($from, $to, $userId);

        return view('timesheet.admin.overtime', compact('from', 'to', 'userId', 'employees', 'rows'));
    }

    public function manage(Request $request)
    {
        $this->authorizeAdmin();
        $userId = $request->get('user_id', 'all');
        $month = $request->get('month', now()->format('Y-m'));
        $employees = $this->timesheet->employeeOptions();
        $items = $this->timesheet->adminEntries(null, null, $userId, $month);
        $totalHours = round((float) collect($items->items())->sum('hours'), 1);

        return view('timesheet.admin.manage', compact('items', 'employees', 'userId', 'month', 'totalHours'));
    }

    public function updateEntryStatus(Request $request, $id)
    {
        $this->authorizeAdmin();
        $status = $request->input('status', 'approved');
        $this->timesheet->updateEntryStatus($id, $status);

        return back()->with('message', 'Entry status updated.');
    }

    public function destroyEntry($id)
    {
        $this->authorizeAdmin();
        $this->timesheet->deleteEntryById($id);

        return back()->with('message', 'Entry deleted.');
    }

    public function categories()
    {
        $this->authorizeAdmin();
        $items = $this->timesheet->allCategories();

        return view('timesheet.admin.categories', compact('items'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
        ]);
        $this->timesheet->storeCategory($data);

        return back()->with('message', 'Category added.');
    }

    public function updateCategory(Request $request, $id)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
        ]);
        $this->timesheet->updateCategory(TimesheetCategory::findOrFail($id), $data);

        return back()->with('message', 'Category updated.');
    }

    public function destroyCategory($id)
    {
        $this->authorizeAdmin();
        $this->timesheet->deleteCategory($id);

        return back()->with('message', 'Category deleted.');
    }
}
