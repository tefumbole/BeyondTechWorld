<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingProduct;
use App\BtwContract;
use App\ContractDocument;
use App\ContractLink;
use App\ContractRateCategory;
use App\ContractReminder;
use App\ContractSetting;
use App\ContractSignatory;
use App\ContractTemplate;
use App\ContractType;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Customer;
use App\CustomerGroup;
use App\Event;
use App\Quotation;
use App\Sale;
use App\Services\Contracts\ContractAuditService;
use App\Services\Contracts\ContractInstanceService;
use App\Services\Contracts\ContractPdfService;
use App\Services\Contracts\ContractWorkflowService;
use App\Services\PeopleDirectoryService;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class ContractController extends Controller
{
    protected $instances;
    protected $workflow;
    protected $pdf;
    protected $people;
    protected $audit;
    protected $all_permission = [];

    public function __construct(
        ContractInstanceService $instances,
        ContractWorkflowService $workflow,
        ContractPdfService $pdf,
        PeopleDirectoryService $people,
        ContractAuditService $audit
    ) {
        $this->instances = $instances;
        $this->workflow = $workflow;
        $this->pdf = $pdf;
        $this->people = $people;
        $this->audit = $audit;
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

    protected function authorizePerm($names)
    {
        $names = (array) $names;
        foreach ($names as $n) {
            if (in_array($n, $this->all_permission, true)) {
                return;
            }
        }
        if (in_array('contracts_module', $this->all_permission, true)
            && (in_array('contracts.view', $names, true) || in_array('contracts.create', $names, true))) {
            return;
        }
        abort(403, 'You are not allowed to access Contracts.');
    }

    public function index(Request $request)
    {
        $this->authorizePerm('contracts.view');
        $status = $request->get('status', 'all');
        $q = $request->get('q');
        $typeId = $request->get('type_id');
        $linkType = $request->get('link_type');
        $linkId = $request->get('link_id');
        $attachMode = (bool) $request->get('attach_mode');

        $query = BtwContract::with(['type', 'partyA', 'partyB', 'signatories'])->orderByDesc('created_at');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($typeId) {
            $query->where('type_id', $typeId);
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('number', 'like', '%'.$q.'%')
                    ->orWhere('title', 'like', '%'.$q.'%');
            });
        }
        if ($linkType && $linkId && ! $attachMode) {
            $ids = ContractLink::where('link_type', $linkType)->where('link_id', (string) $linkId)->pluck('contract_id');
            $query->whereIn('id', $ids);
        }

        return view('contracts.index', [
            'contracts' => $query->paginate(25),
            'types' => ContractType::where('active', true)->orderBy('name')->get(),
            'q' => $q,
            'typeId' => $typeId,
            'status' => $status,
            'linkType' => $linkType,
            'linkId' => $linkId,
            'attachMode' => $attachMode,
            'ctTab' => 'contracts.index',
        ]);
    }

    public function awaitingClient(Request $request)
    {
        $request->merge(['status' => BtwContract::STATUS_AWAITING_CLIENT]);
        $view = $this->index($request);
        $view->with('ctTab', 'contracts.awaiting_client');

        return $view;
    }

    public function awaitingAdmin(Request $request)
    {
        $request->merge(['status' => BtwContract::STATUS_AWAITING_ADMIN]);
        $view = $this->index($request);
        $view->with('ctTab', 'contracts.awaiting_admin');

        return $view;
    }

    public function signed(Request $request)
    {
        $request->merge(['status' => BtwContract::STATUS_SIGNED]);
        $view = $this->index($request);
        $view->with('ctTab', 'contracts.signed');

        return $view;
    }

    public function create(Request $request)
    {
        $this->authorizePerm('contracts.create');

        $linkType = $request->get('link_type');
        $linkId = $request->get('link_id');

        return view('contracts.create', $this->wizardSharedData() + [
            'link_type' => $linkType,
            'link_id' => $linkId,
            'preferred_template_code' => $this->preferredTemplateForLink($linkType, $linkId),
            'ctTab' => 'contracts.index',
        ]);
    }

    /**
     * Map a linked record to the Contracts template that carries the legacy agreement wording.
     */
    protected function preferredTemplateForLink($linkType, $linkId = null)
    {
        if ($linkType === 'shareholder') {
            return 'SHR-MAIN';
        }
        if ($linkType !== 'booking' || ! $linkId) {
            return $linkType === 'booking' ? 'RNT-EQUIPMENT' : null;
        }
        $legacy = \App\BookingContract::where('booking_id', $linkId)->orderByDesc('id')->first();
        $type = $legacy->contract_type ?? 'equipment';
        if ($type === 'accommodation') {
            return 'RNT-ACCOMMODATION';
        }
        if ($type === 'software_license') {
            return 'SFT-LICENSE';
        }
        if ($type === 'studio_rental') {
            return 'RNT-STUDIO';
        }

        return 'RNT-EQUIPMENT';
    }

    protected function wizardSharedData()
    {
        $quotations = Quotation::orderByDesc('id')->limit(150)->get([
            'id', 'reference_no', 'grand_total', 'total_price', 'created_at', 'customer_id',
        ]);
        $sales = Sale::orderByDesc('id')->limit(150)->get([
            'id', 'reference_no', 'grand_total', 'total_price', 'created_at', 'customer_id',
        ]);
        $bookings = class_exists(Booking::class)
            ? Booking::orderByDesc('id')->limit(100)->get(['id', 'reference_no', 'grand_total', 'created_at', 'customer_id'])
            : collect();
        $shareholders = class_exists(\App\Shareholder::class)
            ? \App\Shareholder::orderByDesc('id')->limit(100)->get(['id', 'reference_number', 'full_name', 'investment_amount', 'email'])
            : collect();

        return [
            'templates' => ContractTemplate::with(['type', 'currentVersion'])->where('active', true)->orderBy('name')->get(),
            'types' => ContractType::where('active', true)->orderBy('name')->get(),
            'rates' => ContractRateCategory::where('active', true)->orderBy('daily_rate', 'desc')->get(),
            'events' => Event::orderByDesc('created_at')->limit(100)->get([
                'id', 'name', 'reference_no', 'venue', 'event_start_at', 'event_end_at', 'customer_id',
            ]),
            'quotations' => $quotations,
            'sales' => $sales,
            'bookings' => $bookings,
            'shareholders' => $shareholders,
            'customerGroups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
            'company' => [
                'name' => ContractSetting::getValue('company_legal_name', 'Beyond Enterprise'),
                'address' => ContractSetting::getValue('company_address', ''),
            ],
        ];
    }

    public function store(Request $request)
    {
        $this->authorizePerm('contracts.create');
        $request->validate([
            'template_id' => 'required|string',
            'title' => 'required|string|max:255',
            'party_a.name' => 'required|string|max:255',
            'party_b.name' => 'required|string|max:255',
        ]);

        $template = ContractTemplate::with('currentVersion')->findOrFail($request->template_id);
        try {
            $contract = $this->instances->createFromTemplate($template, $request->all());
            $this->syncReminders($contract, (array) $request->get('reminders', []));
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['template_id' => $e->getMessage()]);
        }

        return redirect()->route('contracts.show', $contract->id)
            ->with('message', 'Contract '.$contract->number.' created as draft.');
    }

    public function show($id)
    {
        $this->authorizePerm('contracts.view');
        $contract = BtwContract::with([
            'type', 'template.currentVersion', 'currentRevision', 'partyA', 'partyB',
            'witnesses', 'signatories', 'links', 'documents', 'auditLogs', 'reminders',
        ])->findOrFail($id);

        return view('contracts.show', [
            'contract' => $contract,
            'missing' => $this->instances->validateForSend($contract),
            'bodyHtml' => $this->instances->renderedHtml($contract),
            'ctTab' => 'contracts.index',
        ]);
    }

    public function edit($id)
    {
        $this->authorizePerm('contracts.edit');
        $contract = BtwContract::with(['partyA', 'partyB', 'currentRevision', 'witnesses', 'type'])->findOrFail($id);
        if (! $contract->isEditable()) {
            return redirect()->route('contracts.show', $id)->withErrors(['edit' => 'This contract is not editable in its current status.']);
        }

        return view('contracts.edit', $this->wizardSharedData() + [
            'contract' => $contract,
            'ctTab' => 'contracts.index',
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizePerm('contracts.edit');
        $contract = BtwContract::findOrFail($id);
        try {
            if (! $contract->isEditable()) {
                throw new \RuntimeException('Signed, cancelled, or superseded contracts cannot be edited. Use Supersede for amendments.');
            }
            if ($contract->editsInPlace()) {
                $this->instances->updateDraft($contract, $request->all());
            } else {
                $this->workflow->materialEditCreatesRevision($contract, $request->all());
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['edit' => $e->getMessage()]);
        }

        return redirect()->route('contracts.show', $id)->with('message', 'Contract updated.');
    }

    public function preview($id)
    {
        $this->authorizePerm('contracts.view');
        $contract = BtwContract::with(['currentRevision', 'partyA', 'partyB'])->findOrFail($id);

        return $this->pdf->streamPreview($contract);
    }

    public function ready(Request $request, $id)
    {
        $this->authorizePerm('contracts.edit');
        $contract = BtwContract::findOrFail($id);
        try {
            $this->workflow->markReady($contract);
        } catch (\Throwable $e) {
            return back()->withErrors(['ready' => $e->getMessage()]);
        }

        return back()->with('message', 'Contract marked Ready to Send.');
    }

    public function send(Request $request, $id)
    {
        $this->authorizePerm('contracts.send');
        $contract = BtwContract::with('signatories')->findOrFail($id);
        try {
            $this->workflow->sendForSignature($contract, $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['send' => $e->getMessage()]);
        }

        return back()->with('message', 'Contract sent for signature. Outstanding links invalidated on any later material edit.');
    }

    public function resend(Request $request, $id, $signatoryId)
    {
        $this->authorizePerm('contracts.send');
        $contract = BtwContract::findOrFail($id);
        $sig = ContractSignatory::where('contract_id', $contract->id)->findOrFail($signatoryId);
        try {
            $this->workflow->resend($contract, $sig, $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['resend' => $e->getMessage()]);
        }

        return back()->with('message', 'Signature invite resent.');
    }

    public function signAdmin(Request $request, $id)
    {
        $this->authorizePerm('contracts.sign_admin');
        $contract = BtwContract::with('signatories')->findOrFail($id);
        $admin = $contract->signatories()->where('role', 'admin')->where('status', 'pending')->first();
        if (! $admin) {
            return back()->withErrors(['sign' => 'No pending admin signature.']);
        }

        // Issue ephemeral request and sign immediately
        $plain = \Illuminate\Support\Str::random(48);
        \App\SignatureRequest::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'signatory_id' => $admin->id,
            'token_hash' => hash('sha256', $plain),
            'channel' => 'admin',
            'sent_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        $req = $this->workflow->findByToken($plain);
        try {
            $this->workflow->sign($req, [
                'typed_name' => $request->get('typed_name', Auth::user()->name),
                'consent' => true,
                'signature_image' => $request->get('signature_image'),
            ], $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['sign' => $e->getMessage()]);
        }

        return back()->with('message', 'Admin signature recorded.');
    }

    public function cancel(Request $request, $id)
    {
        $this->authorizePerm('contracts.cancel');
        $contract = BtwContract::findOrFail($id);
        try {
            $this->workflow->cancel($contract, $request->get('reason'), $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('contracts.index')->with('message', 'Contract cancelled.');
    }

    public function supersede(Request $request, $id)
    {
        $this->authorizePerm(['contracts.create', 'contracts.cancel']);
        $contract = BtwContract::findOrFail($id);
        try {
            $new = $this->workflow->supersede($contract, $request);
            $reason = trim((string) $request->get('amendment_reason', ''));
            if ($reason !== '') {
                $new->purpose = trim(($new->purpose ? $new->purpose."\n" : '').'Amendment reason: '.$reason);
                $new->save();
                $this->audit->log($new->id, 'amendment_reason', null, ['reason' => $reason], $request);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['supersede' => $e->getMessage()]);
        }

        return redirect()->route('contracts.edit', $new->id)
            ->with('message', 'Amendment draft created from signed contract. Edit content, then send for signature.');
    }

    /**
     * Phase 5: bulk Engineer Engagement contracts from event workforce.
     */
    public function bulkEngineerEngagements(Request $request, $eventId)
    {
        $this->authorizePerm(['contracts.create', 'contracts.bulk']);
        $request->validate([
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'string',
        ]);
        $event = \App\Event::findOrFail($eventId);
        try {
            $result = app(\App\Services\Contracts\ContractBulkEngagementService::class)
                ->createForAssignments($event, $request->assignment_ids);
        } catch (\Throwable $e) {
            return back()->withErrors(['bulk' => $e->getMessage()]);
        }

        $msg = count($result['created']).' enterprise contract(s) created';
        if (count($result['skipped'])) {
            $msg .= '; '.count($result['skipped']).' skipped';
        }
        if (count($result['errors'])) {
            $msg .= '; '.count($result['errors']).' failed';
        }

        return back()->with('message', $msg);
    }

    public function download($id, $docId)
    {
        $this->authorizePerm('contracts.view');
        $doc = ContractDocument::where('contract_id', $id)->findOrFail($docId);
        $path = storage_path('app/'.$doc->file_path);
        if (! is_file($path)) {
            abort(404);
        }

        return Response::download($path, basename($path));
    }

    public function peopleSearch(Request $request)
    {
        $this->authorizePerm('contracts.view');
        $filter = $request->get('filter', 'all');
        $rows = $this->people->eligibleForTasks($filter, $request->get('q', ''));

        return response()->json($rows->take(50)->values());
    }

    public function quickCustomer(Request $request)
    {
        $this->authorizePerm(['contracts.create', 'contracts.edit']);
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'customer_group_id' => 'nullable|integer',
        ]);

        $groupId = $request->get('customer_group_id')
            ?: optional(CustomerGroup::where('name', 'GENERAL')->first() ?: CustomerGroup::orderBy('id')->first())->id
            ?: 1;

        $customer = Customer::create([
            'customer_group_id' => $groupId,
            'name' => $request->name,
            'company_name' => $request->get('company_name'),
            'phone_number' => WhatsAppPhone::sanitizeForStorage($request->phone),
            'email' => $request->get('email'),
            'address' => $request->get('address') ?: 'N/A',
            'city' => $request->get('city') ?: 'N/A',
            'is_active' => true,
        ]);

        return response()->json([
            'id' => 'customer:'.$customer->id,
            'subject_type' => 'customer',
            'subject_id' => (string) $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone_number,
            'address' => $customer->address,
            'organization' => $customer->company_name,
            'source' => 'Customer',
        ]);
    }

    public function linkMeta(Request $request)
    {
        $this->authorizePerm('contracts.view');
        $type = $request->get('type');
        $id = $request->get('id');
        if (! $type || ! $id) {
            return response()->json(['error' => 'Missing type/id'], 422);
        }

        $meta = ['type' => $type, 'id' => $id, 'label' => '', 'value' => null, 'currency' => 'XAF',
            'effective_date' => null, 'start_date' => null, 'end_date' => null, 'title' => null, 'customer' => null];

        if ($type === 'quotation') {
            $q = Quotation::with('customer')->find($id);
            if (! $q) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $meta['label'] = $q->reference_no;
            $meta['value'] = $q->grand_total ?? $q->total_price;
            $meta['effective_date'] = optional($q->created_at)->format('Y-m-d');
            $meta['title'] = 'Contract for quotation '.$q->reference_no;
            if ($q->customer) {
                $meta['customer'] = [
                    'id' => 'customer:'.$q->customer->id,
                    'subject_type' => 'customer',
                    'subject_id' => (string) $q->customer->id,
                    'name' => $q->customer->name,
                    'email' => $q->customer->email,
                    'phone' => $q->customer->phone_number,
                    'address' => $q->customer->address,
                    'organization' => $q->customer->company_name,
                ];
            }
        } elseif ($type === 'sale') {
            $s = Sale::with('customer')->find($id);
            if (! $s) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $meta['label'] = $s->reference_no;
            $meta['value'] = $s->grand_total ?? $s->total_price;
            $meta['effective_date'] = optional($s->created_at)->format('Y-m-d');
            $meta['title'] = 'Contract for sale '.$s->reference_no;
            if ($s->customer) {
                $meta['customer'] = [
                    'id' => 'customer:'.$s->customer->id,
                    'subject_type' => 'customer',
                    'subject_id' => (string) $s->customer->id,
                    'name' => $s->customer->name,
                    'email' => $s->customer->email,
                    'phone' => $s->customer->phone_number,
                    'address' => $s->customer->address,
                    'organization' => $s->customer->company_name,
                ];
            }
        } elseif ($type === 'booking') {
            $b = Booking::with('customer')->find($id);
            if (! $b) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $meta['label'] = $b->reference_no;
            $meta['value'] = $b->grand_total ?? $b->total_price ?? null;
            $meta['effective_date'] = optional($b->created_at)->format('Y-m-d');
            $meta['preferred_template_code'] = $this->preferredTemplateForLink('booking', $b->id);
            $titles = [
                'RNT-ACCOMMODATION' => 'Student Accommodation Agreement — ',
                'SFT-LICENSE' => 'Software License Subscription — ',
                'RNT-EQUIPMENT' => 'Equipment Rental Agreement — ',
                'RNT-STUDIO' => 'Studio Rental Agreement — ',
            ];
            $meta['title'] = ($titles[$meta['preferred_template_code']] ?? 'Rental Agreement — ').$b->reference_no;
            $range = BookingProduct::where('booking_id', $b->id)
                ->selectRaw('MIN(`start`) as start_min, MAX(`end`) as end_max')
                ->first();
            if ($range && $range->start_min) {
                $meta['start_date'] = date('Y-m-d', strtotime($range->start_min));
            }
            if ($range && $range->end_max) {
                $meta['end_date'] = date('Y-m-d', strtotime($range->end_max));
            }
            if ($b->customer) {
                $meta['customer'] = [
                    'id' => 'customer:'.$b->customer->id,
                    'subject_type' => 'customer',
                    'subject_id' => (string) $b->customer->id,
                    'name' => $b->customer->name,
                    'email' => $b->customer->email,
                    'phone' => $b->customer->phone_number,
                    'address' => $b->customer->address,
                    'organization' => $b->customer->company_name,
                ];
            }
        } elseif ($type === 'shareholder') {
            $sh = \App\Shareholder::find($id);
            if (! $sh) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $meta['label'] = $sh->reference_number.' — '.$sh->full_name;
            $meta['value'] = $sh->investment_amount;
            $meta['effective_date'] = optional($sh->agreement_signed_at ?: $sh->created_at)->format('Y-m-d');
            $meta['title'] = 'Shareholder Agreement — '.$sh->reference_number;
            $meta['preferred_template_code'] = 'SHR-MAIN';
            $meta['customer'] = [
                'id' => 'shareholder:'.$sh->id,
                'subject_type' => 'shareholder',
                'subject_id' => (string) $sh->id,
                'name' => $sh->full_name,
                'email' => $sh->email,
                'phone' => $sh->full_phone_number ?? $sh->phone,
                'address' => $sh->address,
                'organization' => $sh->company_name,
            ];
        } elseif ($type === 'event') {
            $e = Event::find($id);
            if (! $e) {
                return response()->json(['error' => 'Not found'], 404);
            }
            $meta['label'] = $e->name.' ('.$e->reference_no.')';
            $meta['start_date'] = optional($e->event_start_at)->format('Y-m-d');
            $meta['end_date'] = optional($e->event_end_at)->format('Y-m-d');
            $meta['effective_date'] = $meta['start_date'] ?: date('Y-m-d');
            $meta['title'] = 'Contract — '.$e->name;
        } else {
            return response()->json(['error' => 'Unsupported type'], 422);
        }

        return response()->json($meta);
    }

    public function templateBody($id)
    {
        $this->authorizePerm('contracts.view');
        $tpl = ContractTemplate::with('currentVersion')->findOrFail($id);

        return response()->json([
            'id' => $tpl->id,
            'name' => $tpl->name,
            'content_html' => optional($tpl->currentVersion)->content_html,
            'party_a_label' => optional($tpl->type)->default_party_a_label,
            'party_b_label' => optional($tpl->type)->default_party_b_label,
        ]);
    }

    public function attach(Request $request, $id)
    {
        $this->authorizePerm('contracts.edit');
        $contract = BtwContract::findOrFail($id);
        $request->validate([
            'link_type' => 'required|in:'.implode(',', ContractLink::ALLOWED_TYPES),
            'link_id' => 'required',
        ]);
        try {
            $this->instances->attachLink($contract, $request->link_type, $request->link_id, (bool) $request->get('primary', true));
        } catch (\Throwable $e) {
            return back()->withErrors(['link' => $e->getMessage()]);
        }

        return back()->with('message', 'Record linked to contract.');
    }

    public function storeReminder(Request $request, $id)
    {
        $this->authorizePerm(['contracts.edit', 'contracts.create']);
        $contract = BtwContract::findOrFail($id);
        $request->validate([
            'reminder_time' => 'required|date',
            'label' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:2000',
        ]);

        try {
            $when = Carbon::parse($request->reminder_time);
        } catch (\Throwable $e) {
            return back()->withErrors(['reminder' => 'Invalid reminder time.']);
        }

        ContractReminder::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'reminder_time' => $when,
            'label' => $request->get('label'),
            'message' => $request->get('message'),
            'is_sent' => false,
            'created_by' => Auth::id(),
        ]);
        $this->audit->log($contract->id, 'reminder_added', null, ['at' => $when->toDateTimeString()]);

        return back()->with('message', 'Reminder scheduled for '.$when->format('M j, Y H:i').'.');
    }

    public function destroyReminder($id, $reminderId)
    {
        $this->authorizePerm('contracts.edit');
        $reminder = ContractReminder::where('contract_id', $id)->findOrFail($reminderId);
        if ($reminder->is_sent) {
            return back()->withErrors(['reminder' => 'Sent reminders cannot be deleted.']);
        }
        $reminder->delete();

        return back()->with('message', 'Reminder removed.');
    }

    protected function syncReminders(BtwContract $contract, array $times)
    {
        foreach ($times as $raw) {
            if (! $raw) {
                continue;
            }
            try {
                $when = Carbon::parse($raw);
            } catch (\Throwable $e) {
                continue;
            }
            ContractReminder::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'reminder_time' => $when,
                'is_sent' => false,
                'created_by' => Auth::id(),
            ]);
        }
    }
}
