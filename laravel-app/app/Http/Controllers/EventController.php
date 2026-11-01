<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\Booking;
use App\Customer;
use App\CustomerGroup;
use App\Employee;
use App\Event;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use App\Services\CampayPayoutService;
use App\Services\EventCalendarBuilder;
use App\Services\EventPublicationService;
use App\Services\EventService;
use App\Services\EventWorkforceService;
use App\Services\MobileMoneyHolderService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $query = Event::with(['customer', 'publication', 'assignments.workerProfile.customer', 'assignments.workerProfile.user'])
            ->orderBy('created_at', 'desc');

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
        $roles = EventWorkerCategory::active()->orderBy('name')->get();
        $people = $this->engineerDirectory();
        $customerOptions = [];
        $selectedCustomer = '';
        $selectedId = (string) old('customer_id');
        foreach ($customers as $customer) {
            $customerOptions[] = [
                'id' => (string) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone_number,
            ];
            if ($selectedId !== '' && $selectedId === (string) $customer->id) {
                $selectedCustomer = $customer->name.($customer->phone_number ? ' — '.$customer->phone_number : '');
            }
        }
        $roleOptions = [];
        foreach ($roles as $role) {
            $roleOptions[] = [
                'id' => (int) $role->id,
                'name' => $role->name,
                'rate' => (int) $role->default_daily_rate,
            ];
        }
        $event = new Event([
            'internal_status' => 'draft',
            'timezone' => 'Africa/Douala',
            'rental_link_mode' => 'none',
            'labour_mode' => 'individual',
            'event_type' => 'other',
        ]);

        return view('events.create', compact('event', 'customers', 'people', 'roles', 'customerOptions', 'roleOptions', 'selectedCustomer'));
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

    public function rememberLabourRole(Request $request, EventWorkforceService $workforce)
    {
        $this->can('events.create');

        $data = $request->validate([
            'kind' => 'required|in:profile,customer,user,employee,portal',
            'ref' => 'required|string|max:80',
            'category_id' => 'required|integer',
        ]);
        $category = EventWorkerCategory::active()->find($data['category_id']);
        if (! $category) {
            return response()->json(['ok' => false, 'message' => 'Choose a role from Event Settings.'], 422);
        }
        $profile = $this->profileForDirectory($data['kind'], $data['ref'], $category->id, $workforce);
        if (! $profile) {
            return response()->json(['ok' => false, 'message' => 'That person is no longer available.'], 422);
        }
        $workforce->rememberRole($profile, $category);

        return response()->json([
            'ok' => true,
            'category_id' => (int) $category->id,
            'role' => $category->name,
            'rate' => (int) $category->default_daily_rate,
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
            'internal_notes' => 'nullable|string|max:5000',
            'publish_on_website' => 'nullable',
            'crew' => 'nullable|array',
            'crew.*.kind' => 'nullable|string|max:32',
            'crew.*.ref' => 'nullable|string|max:80',
            'crew.*.phone' => 'nullable|string|max:32',
            'crew.*.name' => 'nullable|string|max:255',
            'crew.*.days' => 'nullable|integer|min:1|max:365',
            'crew.*.rate' => 'nullable|integer|min:0',
            'crew.*.food' => 'nullable|integer|min:0',
            'crew.*.category_id' => 'nullable',
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $start = $data['event_start_at'];
        $end = $data['event_end_at'];
        $days = max(1, (int) ((strtotime($end) - strtotime($start)) / 86400) + 1);
        $publish = $request->boolean('publish_on_website');
        $warnings = [];

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
            'internal_notes' => isset($data['internal_notes']) ? trim((string) $data['internal_notes']) : null,
            'expected_workdays' => $days,
            'rental_link_mode' => 'none',
            'internal_status' => 'draft',
            'labour_mode' => 'individual',
            'slug' => Event::uniqueSlug($data['name']),
        ];
        if ($request->hasFile('flyer')) {
            $flyer = $request->file('flyer');
            if ($flyer->isValid() && strpos((string) $flyer->getMimeType(), 'image/') === 0 && $flyer->getSize() <= 8192 * 1024) {
                $payload['flyer_path'] = $eventService->storeFlyer($flyer);
            } else {
                $warnings[] = 'The flyer was skipped. The event was still created.';
            }
        }

        $event = $eventService->create($payload, $publish);
        $prepared = $this->prepareCrew($request->input('crew', []), $warnings);
        foreach ($prepared as $member) {
            try {
                $category = ! empty($member['category_id'])
                    ? EventWorkerCategory::active()->find($member['category_id'])
                    : null;
                $profile = $this->profileForCrewMember($member, $workforce);
                $rate = $member['rate'];
                $roleName = $member['name'] !== '' ? $member['name'] : 'Crew';
                if ($category) {
                    $workforce->rememberRole($profile, $category);
                    $roleName = $category->name;
                    $rate = (int) $category->default_daily_rate;
                } else {
                    $warnings[] = trim($member['name'].' was added without a settings role.');
                }
                $workforce->assignWorker($event, $profile, [
                    'assignment_role' => $roleName,
                    'event_daily_rate' => $rate,
                    'expected_days' => $member['days'],
                    'work_start_date' => $start,
                    'work_end_date' => $end,
                    'compensation_method' => 'daily',
                    'food_per_day' => $member['food'],
                ]);
            } catch (\Exception $e) {
                $warnings[] = trim($member['name'].' was not added. '.$e->getMessage());
            }
        }

        $msg = $event->name.' is on the Events dashboard.';
        if ($publish) {
            $msg .= ' It is also on the website Events page.';
        }
        if ($warnings) {
            $msg .= ' '.implode(' ', array_unique($warnings));
        }

        return redirect()->route('events.dashboard')->with('message', $msg);
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

    public function calendar(Request $request, EventCalendarBuilder $builder)
    {
        $this->can('events.view');

        $monthStart = $builder->monthStart($request->get('month'));
        $gridStart = $monthStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::MONDAY)->endOfDay();
        $events = Event::whereNotNull('event_start_at')
            ->where('event_start_at', '<=', $gridEnd)
            ->where(function ($query) use ($gridStart) {
                $query->where('event_end_at', '>=', $gridStart)
                    ->orWhereNull('event_end_at');
            })
            ->orderBy('event_start_at')
            ->get(['id', 'name', 'event_start_at', 'event_end_at', 'internal_status', 'venue']);
        $weeks = $builder->weeks($events, $monthStart);
        $monthLabel = $monthStart->format('F Y');
        $monthKey = $monthStart->format('Y-m');
        $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
        $today = \Carbon\Carbon::now('Africa/Douala')->toDateString();

        return view('events.calendar', compact('weeks', 'monthLabel', 'monthKey', 'prevMonth', 'nextMonth', 'today'));
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

    protected function prepareCrew(array $crew, array &$warnings = [])
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
            $categoryId = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $phone = '';
            $name = isset($row['name']) ? (string) $row['name'] : '';
            if ($kind === 'phone') {
                $phone = app(CampayPayoutService::class)->momoNumber(isset($row['phone']) ? $row['phone'] : '');
                if (! $phone) {
                    $warnings[] = 'A new person was skipped because the phone number is not MTN or Orange.';
                    continue;
                }
                $hit = app(MobileMoneyHolderService::class)->lookup($phone, false, false);
                $source = isset($hit['source']) ? $hit['source'] : '';
                $typed = trim($name);
                if (empty($hit['name']) || ! in_array($source, ['campay', 'pawapay'], true)) {
                    if ($typed === '') {
                        $warnings[] = 'Campay has no registered name for '.$phone.'.';
                        continue;
                    }
                    $name = $typed;
                } else {
                    $name = $typed !== '' ? $typed : $hit['name'];
                    $memberName = $hit['name'];
                }
                $ref = $phone;
                $key = 'phone:'.$phone;
            } elseif (in_array($kind, ['profile', 'customer', 'user', 'employee', 'portal'], true)) {
                $ref = isset($row['ref']) ? (string) $row['ref'] : '';
                if ($ref === '') {
                    $warnings[] = trim($name.' was skipped because the account could not be matched.');
                    continue;
                }
                $phone = isset($row['phone']) ? (string) $row['phone'] : '';
                $key = $kind.':'.$ref;
            } else {
                continue;
            }
            if (isset($seen[$key])) {
                $warnings[] = trim($name.' is already on this event.');
                continue;
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
                'category_id' => $categoryId,
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
            return $workforce->profileFromCustomer($member['ref'], ! empty($member['category_id']) ? $member['category_id'] : null);
        }
        $directory = $this->profileForDirectory($member['kind'], $member['ref'], ! empty($member['category_id']) ? $member['category_id'] : null, $workforce);
        if ($directory) {
            return $directory;
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
        $profile = $workforce->profileFromCustomer($customer->id, ! empty($member['category_id']) ? $member['category_id'] : null);
        if (! $profile->telephone) {
            $profile->telephone = $phone;
            $profile->save();
        }

        return $profile;
    }

    protected function personFromProfile(EventWorkerProfile $profile)
    {
        $category = ($profile->category && $profile->category->is_active) ? $profile->category : null;

        return [
            'kind' => 'profile',
            'ref' => (string) $profile->id,
            'name' => $profile->displayName(),
            'phone' => $profile->telephone ?: optional($profile->customer)->phone_number ?: optional($profile->user)->phone,
            'email' => $profile->email ?: optional($profile->customer)->email ?: optional($profile->user)->email,
            'rate' => $category ? (int) $category->default_daily_rate : (int) $profile->standard_daily_rate,
            'category_id' => $category ? (int) $category->id : null,
            'role' => $category ? $category->name : '',
            'source' => '',
        ];
    }

    protected function profileForDirectory($kind, $ref, $categoryId, EventWorkforceService $workforce)
    {
        if ($kind === 'user') {
            return $workforce->profileFromUser($ref, $categoryId);
        }
        if ($kind === 'employee') {
            return $workforce->profileFromEmployee($ref, $categoryId);
        }
        if ($kind === 'portal') {
            return $workforce->profileFromPortal($ref, $categoryId);
        }

        return null;
    }

    protected function engineerDirectory()
    {
        $people = [];
        $seen = [];
        $internIds = [];
        if (Schema::hasTable('internship_enrolments')) {
            foreach (DB::table('internship_enrolments')->pluck('student_user_id') as $id) {
                $internIds[(int) $id] = true;
            }
        }
        $employeeUserIds = [];
        $employees = collect();
        if (Schema::hasTable('employees')) {
            $employees = Employee::orderBy('name')->get(['id', 'name', 'email', 'phone_number', 'user_id', 'city', 'is_active']);
            foreach ($employees as $employee) {
                if ($employee->user_id) {
                    $employeeUserIds[(int) $employee->user_id] = true;
                }
            }
        }
        $roleNames = DB::table('roles')->pluck('name', 'id')->all();

        $profiles = EventWorkerProfile::with('customer', 'user', 'category')->orderBy('created_at', 'desc')->get();
        foreach ($profiles as $profile) {
            $person = $this->personFromProfile($profile);
            $person['source'] = $this->accountLabel(
                $profile->user_id,
                $profile->user ? $profile->user->role_id : null,
                $internIds,
                $employeeUserIds,
                $roleNames,
                $profile->customer_id ? 'Customer' : 'Account'
            );
            $people[] = $person;
            $this->rememberPerson($seen, $person['phone'], $person['email'], $profile->customer_id, $profile->user_id, $profile->be_user_id);
        }

        $users = User::query()
            ->where(function ($query) {
                $query->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role_id']);
        foreach ($users as $user) {
            if ($this->personSeen($seen, $user->phone, $user->email, null, $user->id, null)) {
                continue;
            }
            $people[] = [
                'kind' => 'user',
                'ref' => (string) $user->id,
                'name' => $user->name ?: 'User',
                'phone' => $user->phone,
                'email' => $user->email,
                'rate' => 0,
                'category_id' => null,
                'role' => '',
                'source' => $this->accountLabel($user->id, $user->role_id, $internIds, $employeeUserIds, $roleNames, 'User'),
            ];
            $this->rememberPerson($seen, $user->phone, $user->email, null, $user->id, null);
        }

        foreach ($employees as $employee) {
            if ($this->personSeen($seen, $employee->phone_number, $employee->email, null, $employee->user_id, null)) {
                continue;
            }
            $people[] = [
                'kind' => 'employee',
                'ref' => (string) $employee->id,
                'name' => $employee->name ?: 'Staff',
                'phone' => $employee->phone_number,
                'email' => $employee->email,
                'rate' => 0,
                'category_id' => null,
                'role' => '',
                'source' => 'Staff',
            ];
            $this->rememberPerson($seen, $employee->phone_number, $employee->email, null, $employee->user_id, null);
        }

        foreach (Customer::orderBy('name')->get(['id', 'name', 'phone_number', 'email']) as $customer) {
            if ($this->personSeen($seen, $customer->phone_number, $customer->email, $customer->id, null, null)) {
                continue;
            }
            $people[] = [
                'kind' => 'customer',
                'ref' => (string) $customer->id,
                'name' => $customer->name ?: 'Customer',
                'phone' => $customer->phone_number,
                'email' => $customer->email,
                'rate' => 0,
                'category_id' => null,
                'role' => '',
                'source' => 'Customer',
            ];
            $this->rememberPerson($seen, $customer->phone_number, $customer->email, $customer->id, null, null);
        }

        if (Schema::hasTable('be_users')) {
            foreach (BeyondUser::orderBy('name')->get(['id', 'name', 'email', 'phone', 'role']) as $portal) {
                if ($this->personSeen($seen, $portal->phone, $portal->email, null, null, $portal->id)) {
                    continue;
                }
                $label = $portal->role ? ucfirst(str_replace('_', ' ', (string) $portal->role)) : 'Portal';
                $people[] = [
                    'kind' => 'portal',
                    'ref' => (string) $portal->id,
                    'name' => $portal->name ?: 'Portal user',
                    'phone' => $portal->phone,
                    'email' => $portal->email,
                    'rate' => 0,
                    'category_id' => null,
                    'role' => '',
                    'source' => $label,
                ];
                $this->rememberPerson($seen, $portal->phone, $portal->email, null, null, $portal->id);
            }
        }

        return $people;
    }

    protected function accountLabel($userId, $roleId, array $internIds, array $employeeUserIds, array $roleNames, $fallback)
    {
        if ($userId && isset($internIds[(int) $userId])) {
            return 'Intern';
        }
        if ($userId && isset($employeeUserIds[(int) $userId])) {
            return 'Staff';
        }
        if ($roleId && isset($roleNames[$roleId])) {
            $name = (string) $roleNames[$roleId];
            if (stripos($name, 'intern') !== false) {
                return 'Intern';
            }

            return $name;
        }

        return $fallback;
    }

    protected function personSeen(array $seen, $phone, $email, $customerId, $userId, $portalId)
    {
        $phoneKey = $this->phoneKey($phone);
        $emailKey = strtolower(trim((string) $email));
        if ($phoneKey !== '' && isset($seen['p:'.$phoneKey])) {
            return true;
        }
        if ($emailKey !== '' && isset($seen['e:'.$emailKey])) {
            return true;
        }
        if ($customerId && isset($seen['c:'.$customerId])) {
            return true;
        }
        if ($userId && isset($seen['u:'.$userId])) {
            return true;
        }
        if ($portalId && isset($seen['b:'.$portalId])) {
            return true;
        }

        return false;
    }

    protected function rememberPerson(array &$seen, $phone, $email, $customerId, $userId, $portalId)
    {
        $phoneKey = $this->phoneKey($phone);
        $emailKey = strtolower(trim((string) $email));
        if ($phoneKey !== '') {
            $seen['p:'.$phoneKey] = true;
        }
        if ($emailKey !== '') {
            $seen['e:'.$emailKey] = true;
        }
        if ($customerId) {
            $seen['c:'.$customerId] = true;
        }
        if ($userId) {
            $seen['u:'.$userId] = true;
        }
        if ($portalId) {
            $seen['b:'.$portalId] = true;
        }
    }

    protected function phoneKey($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 6) {
            return '';
        }

        return substr($digits, -9);
    }
}
