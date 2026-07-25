@extends('layout.main')

@section('content')
@php
    $defaultJurisdiction = old('jurisdiction', \App\ContractSetting::getValue('default_jurisdiction', 'Republic of Cameroon'));
    $prefillLinkType = old('link_type', $link_type ?? request('link_type', ''));
    $prefillLinkId = old('link_id', $link_id ?? request('link_id', ''));
    $preferredTemplateCode = $preferred_template_code ?? null;
@endphp
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="mb-4">
            <h1 class="ct-title">Create Contract</h1>
            <p class="ct-subtitle">Select a linked record and parties from your customer/user directory. Draft stays editable after create.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ct-card">
            <div class="ct-step-nav" id="wizard-nav">
                @foreach(['Template', 'Link & Details', 'Party A', 'Party B', 'Witnesses', 'Values', 'Content', 'Review'] as $i => $label)
                    <button type="button" class="ct-step-btn {{ $i === 0 ? 'is-active' : '' }}" data-step="{{ $i + 1 }}">{{ $i + 1 }}. {{ $label }}</button>
                @endforeach
            </div>

            <form method="POST" action="{{ route('contracts.store') }}" id="contract-create-form">
                @csrf

                {{-- 1 Template --}}
                <div class="ct-wizard-step is-visible" data-step="1">
                    <h5 class="mb-3">1. Choose Template</h5>
                    <label class="ct-label">Template *</label>
                    @if($prefillLinkType === 'booking')
                        <p class="text-muted small mb-2">From a booking: pick the matching agreement under <strong>Rentals</strong> or <strong>Software License</strong> (wording matches the legacy module exactly).</p>
                    @elseif($prefillLinkType === 'shareholder')
                        <p class="text-muted small mb-2">Shareholder contracts use the <strong>Shareholder Agreement</strong> template (exact portal wording).</p>
                    @endif
                    <select name="template_id" id="ct-template-id" class="ct-field" required>
                        <option value="">Select template…</option>
                        @foreach($templates ?? [] as $tpl)
                            @php
                                $isPreferred = $preferredTemplateCode && $tpl->code === $preferredTemplateCode;
                                $isSelected = old('template_id') == $tpl->id || (! old('template_id') && $isPreferred);
                            @endphp
                            <option value="{{ $tpl->id }}"
                                data-code="{{ $tpl->code }}"
                                data-a-label="{{ optional($tpl->type)->default_party_a_label }}"
                                data-b-label="{{ optional($tpl->type)->default_party_b_label }}"
                                @if($isSelected) selected @endif>
                                {{ $tpl->name }} ({{ optional($tpl->type)->name ?? optional($tpl->type)->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 2 Link + Details together --}}
                <div class="ct-wizard-step" data-step="2">
                    <h5 class="mb-3">2. Link &amp; Contract Details</h5>
                    <p class="text-muted small">Pick a quotation, sale, rental, or event — amount and dates fill in below and stay editable.</p>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">Link type</label>
                            <select name="link_type" id="link_type" class="ct-field">
                                <option value="">None</option>
                                @foreach(['quotation' => 'Quotation', 'sale' => 'Sale', 'booking' => 'Rental / Booking', 'shareholder' => 'Shareholder', 'event' => 'Event'] as $lt => $ll)
                                    <option value="{{ $lt }}" @if($prefillLinkType === $lt) selected @endif>{{ $ll }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="ct-label">Linked record</label>
                            <select id="link_pick" class="ct-field">
                                <option value="">Select…</option>
                            </select>
                            <input type="hidden" name="link_id" id="link_id" value="{{ $prefillLinkId }}">
                        </div>
                    </div>
                    <div id="ct-link-summary" class="alert alert-info d-none mb-3"></div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="ct-label">Title *</label>
                            <input type="text" name="title" id="ct-title" class="ct-field" required value="{{ old('title') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">Effective date</label>
                            <input type="date" name="effective_date" id="ct-effective" class="ct-field" value="{{ old('effective_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">Start date</label>
                            <input type="date" name="start_date" id="ct-start" class="ct-field" value="{{ old('start_date') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">End date</label>
                            <input type="date" name="end_date" id="ct-end" class="ct-field" value="{{ old('end_date') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">Value (from linked record)</label>
                            <input type="number" step="0.01" name="value" id="ct-value" class="ct-field" value="{{ old('value') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="ct-label">Currency</label>
                            <input type="text" name="currency" id="ct-currency" class="ct-field" value="{{ old('currency', 'XAF') }}">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="ct-label">Jurisdiction</label>
                            <input type="text" name="jurisdiction" class="ct-field" value="{{ old('jurisdiction', $defaultJurisdiction) }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="ct-label">Purpose</label>
                            <textarea name="purpose" class="ct-field" rows="2">{{ old('purpose') }}</textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="ct-label">Payment schedule</label>
                            <textarea name="payment_schedule" class="ct-field" rows="2">{{ old('payment_schedule') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- 3 Party A --}}
                <div class="ct-wizard-step" data-step="3">
                    <h5 class="mb-3">3. Party A</h5>
                    <p class="text-muted small">Select from customers / users (including company customers). Fields stay editable after selection.</p>
                    @include('contracts.partials.person_picker', [
                        'prefix' => 'party_a',
                        'label' => 'Party A',
                        'roleDefault' => old('party_a_role', 'Party A'),
                        'requiredName' => true,
                    ])
                </div>

                {{-- 4 Party B --}}
                <div class="ct-wizard-step" data-step="4">
                    <h5 class="mb-3">4. Party B</h5>
                    @include('contracts.partials.person_picker', [
                        'prefix' => 'party_b',
                        'label' => 'Party B',
                        'roleDefault' => old('party_b_role', 'Party B'),
                        'requiredName' => true,
                    ])
                </div>

                {{-- 5 Witnesses --}}
                <div class="ct-wizard-step" data-step="5">
                    <h5 class="mb-3">5. Witnesses (optional)</h5>
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <h6>Witness A</h6>
                            @include('contracts.partials.person_picker', [
                                'prefix' => 'witness_a',
                                'label' => 'Witness A',
                                'showRole' => false,
                                'requiredName' => false,
                            ])
                        </div>
                        <div class="col-lg-6 mb-4">
                            <h6>Witness B</h6>
                            @include('contracts.partials.person_picker', [
                                'prefix' => 'witness_b',
                                'label' => 'Witness B',
                                'showRole' => false,
                                'requiredName' => false,
                            ])
                        </div>
                    </div>
                </div>

                {{-- 6 Values / engineer rates --}}
                <div class="ct-wizard-step" data-step="6">
                    <h5 class="mb-3">6. Values &amp; engineer rates</h5>
                    <p class="text-muted small">Selecting a rate category fills the daily rate into the contract placeholders.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="ct-label">Engineer / role rate category</label>
                            <select id="ct-rate-category" class="ct-field">
                                <option value="">Select rate…</option>
                                @foreach($rates ?? [] as $rate)
                                    <option value="{{ $rate->code }}" data-rate="{{ $rate->daily_rate }}" data-name="{{ $rate->name }}">
                                        {{ $rate->name }} — {{ number_format($rate->daily_rate) }} XAF/day
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="ct-label">worker.role</label>
                            <input type="text" name="values[worker.role]" id="ct-worker-role" class="ct-field" value="{{ old('values.worker.role') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="ct-label">worker.daily_rate</label>
                            <input type="text" name="values[worker.daily_rate]" id="ct-worker-rate" class="ct-field" value="{{ old('values.worker.daily_rate') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">work.estimated_days</label>
                            <input type="text" name="values[work.estimated_days]" class="ct-field" value="{{ old('values.work.estimated_days') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">worker.food_allowance</label>
                            <input type="text" name="values[worker.food_allowance]" class="ct-field" value="{{ old('values.worker.food_allowance', '1,000') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="ct-label">work.supervisor_name</label>
                            <input type="text" name="values[work.supervisor_name]" class="ct-field" value="{{ old('values.work.supervisor_name') }}">
                        </div>
                    </div>
                </div>

                {{-- 7 Content --}}
                <div class="ct-wizard-step" data-step="7">
                    <h5 class="mb-3">7. Contract content</h5>
                    <p class="text-muted small">Full template body — edit freely. Changes apply only to this contract instance.</p>
                    <textarea name="content_html" id="ct-content-html" class="ct-field" rows="20">{{ old('content_html') }}</textarea>
                </div>

                {{-- 8 Review + reminders --}}
                <div class="ct-wizard-step" data-step="8">
                    <h5 class="mb-3">8. Reminders &amp; review</h5>
                    <p class="text-muted">Optional: add specific reminder times for this contract (like Task Manager). You can add more later on the contract page.</p>
                    <div id="ct-create-reminders" class="mb-3"></div>
                    <button type="button" class="ct-btn-secondary btn-sm mb-3" id="ct-add-reminder-row"><i class="dripicons-plus"></i> Add reminder</button>
                    <div id="ct-review-box" class="border rounded p-3 mb-3 bg-light" style="white-space:pre-wrap;font-size:13px;"></div>
                    <button type="submit" class="ct-btn"><i class="dripicons-checkmark"></i> Create Draft Contract</button>
                </div>

                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <button type="button" class="ct-btn-secondary" id="wizard-prev" disabled>Previous</button>
                    <button type="button" class="ct-btn" id="wizard-next">Next</button>
                </div>
            </form>
        </div>
    </div>
</section>

@include('contracts.partials.customer_modal')
@endsection

@section('scripts')
@include('contracts.partials.wizard_scripts', [
    'mode' => 'create',
    'linkCatalog' => [
        'quotation' => collect($quotations ?? [])->map(function ($q) {
            return [
                'id' => (string) $q->id,
                'label' => ($q->reference_no ?: ('#'.$q->id)).' — '.number_format((float) ($q->grand_total ?? $q->total_price ?? 0), 0).' XAF',
            ];
        })->values(),
        'sale' => collect($sales ?? [])->map(function ($s) {
            return [
                'id' => (string) $s->id,
                'label' => ($s->reference_no ?: ('#'.$s->id)).' — '.number_format((float) ($s->grand_total ?? $s->total_price ?? 0), 0).' XAF',
            ];
        })->values(),
        'booking' => collect($bookings ?? [])->map(function ($b) {
            return [
                'id' => (string) $b->id,
                'label' => ($b->reference_no ?: ('#'.$b->id)).' — '.number_format((float) ($b->grand_total ?? 0), 0).' XAF',
            ];
        })->values(),
        'event' => collect($events ?? [])->map(function ($e) {
            return [
                'id' => (string) $e->id,
                'label' => ($e->name ?: 'Event').' ('.($e->reference_no ?: $e->id).')',
            ];
        })->values(),
        'shareholder' => collect($shareholders ?? [])->map(function ($s) {
            return [
                'id' => (string) $s->id,
                'label' => ($s->reference_number ?: ('#'.$s->id)).' — '.($s->full_name ?: 'Shareholder'),
            ];
        })->values(),
    ],
])
@endsection
