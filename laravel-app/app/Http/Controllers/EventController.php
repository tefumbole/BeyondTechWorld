<?php

namespace App\Http\Controllers;

use App\Booking;
use App\Customer;
use App\CustomerGroup;
use App\Event;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use App\Services\CampayPayoutService;
use App\Services\EventPublicationService;
use App\Services\EventService;
use App\Services\EventWorkforceService;
use App\Services\MobileMoneyHolderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
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
        $workerProfiles = EventWorkerProfile::active()->with('customer')->orderBy('created_at', 'desc')->get();
        $linkedCustomers = $workerProfiles->pluck('customer_id')->filter()->all();
        $people = [];
        foreach ($workerProfiles as $profile) {
            $people[] = [
                'kind' => 'profile',
                'ref' => (string) $profile->id,
                'name' => $profile->displayName(),
                'phone' => $profile->telephone ?: optional($profile->customer)->phone_number,
                'rate' => (int) $profile->standard_daily_rate,
            ];
        }
        foreach ($customers as $customer) {
            if (in_array($customer->id, $linkedCustomers)) {
                continue;
            }
            $people[] = [
                'kind' => 'customer',
                'ref' => (string) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone_number,
                'rate' => 0,
            ];
        }
        $event = new Event([
            'internal_status' => 'draft',
            'timezone' => 'Africa/Douala',
            'rental_link_mode' => 'none',
            'labour_mode' => 'individual',
            'event_type' => 'other',
        ]);

        return view('events.create', compact('event', 'customers', 'people'));
    }

    public function resolveLabourName(Request $request, CampayPayoutService $campay, MobileMoneyHolderService $holders)
    {
        $this->can('events.create');

        $phone = $campay->momoNumber($request->input('phone'));
        if (! $phone) {
            return response()->json(['ok' => false, 'message' => 'Enter an MTN or Orange Cameroon number.'], 422);
        }
        $hit = $holders->lookup($phone, false, false);
        $source = isset($hit['source']) ? $hit['source'] : '';
        if (empty($hit['name']) || ! in_array($source, ['campay', 'pawapay'], true)) {
            return response()->json(['ok' => false, 'message' => 'Campay has no registered name for this number.'], 422);
        }

        return response()->json([
            'ok' => true,
            'name' => $hit['name'],
            'phone' => $phone,
        ]);
    }

    public function store(Request $request, EventService $eventService, EventWorkforceService $workforce)
    {
        $this->can('events.create');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'required|integer|exists:customers,id',
            'event_start_at' => 'required|date',
            'event_end_at' => 'required|date|after_or_equal:event_start_at',
            'flyer' => 'nullable|image|max:8192',
            'publish_on_website' => 'nullable|boolean',
            'crew' => 'nullable|array',
            'crew.*.kind' => 'required|in:profile,customer,phone',
            'crew.*.ref' => 'nullable|string|max:80',
            'crew.*.phone' => 'nullable|string|max:32',
            'crew.*.name' => 'nullable|string|max:255',
            'crew.*.days' => 'required|integer|min:1|max:365',
            'crew.*.rate' => 'required|integer|min:0',
            'crew.*.food' => 'nullable|integer|min:0',
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $prepared = $this->prepareCrew($request->input('crew', []));
        $start = $data['event_start_at'];
        $end = $data['event_end_at'];
        $days = max(1, (int) ((strtotime($end) - strtotime($start)) / 86400) + 1);
        $publish = $request->boolean('publish_on_website');

        $event = DB::transaction(function () use ($request, $eventService, $workforce, $data, $customer, $prepared, $start, $end, $days, $publish) {
            $payload = [
                'name' => $data['name'],
                'event_type' => 'other',
                'customer_id' => $customer->id,
                'client_contact_person' => $customer->name,
                'client_telephone' => $customer->phone_number,
                'client_email' => $customer->email,
                'city' => $customer->city,
                'timezone' => 'Africa/Douala',
                'event_start_at' => $start.' 00:00:00',
                'event_end_at' => $end.' 23:59:00',
                'expected_workdays' => $days,
                'rental_link_mode' => 'none',
                'internal_status' => 'draft',
                'labour_mode' => 'individual',
                'slug' => Event::uniqueSlug($data['name']),
            ];
            if ($request->hasFile('flyer') && $request->file('flyer')->isValid()) {
                $payload['flyer_path'] = $eventService->storeFlyer($request->file('flyer'));
            }
            $event = $eventService->create($payload, $publish);
            foreach ($prepared as $member) {
                $profile = $this->profileForCrewMember($member, $workforce);
                $workforce->assignWorker($event, $profile, [
                    'assignment_role' => $member['name'] !== '' ? $member['name'] : 'Crew',
                    'event_daily_rate' => $member['rate'],
                    'expected_days' => $member['days'],
                    'work_start_date' => $start,
                    'work_end_date' => $end,
                    'compensation_method' => 'daily',
                    'food_per_day' => $member['food'],
                ]);
            }

            return $event;
        });

        $msg = 'Event created successfully.';
        if ($publish) {
            $msg .= ' It is on the website Events page.';
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

    protected function prepareCrew(array $crew)
    {
        $prepared = [];
        $seen = [];
        foreach ($crew as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = isset($row['kind']) ? (string) $row['kind'] : '';
            $days = max(1, (int) (isset($row['days']) ? $row['days'] : 1));
            $rate = max(0, (int) (isset($row['rate']) ? $row['rate'] : 0));
            $food = max(0, (int) (isset($row['food']) ? $row['food'] : 0));
            $phone = '';
            $name = isset($row['name']) ? (string) $row['name'] : '';
            if ($kind === 'phone') {
                $phone = app(CampayPayoutService::class)->momoNumber(isset($row['phone']) ? $row['phone'] : '');
                if (! $phone) {
                    throw ValidationException::withMessages([
                        'crew' => 'Enter an MTN or Orange Cameroon number for each new person.',
                    ]);
                }
                $hit = app(MobileMoneyHolderService::class)->lookup($phone, false, false);
                $source = isset($hit['source']) ? $hit['source'] : '';
                if (empty($hit['name']) || ! in_array($source, ['campay', 'pawapay'], true)) {
                    throw ValidationException::withMessages([
                        'crew' => 'Campay has no registered name for '.$phone.'.',
                    ]);
                }
                $typed = trim($name);
                $name = $typed !== '' ? $typed : $hit['name'];
                $memberName = $hit['name'];
                $ref = $phone;
                $key = 'phone:'.$phone;
            } elseif ($kind === 'profile' || $kind === 'customer') {
                $ref = isset($row['ref']) ? (string) $row['ref'] : '';
                if ($ref === '') {
                    throw ValidationException::withMessages(['crew' => 'Choose a person from the list.']);
                }
                $phone = isset($row['phone']) ? (string) $row['phone'] : '';
                $key = $kind.':'.$ref;
            } else {
                continue;
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'crew' => trim($name.' is already on this event.'),
                ]);
            }
            $seen[$key] = true;
            $prepared[] = [
                'kind' => $kind,
                'ref' => $ref,
                'name' => $name,
                'registered_name' => isset($memberName) ? $memberName : $name,
                'phone' => $phone,
                'days' => $days,
                'rate' => $rate,
                'food' => $food,
            ];
            unset($memberName);
        }

        return $prepared;
    }

    protected function profileForCrewMember(array $member, EventWorkforceService $workforce)
    {
        if ($member['kind'] === 'profile') {
            $profile = EventWorkerProfile::find($member['ref']);
            if (! $profile) {
                throw ValidationException::withMessages(['crew' => 'One of the selected people is no longer available.']);
            }

            return $profile;
        }
        if ($member['kind'] === 'customer') {
            return $workforce->profileFromCustomer($member['ref']);
        }

        $phone = $member['ref'];
        $tail = substr($phone, -9);
        $customer = Customer::where(function ($query) use ($phone, $tail) {
            $query->where('phone_number', $phone)->orWhere('phone_number', 'like', '%'.$tail);
        })->first();
        if (! $customer) {
            $groupId = CustomerGroup::query()->value('id');
            $customer = Customer::create([
                'customer_group_id' => $groupId ? $groupId : 1,
                'name' => ! empty($member['registered_name']) ? $member['registered_name'] : $member['name'],
                'phone_number' => $phone,
                'address' => 'N/A',
                'city' => 'N/A',
                'is_active' => true,
            ]);
        }
        $profile = $workforce->profileFromCustomer($customer->id);
        if (! $profile->telephone) {
            $profile->telephone = $phone;
            $profile->save();
        }

        return $profile;
    }
}
