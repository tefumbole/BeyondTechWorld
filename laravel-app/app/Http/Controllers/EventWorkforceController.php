<?php

namespace App\Http\Controllers;

use App\Event;
use App\EventAssignment;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use App\Services\EventWorkforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventWorkforceController extends Controller
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

    public function profiles(Request $request)
    {
        $this->can('event_workers.view');

        $query = EventWorkerProfile::with(['customer', 'category'])->orderBy('created_at', 'desc');
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($w) use ($q) {
                $w->where('telephone', 'like', $q)->orWhere('email', 'like', $q)
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('name', 'like', $q);
                    });
            });
        }

        $profiles = $query->paginate(25);
        $categories = EventWorkerCategory::active()->orderBy('name')->get();

        return view('events.workforce.profiles', compact('profiles', 'categories'));
    }

    public function storeProfile(Request $request, EventWorkforceService $workforce)
    {
        $this->can('event_workers.create');

        if ($request->filled('customer_id') && ! $request->filled('name')) {
            $profile = $workforce->profileFromCustomer(
                $request->customer_id,
                $request->worker_category_id
            );

            return back()->with('message', 'Worker profile linked from customer: ' . $profile->displayName());
        }

        $data = $request->validate([
            'worker_category_id' => 'required|exists:event_worker_categories,id',
            'customer_id' => 'nullable|exists:customers,id',
            'user_id' => 'nullable|exists:users,id',
            'standard_daily_rate' => 'required|integer|min:0',
            'standard_hourly_rate' => 'nullable|integer|min:0',
            'specialization' => 'nullable|string|max:128',
            'telephone' => 'nullable|string|max:64',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
        ]);

        $workforce->createProfile($data);

        return back()->with('message', 'Worker profile created.');
    }

    public function assign(Request $request, $eventId, EventWorkforceService $workforce)
    {
        $this->can('events.manage_workforce');

        $event = Event::findOrFail($eventId);
        $data = $request->validate([
            'worker_profile_id' => 'required|exists:event_worker_profiles,id',
            'assignment_role' => 'required|string|max:255',
            'work_start_date' => 'nullable|date',
            'work_end_date' => 'nullable|date|after_or_equal:work_start_date',
            'expected_days' => 'nullable|integer|min:1|max:365',
            'event_daily_rate' => 'nullable|integer|min:0',
            'hourly_rate' => 'nullable|integer|min:0',
            'fixed_amount' => 'nullable|integer|min:0',
            'compensation_method' => 'nullable|in:daily,hourly,fixed,manual,budget_share',
            'reporting_time' => 'nullable|date',
            'is_supervisor' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $profile = EventWorkerProfile::findOrFail($data['worker_profile_id']);
        $conflicts = $workforce->overlappingAssignments(
            $profile,
            $data['work_start_date'] ?? null,
            $data['work_end_date'] ?? null,
            $event->id
        );

        try {
            $assignment = $workforce->assignWorker($event, $profile, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['assign' => $e->getMessage()]);
        }

        $msg = 'Worker assigned.';
        if ($conflicts) {
            $names = collect($conflicts)->map(function ($c) {
                return $c['event']->name;
            })->implode(', ');
            $msg .= ' Warning: overlapping assignment on: ' . $names;
        }

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'workforce'])->with('message', $msg);
    }

    public function removeAssignment($eventId, $assignmentId, EventWorkforceService $workforce)
    {
        $this->can('events.manage_workforce');

        $event = Event::findOrFail($eventId);
        EventAssignment::where('event_id', $event->id)->where('id', $assignmentId)->delete();
        $workforce->refreshLabourBudgetAllocation($event);

        return back()->with('message', 'Assignment removed.');
    }

    public function saveBudget(Request $request, $eventId, EventWorkforceService $workforce)
    {
        $this->can('events.manage_budget');

        $event = Event::findOrFail($eventId);
        $data = $request->validate([
            'total_budget' => 'required|integer|min:0',
            'distribution_mode' => 'nullable|in:manual,equal,category_weight,hours,days',
            'notes' => 'nullable|string',
            'budget_override_reason' => 'nullable|string|max:500',
        ]);

        $allocated = (int) $event->assignments()->sum('expected_total');
        if ($allocated > (int) $data['total_budget'] && ! $request->filled('budget_override_reason')) {
            return back()->withErrors(['budget' => 'Allocated amount exceeds budget. Provide override reason to continue.']);
        }

        if ($request->filled('budget_override_reason')) {
            $data['notes'] = trim(($data['notes'] ?? '') . "\n[Budget override] " . $request->budget_override_reason);
        }

        $workforce->saveLabourBudget($event, $data);

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'budget'])
            ->with('message', 'Labour budget saved.');
    }

    public function search(Request $request, EventWorkforceService $workforce)
    {
        $this->can('events.manage_workforce');
        $term = $request->get('q', '');
        if (strlen($term) < 2) {
            return response()->json(['profiles' => [], 'customers' => []]);
        }

        $result = $workforce->searchPeople($term);
        $profiles = collect($result['profiles'])->map(function (EventWorkerProfile $p) {
            return [
                'id' => $p->id,
                'name' => $p->displayName(),
                'category' => optional($p->category)->name,
                'rate' => $p->standard_daily_rate,
                'type' => 'profile',
            ];
        });
        $customers = collect($result['customers'])->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone_number,
                'type' => 'customer',
            ];
        });

        return response()->json(['profiles' => $profiles, 'customers' => $customers]);
    }
}
