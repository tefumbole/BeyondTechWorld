<?php

namespace App\Services\Contracts;

use App\BtwContract;
use App\ContractLink;
use App\ContractParty;
use App\ContractRevision;
use App\ContractSetting;
use App\ContractSignatory;
use App\ContractTemplate;
use App\ContractValue;
use App\ContractWitness;
use App\Booking;
use App\BookingContract;
use App\BookingProduct;
use App\Event;
use App\Product;
use App\Quotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractInstanceService
{
    protected $numbers;
    protected $audit;
    protected $resolver;

    public function __construct(
        ContractNumberService $numbers,
        ContractAuditService $audit,
        PlaceholderResolver $resolver
    ) {
        $this->numbers = $numbers;
        $this->audit = $audit;
        $this->resolver = $resolver;
    }

    public function createFromTemplate(ContractTemplate $template, array $input)
    {
        $version = $template->currentVersion;
        if (! $version) {
            throw new \InvalidArgumentException('Template has no published version.');
        }

        return DB::transaction(function () use ($template, $version, $input) {
            $type = $template->type;
            $contract = BtwContract::create([
                'id' => (string) Str::uuid(),
                'number' => $this->numbers->next($type ? $type->code : null),
                'type_id' => $template->type_id,
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'title' => $input['title'] ?? $template->name,
                'status' => BtwContract::STATUS_DRAFT,
                'owner_id' => Auth::id(),
                'effective_date' => $input['effective_date'] ?? now()->toDateString(),
                'start_date' => $input['start_date'] ?? null,
                'end_date' => $input['end_date'] ?? null,
                'value' => $input['value'] ?? null,
                'currency' => $input['currency'] ?? 'XAF',
                'jurisdiction' => $input['jurisdiction'] ?? ContractSetting::getValue('default_jurisdiction'),
                'purpose' => $input['purpose'] ?? null,
                'payment_schedule' => $input['payment_schedule'] ?? null,
            ]);

            $contentHtml = ! empty($input['content_html'])
                ? $input['content_html']
                : $version->content_html; // independent copy of published template
            $revision = ContractRevision::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'revision_no' => 1,
                'content_html' => $contentHtml,
                'content_json' => $version->content_json,
                'checksum' => hash('sha256', (string) $contentHtml),
                'state' => 'draft',
                'created_by' => Auth::id(),
            ]);
            $contract->current_revision_id = $revision->id;
            $contract->save();

            $labelA = $input['party_a_role'] ?? ($type->default_party_a_label ?? 'Party A');
            $labelB = $input['party_b_role'] ?? ($type->default_party_b_label ?? 'Party B');

            $partyA = $this->storeParty($contract, 'A', $labelA, $input['party_a'] ?? []);
            $partyB = $this->storeParty($contract, 'B', $labelB, $input['party_b'] ?? []);

            $this->storeWitness($contract, 'A', $input['witness_a'] ?? null);
            $this->storeWitness($contract, 'B', $input['witness_b'] ?? null);

            if (! empty($input['link_type']) && ! empty($input['link_id'])) {
                $this->attachLink($contract, $input['link_type'], $input['link_id'], true);
            }

            $data = $this->buildPlaceholderData($contract, $input);
            foreach ($data as $key => $val) {
                if (strpos($key, '.') === false) {
                    continue;
                }
                ContractValue::create([
                    'id' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'revision_id' => $revision->id,
                    'placeholder_key' => $key,
                    'value_json' => $val,
                    'manually_overridden' => ! empty($input['values'][$key]),
                ]);
            }
            $revision->resolved_data_json = $data;
            $revision->save();

            $contract->load('witnesses');
            $this->ensureSignatories($contract, $version, $partyA, $partyB);

            $this->audit->log($contract->id, 'created', null, [
                'number' => $contract->number,
                'template_version_id' => $version->id,
            ]);

            return $contract->fresh(['partyA', 'partyB', 'currentRevision', 'type', 'template', 'signatories', 'links']);
        });
    }

    public function updateDraft(BtwContract $contract, array $input)
    {
        if (! $contract->isEditable()) {
            throw new \RuntimeException('This contract cannot be edited in its current status.');
        }

        $contract->fill([
            'title' => $input['title'] ?? $contract->title,
            'effective_date' => $input['effective_date'] ?? $contract->effective_date,
            'start_date' => $input['start_date'] ?? $contract->start_date,
            'end_date' => $input['end_date'] ?? $contract->end_date,
            'value' => array_key_exists('value', $input) ? $input['value'] : $contract->value,
            'currency' => $input['currency'] ?? $contract->currency,
            'jurisdiction' => $input['jurisdiction'] ?? $contract->jurisdiction,
            'purpose' => $input['purpose'] ?? $contract->purpose,
            'payment_schedule' => $input['payment_schedule'] ?? $contract->payment_schedule,
        ]);
        $contract->save();

        if (! empty($input['link_type']) && ! empty($input['link_id'])) {
            ContractLink::where('contract_id', $contract->id)->delete();
            $this->attachLink($contract, $input['link_type'], $input['link_id'], true);
        }

        $revision = $contract->currentRevision;
        if ($revision && array_key_exists('content_html', $input) && $input['content_html'] !== null && $input['content_html'] !== '') {
            $revision->content_html = $input['content_html'];
            $revision->checksum = hash('sha256', (string) $input['content_html']);
        }
        if ($revision) {
            $data = $this->buildPlaceholderData($contract->fresh(['partyA', 'partyB', 'witnesses', 'type']), $input);
            $revision->resolved_data_json = $data;
            $revision->save();
            ContractValue::where('contract_id', $contract->id)->where('revision_id', $revision->id)->delete();
            foreach ($this->flatten($data) as $key => $val) {
                ContractValue::create([
                    'id' => (string) Str::uuid(),
                    'contract_id' => $contract->id,
                    'revision_id' => $revision->id,
                    'placeholder_key' => $key,
                    'value_json' => $val,
                ]);
            }
        }

        if (! empty($input['party_a']['name'])) {
            optional($contract->partyA)->delete();
            $this->storeParty($contract, 'A', $input['party_a_role'] ?? optional($contract->type)->default_party_a_label, $input['party_a']);
        }
        if (! empty($input['party_b']['name'])) {
            optional($contract->partyB)->delete();
            $this->storeParty($contract, 'B', $input['party_b_role'] ?? optional($contract->type)->default_party_b_label, $input['party_b']);
        }

        if (array_key_exists('witness_a', $input) || array_key_exists('witness_b', $input)) {
            $contract->witnesses()->delete();
            $this->storeWitness($contract, 'A', $input['witness_a'] ?? null);
            $this->storeWitness($contract, 'B', $input['witness_b'] ?? null);
        }

        $this->audit->log($contract->id, 'updated');

        return $contract->fresh(['partyA', 'partyB', 'currentRevision', 'signatories', 'witnesses']);
    }

    public function validateForSend(BtwContract $contract)
    {
        $revision = $contract->currentRevision;
        $version = $contract->template_version_id
            ? \App\ContractTemplateVersion::find($contract->template_version_id)
            : ($contract->template ? $contract->template->currentVersion : null);
        $schema = $version ? ($version->placeholder_schema ?: []) : [];
        $required = $schema['required'] ?? [];
        $data = $revision ? ($revision->resolved_data_json ?: []) : [];
        // Flatten nested for resolver
        $flat = $this->flatten($data);
        $html = $revision ? $revision->content_html : '';
        $missing = $this->resolver->unresolvedRequired($html, $required, array_merge($flat, $data));

        if (! $contract->partyA || ! $contract->partyB) {
            $missing[] = 'parties';
        }

        return $missing;
    }

    public function renderedHtml(BtwContract $contract)
    {
        $revision = $contract->currentRevision;
        if (! $revision) {
            return '';
        }
        $data = $revision->resolved_data_json ?: [];
        $flat = $this->flatten($data);

        return $this->resolver->resolve($revision->content_html, array_merge($flat, $data));
    }

    protected function storeParty(BtwContract $contract, $side, $roleLabel, array $party)
    {
        if (empty($party) && $side === 'A') {
            $party = [
                'name' => ContractSetting::getValue('company_legal_name', 'Beyond Enterprise'),
                'address' => ContractSetting::getValue('company_address', ''),
                'subject_type' => 'company',
                'subject_id' => 'beyond',
            ];
        }

        return ContractParty::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'side' => $side,
            'subject_type' => $party['subject_type'] ?? null,
            'subject_id' => isset($party['subject_id']) ? (string) $party['subject_id'] : null,
            'role_label' => $roleLabel,
            'identity_snapshot_json' => [
                'name' => $party['name'] ?? '',
                'email' => $party['email'] ?? '',
                'phone' => $party['phone'] ?? '',
                'address' => $party['address'] ?? '',
                'organization' => $party['organization'] ?? ($party['company_name'] ?? ''),
                'id_number' => $party['id_number'] ?? '',
            ],
        ]);
    }

    protected function storeWitness(BtwContract $contract, $forParty, $witness)
    {
        if (! $witness || empty($witness['name'])) {
            return null;
        }
        $w = ContractWitness::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'for_party' => $forParty,
            'person_type' => $witness['subject_type'] ?? null,
            'person_id' => isset($witness['subject_id']) ? (string) $witness['subject_id'] : null,
            'identity_snapshot_json' => [
                'name' => $witness['name'] ?? '',
                'email' => $witness['email'] ?? '',
                'phone' => $witness['phone'] ?? '',
            ],
        ]);

        return $w;
    }

    public function attachLink(BtwContract $contract, $type, $id, $primary = false)
    {
        if (! in_array($type, ContractLink::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported link type.');
        }
        if ($primary) {
            ContractLink::where('contract_id', $contract->id)->update(['is_primary' => false]);
            $contract->primary_link_type = $type;
            $contract->primary_link_id = (string) $id;
            $contract->save();
        }

        return ContractLink::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'link_type' => $type,
            'link_id' => (string) $id,
            'relationship' => $primary ? 'primary' : 'related',
            'is_primary' => $primary,
        ]);
    }

    public function ensureSignatories(BtwContract $contract, $version, $partyA, $partyB)
    {
        ContractSignatory::where('contract_id', $contract->id)->delete();
        $workflow = $version ? ($version->signature_workflow_json ?: []) : [];
        $stages = $workflow['stages'] ?? [
            ['stage' => 1, 'roles' => ['party_a', 'party_b']],
            ['stage' => 2, 'roles' => ['admin']],
        ];
        $roleStage = [];
        foreach ($stages as $st) {
            foreach ($st['roles'] as $role) {
                $roleStage[$role] = (int) $st['stage'];
            }
        }

        $snapA = $partyA ? $partyA->snapshot() : [];
        $snapB = $partyB ? $partyB->snapshot() : [];

        $defs = [
            'party_a' => [$partyA ? $partyA->id : null, $snapA['name'] ?? 'Party A', $snapA['email'] ?? null, $snapA['phone'] ?? null],
            'party_b' => [$partyB ? $partyB->id : null, $snapB['name'] ?? 'Party B', $snapB['email'] ?? null, $snapB['phone'] ?? null],
            'admin' => [null, 'Authorized Admin Signer', null, null],
        ];

        foreach ($defs as $role => $row) {
            if (! isset($roleStage[$role]) && $role === 'admin') {
                $roleStage[$role] = 99;
            }
            if (! isset($roleStage[$role])) {
                continue;
            }
            ContractSignatory::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'revision_id' => $contract->current_revision_id,
                'role' => $role,
                'party_id' => $row[0],
                'display_name' => $row[1],
                'email' => $row[2],
                'phone' => $row[3],
                'stage' => $roleStage[$role],
                'required' => true,
                'status' => 'pending',
            ]);
        }

        foreach ($contract->witnesses as $w) {
            $role = $w->for_party === 'A' ? 'witness_a' : 'witness_b';
            if (! isset($roleStage[$role])) {
                continue;
            }
            $snap = json_decode($w->getAttributes()['identity_snapshot_json'] ?? '{}', true) ?: [];
            $sig = ContractSignatory::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'revision_id' => $contract->current_revision_id,
                'role' => $role,
                'display_name' => $snap['name'] ?? 'Witness',
                'email' => $snap['email'] ?? null,
                'phone' => $snap['phone'] ?? null,
                'stage' => $roleStage[$role],
                'required' => true,
                'status' => 'pending',
            ]);
            $w->signatory_id = $sig->id;
            $w->save();
        }
    }

    public function buildPlaceholderData(BtwContract $contract, array $input = [])
    {
        $contract->loadMissing(['partyA', 'partyB', 'witnesses', 'type']);
        $a = $contract->partyA ? $contract->partyA->snapshot() : [];
        $b = $contract->partyB ? $contract->partyB->snapshot() : [];
        $wa = [];
        $wb = [];
        foreach ($contract->witnesses as $w) {
            $s = json_decode($w->getAttributes()['identity_snapshot_json'] ?? '{}', true) ?: [];
            if ($w->for_party === 'A') {
                $wa = $s;
            } else {
                $wb = $s;
            }
        }

        $manual = $input['values'] ?? [];
        $data = [
            'contract' => [
                'number' => $contract->number,
                'effective_date' => $this->fmtDate($contract->effective_date),
                'start_date' => $this->fmtDate($contract->start_date),
                'end_date' => $this->fmtDate($contract->end_date),
                'value' => $contract->value,
                'currency' => $contract->currency,
                'jurisdiction' => $contract->jurisdiction,
                'purpose' => $contract->purpose,
            ],
            'party_a' => [
                'role_label' => optional($contract->partyA)->role_label,
                'name' => $a['name'] ?? '',
                'address' => $a['address'] ?? '',
                'email' => $a['email'] ?? '',
                'phone' => $a['phone'] ?? '',
                'signer_name' => $manual['party_a.signer_name'] ?? ($a['name'] ?? ''),
                'signer_title' => $manual['party_a.signer_title'] ?? 'Authorized Signatory',
            ],
            'party_b' => [
                'role_label' => optional($contract->partyB)->role_label,
                'name' => $b['name'] ?? '',
                'address' => $b['address'] ?? '',
                'email' => $b['email'] ?? '',
                'phone' => $b['phone'] ?? '',
            ],
            'witness_a' => ['name' => $wa['name'] ?? ''],
            'witness_b' => ['name' => $wb['name'] ?? ''],
            'worker' => [
                'role' => $manual['worker.role'] ?? ($input['worker_role'] ?? ''),
                'daily_rate' => $manual['worker.daily_rate'] ?? ($input['worker_daily_rate'] ?? ''),
                'food_allowance' => $manual['worker.food_allowance'] ?? '1,000',
            ],
            'event' => [
                'name' => '',
                'venue' => '',
                'start_date' => '',
                'end_date' => '',
            ],
            'quotation' => ['number' => '', 'total' => ''],
            'booking' => [
                'reference' => '',
                'grand_total' => '',
                'paid_amount' => '',
                'balance' => '',
                'notes' => '',
                'schedule_html' => '<p><em>No rental schedule linked.</em></p>',
            ],
            'rental' => [
                'kind_label' => 'Equipment / Accommodation Rental',
            ],
            'share' => [
                'price_label' => '',
                'price_per_share' => '',
                'currency' => 'USD',
            ],
            'shareholder' => [
                'reference' => '',
                'shares' => '',
                'investment' => '',
                'email' => '',
                'phone' => '',
                'nationality' => '',
                'company' => '',
                'address' => '',
            ],
            'job' => [
                'title' => '',
                'salary' => 'As agreed / subject to HR confirmation',
            ],
            'applicant' => [
                'reference' => '',
                'name' => '',
            ],
            'work' => [
                'schedule' => $manual['work.schedule'] ?? 'as communicated',
                'estimated_days' => $manual['work.estimated_days'] ?? ($input['work_estimated_days'] ?? ''),
                'day_rate_rule' => $manual['work.day_rate_rule'] ?? 'full day unless otherwise authorized',
                'reporting_time' => $manual['work.reporting_time'] ?? 'as scheduled',
                'supervisor_name' => $manual['work.supervisor_name'] ?? 'assigned supervisor',
            ],
            'payment' => [
                'schedule' => $contract->payment_schedule ?: '',
                'due_rule' => $manual['payment.due_rule'] ?? 'within 7 days of approved timesheets',
                'method' => $manual['payment.method'] ?? 'bank transfer or Mobile Money',
            ],
            'cancellation' => [
                'rule' => $manual['cancellation.rule'] ?? 'Payment remains due only for verified eligible work already completed.',
            ],
            'signature' => [
                'party_a' => '________________',
                'party_b' => '________________',
                'witness_a' => '________________',
                'witness_b' => '________________',
                'party_a_date' => '',
                'party_b_date' => '',
                'witness_a_date' => '',
                'witness_b_date' => '',
            ],
        ];

        if ($contract->primary_link_type === 'event' && $contract->primary_link_id) {
            $event = Event::find($contract->primary_link_id);
            if ($event) {
                $data['event'] = [
                    'name' => $event->name,
                    'venue' => $event->venue,
                    'start_date' => $this->fmtDate($event->event_start_at ?? $event->start_date ?? null),
                    'end_date' => $this->fmtDate($event->event_end_at ?? $event->end_date ?? null),
                ];
            }
        }
        if ($contract->primary_link_type === 'quotation' && $contract->primary_link_id) {
            $q = Quotation::find($contract->primary_link_id);
            if ($q) {
                $data['quotation'] = [
                    'number' => $q->reference_no ?? $q->id,
                    'total' => number_format((float) ($q->grand_total ?? 0), 0).' '.($q->currency ?? 'XAF'),
                ];
            }
        }
        if ($contract->primary_link_type === 'booking' && $contract->primary_link_id) {
            $booking = Booking::with('customer')->find($contract->primary_link_id);
            if ($booking) {
                $contract->loadMissing('template');
                $kind = 'equipment';
                $kindLabel = 'Equipment Rental';
                $legacy = BookingContract::where('booking_id', $booking->id)->orderByDesc('id')->first();
                if ($legacy) {
                    if (($legacy->contract_type ?? '') === 'accommodation') {
                        $kind = 'accommodation';
                        $kindLabel = 'Accommodation Rental';
                    } elseif (($legacy->contract_type ?? '') === 'software_license') {
                        $kind = 'software';
                        $kindLabel = 'Software License Subscription';
                    } elseif (($legacy->contract_type ?? '') === 'studio_rental') {
                        $kind = 'studio';
                        $kindLabel = 'Studio Rental';
                    }
                }
                // Prefer schedule layout matching the selected contract template
                $tplCode = optional($contract->template)->code;
                if ($tplCode === 'RNT-ACCOMMODATION') {
                    $kind = 'accommodation';
                } elseif ($tplCode === 'SFT-LICENSE') {
                    $kind = 'software';
                } elseif ($tplCode === 'RNT-STUDIO') {
                    $kind = 'studio';
                    $kindLabel = 'Studio Rental';
                } elseif ($tplCode === 'RNT-EQUIPMENT') {
                    $kind = 'equipment';
                }
                $paid = (float) ($booking->paid_amount ?? 0);
                $total = (float) ($booking->grand_total ?? $booking->total_price ?? 0);
                $data['booking'] = [
                    'reference' => $booking->reference_no,
                    'grand_total' => number_format($total, 2),
                    'paid_amount' => number_format($paid, 2),
                    'balance' => number_format(max(0, $total - $paid), 2),
                    'notes' => $booking->booking_note
                        ? strip_tags(\App\Support\BookingNoteFormatter::forDisplay($booking->booking_note))
                        : '—',
                    'schedule_html' => $this->bookingScheduleHtml($booking, $kind),
                ];
                $data['rental']['kind_label'] = $kindLabel;
                if (! $contract->value) {
                    $data['contract']['value'] = number_format($total, 2);
                }
            }
        }
        if ($contract->primary_link_type === 'shareholder' && $contract->primary_link_id) {
            $sh = class_exists(\App\Shareholder::class)
                ? \App\Shareholder::find($contract->primary_link_id)
                : null;
            if ($sh) {
                $settings = app(\App\Services\ShareSettingsService::class);
                $cfg = $settings->getSettings();
                $data['share'] = [
                    'price_label' => $settings->formatPrice($cfg['price_per_share'], $cfg['currency']),
                    'price_per_share' => number_format((float) $cfg['price_per_share'], 2),
                    'currency' => $cfg['currency'],
                ];
                $data['shareholder'] = [
                    'reference' => $sh->reference_number,
                    'shares' => number_format((int) $sh->shares_assigned),
                    'investment' => $settings->formatPrice($sh->investment_amount, $cfg['currency']),
                    'email' => $sh->email,
                    'phone' => $sh->full_phone_number ?? $sh->phone,
                    'nationality' => $sh->nationality ?: '—',
                    'company' => $sh->company_name ?: '—',
                    'address' => $sh->address ?: '—',
                ];
                if (empty($data['party_b']['name'])) {
                    $data['party_b']['name'] = $sh->full_name;
                }
            }
        } else {
            // Always expose current share price for shareholder templates without a link
            try {
                $settings = app(\App\Services\ShareSettingsService::class);
                $cfg = $settings->getSettings();
                $data['share']['price_label'] = $settings->formatPrice($cfg['price_per_share'], $cfg['currency']);
                $data['share']['price_per_share'] = number_format((float) $cfg['price_per_share'], 2);
                $data['share']['currency'] = $cfg['currency'];
            } catch (\Throwable $e) {
                // ignore if share settings table missing
            }
        }

        foreach ($manual as $k => $v) {
            if (strpos($k, '.') !== false) {
                [$ns, $field] = explode('.', $k, 2);
                if (! isset($data[$ns]) || ! is_array($data[$ns])) {
                    $data[$ns] = [];
                }
                $data[$ns][$field] = $v;
            }
        }

        return $data;
    }

    protected function flatten(array $data, $prefix = '')
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $out = array_merge($out, $this->flatten($v, $key));
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    protected function fmtDate($d)
    {
        if (! $d) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($d)->format('d F Y');
        } catch (\Throwable $e) {
            return (string) $d;
        }
    }

    protected function bookingScheduleHtml(Booking $booking, $mode = 'equipment')
    {
        $lines = BookingProduct::where('booking_id', $booking->id)->get();
        if ($lines->isEmpty()) {
            return '<p><em>No line items on this booking.</em></p>';
        }

        if ($mode === 'accommodation') {
            $headers = ['Room / Unit', 'Code', 'Qty', 'Monthly Rent', 'Subtotal', 'Occupancy Until'];
        } elseif ($mode === 'software') {
            $headers = ['Product / Service', 'Code', 'Qty', 'Price', 'Subtotal', 'From', 'To (Expires)'];
        } elseif ($mode === 'studio') {
            $headers = ['Studio / Service', 'Code', 'Method', 'Qty', 'Price', 'Subtotal', 'From', 'To'];
        } else {
            $headers = ['Equipment', 'Code', 'Qty', 'Unit Price', 'Subtotal', 'Return By'];
        }

        $methodLabels = [0 => 'Hourly', 1 => 'Daily', 2 => 'Monthly'];

        $th = '';
        foreach ($headers as $h) {
            $th .= '<th style="padding:6px;border:1px solid #d7e0ef;text-align:left;">'.e($h).'</th>';
        }
        $html = '<table style="width:100%;border-collapse:collapse;margin:8px 0;">'
            .'<thead><tr style="background:#0b3f90;color:#fff;">'.$th.'</tr></thead><tbody>';

        foreach ($lines as $line) {
            $product = Product::find($line->product_id);
            $name = $product ? $product->name : ('Item #'.$line->product_id);
            $code = $product ? $product->code : '';
            $start = $line->start ? date('d M Y', strtotime($line->start)) : 'As agreed';
            $startFull = $line->start ? date('d M Y, H:i', strtotime($line->start)) : 'As agreed';
            $endFull = $line->end ? date('d M Y, H:i', strtotime($line->end)) : 'As scheduled';
            $endDay = $line->end ? date('d M Y', strtotime($line->end)) : 'As scheduled';
            $method = $methodLabels[(int) ($line->booking_method ?? 0)] ?? 'Hourly';
            if (!empty($line->number_duration)) {
                $method .= ' × '.$line->number_duration;
            }

            $html .= '<tr>'
                .'<td style="padding:6px;border:1px solid #d7e0ef;">'.e($name).'</td>'
                .'<td style="padding:6px;border:1px solid #d7e0ef;">'.e($code).'</td>';

            if ($mode === 'studio') {
                $html .= '<td style="padding:6px;border:1px solid #d7e0ef;">'.e($method).'</td>';
            }

            $html .= '<td style="padding:6px;border:1px solid #d7e0ef;text-align:center;">'.e((string) $line->qty).'</td>'
                .'<td style="padding:6px;border:1px solid #d7e0ef;text-align:right;">'.number_format((float) $line->net_unit_price, 2).'</td>'
                .'<td style="padding:6px;border:1px solid #d7e0ef;text-align:right;">'.number_format((float) $line->total, 2).'</td>';

            if ($mode === 'software') {
                $html .= '<td style="padding:6px;border:1px solid #d7e0ef;">'.e($start).'</td>'
                    .'<td style="padding:6px;border:1px solid #d7e0ef;">'.e($endDay).'</td>';
            } elseif ($mode === 'studio') {
                $html .= '<td style="padding:6px;border:1px solid #d7e0ef;">'.e($startFull).'</td>'
                    .'<td style="padding:6px;border:1px solid #d7e0ef;">'.e($endFull).'</td>';
            } elseif ($mode === 'accommodation') {
                $html .= '<td style="padding:6px;border:1px solid #d7e0ef;">'.e($endDay).'</td>';
            } else {
                $html .= '<td style="padding:6px;border:1px solid #d7e0ef;">'.e($endFull).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
}
