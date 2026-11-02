<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\Booking;
use App\Customer;
use App\CustomerGroup;
use App\Employee;
use App\Event;
use App\EventAssignment;
use App\EventTimesheetEntry;
use App\EventWorkerCategory;
use App\EventWorkerProfile;
use App\Services\CampayPayoutService;
use App\Services\EventCalendarBuilder;
use App\Services\EventCrewInviteService;
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

    protected function canManageEvents()
    {
        if (in_array('events.create', $this->all_permission, true) || in_array('events.update', $this->all_permission, true)) {
            return;
        }
        abort(403, 'Permission denied: events.update');
    }

    protected function dateList($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array();
        }
        if (! is_array($value)) {
            return array();
        }
        $out = array();
        foreach ($value as $date) {
            $date = substr(trim((string) $date), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $out[$date] = $date;
            }
        }
        $out = array_values($out);
        sort($out);

        return array_slice($out, 0, 62);
    }

    protected function datesBetween($start, $end)
    {
        $start = substr((string) $start, 0, 10);
        $end = substr((string) $end, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            return array();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) {
            $end = $start;
        }
        $out = array();
        $cursor = strtotime($start);
        $last = strtotime($end);
        $guard = 0;
        while ($cursor && $cursor <= $last && $guard < 62) {
            $out[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
            $guard++;
        }

        return $out;
    }

    protected function dateTimeValue($date, $time)
    {
        $date = substr(trim((string) $date), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date.' '.$time;
    }

    protected function setupDatesFor(Event $event)
    {
        $dates = $this->dateList($event->setup_dates);
        if (! count($dates) && $event->setup_start_at) {
            $dates = $this->datesBetween(
                $event->setup_start_at->toDateString(),
                $event->setup_end_at ? $event->setup_end_at->toDateString() : $event->setup_start_at->toDateString()
            );
        }

        return $dates;
    }

    protected function crewFromEvent(Event $event)
    {
        $rows = array();
        foreach ($event->assignments as $assignment) {
            $profile = $assignment->workerProfile;
            if (! $profile) {
                continue;
            }
            $dates = $this->dateList($assignment->work_dates);
            if (! count($dates) && $assignment->work_start_date) {
                $dates = $this->datesBetween(
                    $assignment->work_start_date->toDateString(),
                    $assignment->work_end_date ? $assignment->work_end_date->toDateString() : $assignment->work_start_date->toDateString()
                );
            }
            $category = ($profile->category && $profile->category->is_active) ? $profile->category : null;
            $rows[] = array(
                'assignment_id' => (string) $assignment->id,
                'kind' => 'profile',
                'ref' => (string) $profile->id,
                'name' => $profile->displayName(),
                'phone' => $profile->telephone ?: optional($profile->customer)->phone_number ?: optional($profile->user)->phone,
                'rate' => (int) $assignment->event_daily_rate,
                'food' => (int) $assignment->food_per_day,
                'days' => count($dates) ? count($dates) : (int) $assignment->expected_days,
                'category_id' => $category ? (int) $category->id : '',
                'work_dates' => $dates,
            );
        }

        return $rows;
    }

    public function index(Request $request)
    {
        $this->can('events.view');

        $view = $request->get('view', 'upcoming');
        if (! in_array($view, ['upcoming', 'completed', 'published', 'progress'], true)) {
            $view = 'upcoming';
        }
        $headings = [
            'upcoming' => 'Upcoming events',
            'completed' => 'Completed events',
            'published' => 'Published events',
            'progress' => 'In progress',
        ];
        $heading = $headings[$view];
        $query = Event::with(['customer', 'publication', 'assignments.workerProfile.customer', 'assignments.workerProfile.user']);
        if ($view === 'completed') {
            $query->finished();
        } elseif ($view === 'published') {
            $query->onWebsite();
        } elseif ($view === 'progress') {
            $query->inProgressWindow();
        } else {
            $query->upcoming();
        }
        $query->orderBy('created_at', 'desc');

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

        $events = $query->paginate(20)->appends($request->only(['view', 'q', 'status', 'type']));
        $canDelete = in_array('events.delete', $this->all_permission, true)
            || in_array('events.update', $this->all_permission, true);

        return view('events.index', compact('events', 'heading', 'view', 'canDelete'));
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
        $this->canManageEvents();

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
        $this->canManageEvents();

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

        return $this->saveModern($request, null, $eventService, $workforce);
    }

    public function saveModern(Request $request, Event $existing = null, EventService $eventService = null, EventWorkforceService $workforce = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'customer_query' => 'nullable|string|max:255',
            'event_start_at' => 'required|date',
            'event_end_at' => 'required|date|after_or_equal:event_start_at',
            'internal_notes' => 'nullable|string|max:5000',
            'publish_on_website' => 'nullable',
            'setup_dates' => 'nullable|string|max:4000',
            'crew' => 'nullable|array',
            'crew.*.assignment_id' => 'nullable|string|max:64',
            'crew.*.kind' => 'nullable|string|max:32',
            'crew.*.ref' => 'nullable|string|max:80',
            'crew.*.phone' => 'nullable|string|max:32',
            'crew.*.name' => 'nullable|string|max:255',
            'crew.*.days' => 'nullable|integer|min:1|max:365',
            'crew.*.rate' => 'nullable|integer|min:0',
            'crew.*.food' => 'nullable|integer|min:0',
            'crew.*.category_id' => 'nullable',
            'crew.*.work_dates' => 'nullable|string|max:4000',
        ]);

        $customer = $this->optionalCustomer($request, $data);
        $start = $data['event_start_at'];
        $end = $data['event_end_at'];
        $days = max(1, (int) ((strtotime($end) - strtotime($start)) / 86400) + 1);
        $publish = $request->boolean('publish_on_website');
        $warnings = [];

        $setup = $this->dateList(isset($data['setup_dates']) ? $data['setup_dates'] : '');
        $payload = [
            'name' => $data['name'],
            'customer_id' => $customer ? $customer->id : null,
            'client_contact_person' => $customer ? $customer->name : null,
            'client_telephone' => $customer ? $customer->phone_number : null,
            'client_email' => $customer ? $customer->email : null,
            'city' => $customer ? $customer->city : null,
            'event_start_at' => $this->dateTimeValue($start, '00:00:00'),
            'event_end_at' => $this->dateTimeValue($end, '23:59:00'),
            'internal_notes' => isset($data['internal_notes']) ? trim((string) $data['internal_notes']) : null,
            'expected_workdays' => $days,
        ];
        if (! $existing || $request->input('crew_form') === '1') {
            $payload['setup_dates'] = count($setup) ? json_encode($setup) : null;
            $payload['setup_start_at'] = count($setup) ? $setup[0].' 00:00:00' : null;
            $payload['setup_end_at'] = count($setup) ? $setup[count($setup) - 1].' 23:59:00' : null;
        }
        if ($request->hasFile('flyer')) {
            $flyer = $request->file('flyer');
            if ($flyer->isValid() && strpos((string) $flyer->getMimeType(), 'image/') === 0 && $flyer->getSize() <= 8192 * 1024) {
                $payload['flyer_path'] = $eventService->storeFlyer($flyer);
            } else {
                $warnings[] = 'The flyer was skipped.';
            }
        }

        if ($existing) {
            if ($existing->name !== $data['name']) {
                $payload['slug'] = Event::uniqueSlug($data['name'], $existing->id);
            }
            $existing->fill($payload);
            $existing->updated_by = \Illuminate\Support\Facades\Auth::id();
            $existing->save();
            $event = $existing;
            if ($event->publication) {
                $event->publication->publish_on_website = $publish ? 1 : 0;
                $event->publication->publication_status = $publish ? 'published' : 'draft';
                $event->publication->public_title = $event->name;
                if ($publish && ! $event->publication->visibility_at) {
                    $event->publication->visibility_at = now();
                }
                $event->publication->save();
            }
        } else {
            $payload['event_type'] = 'other';
            $payload['timezone'] = 'Africa/Douala';
            $payload['rental_link_mode'] = 'none';
            $payload['internal_status'] = 'draft';
            $payload['labour_mode'] = 'individual';
            $payload['slug'] = Event::uniqueSlug($data['name']);
            $event = $eventService->create($payload, $publish);
        }

        $prepared = $this->prepareCrew($request->input('crew', []), $warnings);
        $kept = [];
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
                    if ($rate < 1) {
                        $rate = (int) $category->default_daily_rate;
                    }
                } elseif ($member['name'] !== '') {
                    $warnings[] = trim($member['name'].' was saved without a settings role.');
                }
                $dates = count($member['work_dates']) ? $member['work_dates'] : $this->datesBetween($start, $end);
                $assignment = $workforce->writeAssignment($event, $profile, [
                    'assignment_role' => $roleName,
                    'event_daily_rate' => $rate,
                    'expected_days' => count($dates) ? count($dates) : $member['days'],
                    'work_dates' => $dates,
                    'work_start_date' => $start,
                    'work_end_date' => $end,
                    'food_per_day' => $member['food'],
                ], $member['assignment_id']);
                $kept[] = $assignment->id;
            } catch (\Exception $e) {
                $warnings[] = trim($member['name'].' was not saved. '.$e->getMessage());
            }
        }
        if ($existing && $request->input('crew_form') === '1') {
            $extras = EventAssignment::where('event_id', $event->id);
            if (count($kept)) {
                $extras->whereNotIn('id', $kept);
            }
            foreach ($extras->get() as $extra) {
                $extra->load('workerProfile');
                $label = $extra->workerProfile ? $extra->workerProfile->displayName() : 'A person';
                if (! $workforce->dropAssignment($extra)) {
                    $warnings[] = $label.' stayed on the event because a payment is already recorded.';
                }
            }
            $workforce->refreshLabourBudgetAllocation($event);
        }

        $invite = app(EventCrewInviteService::class)->inviteAssigned($event);
        $warnings = array_merge($warnings, $invite['warnings']);

        $msg = $existing ? $event->name.' saved.' : $event->name.' is on the Events dashboard.';
        if ($invite['sent'] > 0) {
            $msg .= ' '.$invite['sent'].' people received the selection message.';
        }
        if ($publish) {
            $msg .= ' It is also on the website Events page.';
        }
        if ($warnings) {
            $msg .= ' '.implode(' ', array_unique($warnings));
        }

        return redirect()->route($existing ? 'events.show' : 'events.dashboard', $existing ? $event->id : [])
            ->with('message', $msg);
    }

    protected function optionalCustomer(Request $request, array $data)
    {
        if (! empty($data['customer_id'])) {
            return Customer::find($data['customer_id']);
        }
        $typed = trim((string) $request->input('customer_query'));
        if ($typed === '') {
            return null;
        }
        $name = trim(preg_replace('/\s+—\s+.*$/u', '', $typed));
        if ($name !== '') {
            $match = Customer::where('is_active', true)->where('name', $name)->first();
            if ($match) {
                return $match;
            }
        }
        $digits = preg_replace('/\D+/', '', $typed);
        if (strlen($digits) >= 9) {
            return Customer::where('is_active', true)
                ->where('phone_number', 'like', '%'.substr($digits, -9))
                ->first();
        }

        return null;
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

        $event = Event::with([
            'publication',
            'customer',
            'assignments.workerProfile.customer',
            'assignments.workerProfile.user',
            'assignments.workerProfile.category',
        ])->findOrFail($id);
        $customers = Customer::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'email', 'company_name', 'address', 'city']);
        $roles = EventWorkerCategory::active()->orderBy('name')->get();
        $people = $this->engineerDirectory();
        $customerOptions = [];
        $selectedCustomer = '';
        if ($event->customer) {
            $selectedCustomer = $event->customer->name.($event->customer->phone_number ? ' — '.$event->customer->phone_number : '');
        }
        foreach ($customers as $customer) {
            $customerOptions[] = [
                'id' => (string) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone_number,
            ];
        }
        $roleOptions = [];
        foreach ($roles as $role) {
            $roleOptions[] = [
                'id' => (int) $role->id,
                'name' => $role->name,
                'rate' => (int) $role->default_daily_rate,
            ];
        }
        $editing = true;
        $existingCrew = $this->crewFromEvent($event);
        $setupDates = $this->setupDatesFor($event);

        return view('events.create', compact(
            'event', 'customers', 'people', 'roles', 'customerOptions', 'roleOptions',
            'selectedCustomer', 'editing', 'existingCrew', 'setupDates'
        ));
    }

    public function update(Request $request, $id, EventService $eventService, EventWorkforceService $workforce)
    {
        $this->can('events.update');

        $event = Event::with('publication')->findOrFail($id);

        return $this->saveModern($request, $event, $eventService, $workforce);
    }

    public function destroy($id)
    {
        if (! in_array('events.delete', $this->all_permission, true)
            && ! in_array('events.update', $this->all_permission, true)) {
            abort(403, 'Permission denied: events.delete');
        }

        $event = Event::findOrFail($id);
        $name = $event->name;
        $sheetIds = $event->timesheets()->pluck('id');
        if ($sheetIds->count() > 0) {
            EventTimesheetEntry::whereIn('timesheet_id', $sheetIds)->delete();
        }
        $event->timesheets()->delete();
        $event->payments()->delete();
        $event->contracts()->delete();
        $event->reminders()->delete();
        $event->assignments()->delete();
        $event->labourBudget()->delete();
        $event->publication()->delete();
        $event->statusHistories()->delete();
        $event->delete();

        return redirect()->route('events.dashboard')->with('message', $name.' deleted.');
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
            $workDates = $this->dateList(isset($row['work_dates']) ? $row['work_dates'] : []);
            $assignmentId = isset($row['assignment_id']) ? trim((string) $row['assignment_id']) : '';
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
                'days' => count($workDates) ? count($workDates) : $days,
                'rate' => $rate,
                'food' => $food,
                'category_id' => $categoryId,
                'work_dates' => $workDates,
                'assignment_id' => $assignmentId,
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
