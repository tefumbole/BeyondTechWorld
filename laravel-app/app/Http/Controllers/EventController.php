<?php

namespace App\Http\Controllers;

use App\Booking;
use App\Customer;
use App\Event;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use App\Services\EventPublicationService;
use App\Services\EventService;
use App\Services\EventWorkforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventController extends Controller
{
    protected $user;
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $role = Role::find($this->user->role_id);
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403, 'Permission denied: ' . $perm);
        }
    }

    public function index(Request $request)
    {
        $this->can('events.view');

        $query = Event::with(['customer', 'publication'])->orderBy('event_start_at', 'desc');

        if ($request->filled('status')) {
            $query->where('internal_status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('event_type', $request->type);
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', $q)
                    ->orWhere('reference_no', 'like', $q)
                    ->orWhere('venue', 'like', $q);
            });
        }

        $events = $query->paginate(20);

        return view('events.index', compact('events'));
    }

    public function create()
    {
        $this->can('events.create');

        $customers = Customer::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email', 'company_name', 'address', 'city']);
        $bookings = Booking::orderBy('id', 'desc')->limit(100)->get(['id', 'reference_no', 'customer_id', 'grand_total', 'paid_amount']);
        $workerProfiles = EventWorkerProfile::active()->with('customer', 'category')->orderBy('created_at', 'desc')->get();
        $event = new Event([
            'internal_status' => 'draft',
            'timezone' => 'Africa/Kigali',
            'rental_link_mode' => 'none',
            'labour_mode' => 'individual',
        ]);

        return view('events.create', compact('event', 'customers', 'bookings', 'workerProfiles'));
    }

    public function store(Request $request, EventService $eventService, EventWorkforceService $workforce)
    {
        $this->can('events.create');

        $data = $this->validatedEvent($request);

        if ($request->hasFile('flyer') && $request->file('flyer')->isValid()) {
            $data['flyer_path'] = $eventService->storeFlyer($request->file('flyer'));
        }

        $publish = $request->boolean('publish_on_website', true);
        $event = $eventService->create($data, $publish);

        if ($request->input('labour_mode') === 'individual') {
            $this->assignStaffFromRequest($request, $event, $workforce);
        } elseif ($request->input('labour_mode') === 'budget' && $request->filled('labour_budget_total')) {
            $workforce->saveLabourBudget($event, [
                'total_budget' => (int) $request->input('labour_budget_total'),
                'distribution_mode' => 'manual',
            ]);
        }

        $msg = 'Event created successfully.';
        if ($publish) {
            $msg .= ' It is published on the website Events page and home page.';
        }

        return redirect()->route('events.show', $event->id)->with('message', $msg);
    }

    public function show($id, Request $request, EventWorkforceService $workforce)
    {
        $this->can('events.view');

        $event = Event::with([
            'customer', 'booking.bookingProduct', 'publication', 'statusHistories.changer',
            'assignments.workerProfile.customer', 'assignments.workerProfile.category',
            'labourBudget', 'contracts.assignment.workerProfile',
            'reminders', 'payments.workerProfile',
        ])->findOrFail($id);

        $tab = $request->get('tab', 'overview');
        $categories = EventWorkerCategory::active()->orderBy('name')->get();
        $workerProfiles = EventWorkerProfile::active()->with('customer', 'category')->orderBy('created_at', 'desc')->limit(50)->get();
        $rentalWarning = $workforce->rentalScheduleWarning($event);
        $specializations = EventWorkforceService::SPECIALIZATIONS;
        $countdownTargets = EventPublicationService::COUNTDOWN_TARGETS;
        $publicStatuses = EventPublicationService::PUBLIC_STATUSES;
        $contractTemplates = \App\EventContractTemplate::where('is_active', true)->orderBy('name')->get();

        return view('events.show', compact(
            'event', 'tab', 'categories', 'workerProfiles', 'rentalWarning',
            'specializations', 'countdownTargets', 'publicStatuses', 'contractTemplates'
        ));
    }

    public function edit($id)
    {
        $this->can('events.update');

        $event = Event::with('publication')->findOrFail($id);
        $customers = Customer::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email', 'company_name', 'address', 'city']);
        $bookings = Booking::orderBy('id', 'desc')->limit(100)->get(['id', 'reference_no', 'customer_id', 'grand_total', 'paid_amount']);
        $workerProfiles = EventWorkerProfile::active()->with('customer', 'category')->orderBy('created_at', 'desc')->get();

        return view('events.edit', compact('event', 'customers', 'bookings', 'workerProfiles'));
    }

    public function update(Request $request, $id, EventService $eventService)
    {
        $this->can('events.update');

        $event = Event::findOrFail($id);
        $data = $this->validatedEvent($request, $event);

        if ($request->hasFile('flyer') && $request->file('flyer')->isValid()) {
            $data['flyer_path'] = $eventService->storeFlyer($request->file('flyer'));
        }

        $eventService->update($event, $data, $request->input('status_note'));

        return redirect()->route('events.show', $event->id)
            ->with('message', 'Event updated successfully.');
    }

    public function destroy($id)
    {
        $this->can('events.delete');

        $event = Event::findOrFail($id);
        if (! in_array($event->internal_status, ['draft', 'cancelled'], true)) {
            return back()->withErrors(['delete' => 'Only draft or cancelled events can be deleted. Postpone or cancel first.']);
        }

        $event->publication()->delete();
        $event->statusHistories()->delete();
        $event->delete();

        return redirect()->route('events.index')->with('message', 'Event deleted.');
    }

    public function calendar()
    {
        $this->can('events.view');

        $events = Event::whereNotNull('event_start_at')
            ->orderBy('event_start_at')
            ->get(['id', 'name', 'event_start_at', 'event_end_at', 'internal_status', 'venue']);

        return view('events.calendar', compact('events'));
    }

    private function validatedEvent(Request $request, Event $existing = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:191',
            'event_type' => 'required|in:' . implode(',', array_keys(Event::TYPES)),
            'customer_id' => 'nullable|integer|exists:customers,id',
            'client_contact_person' => 'nullable|string|max:255',
            'client_telephone' => 'nullable|string|max:64',
            'client_email' => 'nullable|email|max:255',
            'venue' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'city' => 'nullable|string|max:128',
            'timezone' => 'nullable|string|max:64',
            'internal_description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'packing_at' => 'nullable|date',
            'loading_at' => 'nullable|date',
            'departure_at' => 'nullable|date',
            'setup_start_at' => 'nullable|date',
            'setup_end_at' => 'nullable|date',
            'rehearsal_at' => 'nullable|date',
            'event_start_at' => ($existing ? 'nullable' : 'required') . '|date',
            'event_end_at' => ($existing ? 'nullable' : 'required') . '|date|after_or_equal:event_start_at',
            'dismantling_start_at' => 'nullable|date',
            'dismantling_end_at' => 'nullable|date',
            'return_at' => 'nullable|date',
            'expected_workdays' => 'nullable|integer|min:0|max:365',
            'timesheet_deadline_at' => 'nullable|date',
            'booking_id' => 'nullable|integer|exists:bookings,id',
            'rental_link_mode' => 'nullable|in:none,link,create',
            'internal_status' => 'nullable|in:' . implode(',', array_keys(Event::STATUSES)),
            'labour_mode' => 'nullable|in:individual,budget',
            'status_note' => 'nullable|string|max:500',
            'publish_on_website' => 'nullable|boolean',
            'labour_budget_total' => 'nullable|integer|min:0',
            'staff' => 'nullable|array',
            'staff.*' => 'exists:event_worker_profiles,id',
            'staff_role' => 'nullable|array',
            'staff_rate' => 'nullable|array',
            'staff_days' => 'nullable|array',
        ];

        $validated = $request->validate($rules);

        unset(
            $validated['publish_on_website'],
            $validated['labour_budget_total'],
            $validated['staff'],
            $validated['staff_role'],
            $validated['staff_rate'],
            $validated['staff_days']
        );

        if (empty($validated['slug']) && ! empty($validated['name'])) {
            $validated['slug'] = Event::uniqueSlug($validated['name'], $existing ? $existing->id : null);
        }

        if (($validated['rental_link_mode'] ?? 'none') === 'none') {
            $validated['booking_id'] = null;
        }

        return $validated;
    }

    protected function assignStaffFromRequest(Request $request, Event $event, EventWorkforceService $workforce)
    {
        $staffIds = array_unique((array) $request->input('staff', []));
        $roles = (array) $request->input('staff_role', []);
        $rates = (array) $request->input('staff_rate', []);
        $days = (array) $request->input('staff_days', []);
        $workStart = $request->input('event_start_at');
        $workEnd = $request->input('event_end_at');

        foreach ($staffIds as $profileId) {
            $profile = EventWorkerProfile::find($profileId);
            if (! $profile) {
                continue;
            }
            try {
                $workforce->assignWorker($event, $profile, [
                    'assignment_role' => $roles[$profileId] ?? (optional($profile->category)->name ?: 'Crew'),
                    'event_daily_rate' => (int) ($rates[$profileId] ?? $profile->standard_daily_rate),
                    'expected_days' => max(1, (int) ($days[$profileId] ?? $request->input('expected_workdays', 1))),
                    'work_start_date' => $workStart ? substr($workStart, 0, 10) : null,
                    'work_end_date' => $workEnd ? substr($workEnd, 0, 10) : null,
                    'compensation_method' => 'daily',
                ]);
            } catch (\InvalidArgumentException $e) {
                // skip duplicate / invalid
            }
        }
    }
}
