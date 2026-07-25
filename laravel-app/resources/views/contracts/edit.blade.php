@extends('layout.main')

@section('content')
@php
    $partyA = optional($contract->partyA)->snapshot() ?? [];
    $partyB = optional($contract->partyB)->snapshot() ?? [];
    $witnessA = [];
    $witnessB = [];
    foreach ($contract->witnesses ?? [] as $w) {
        $s = json_decode($w->getAttributes()['identity_snapshot_json'] ?? '{}', true) ?: [];
        if ($w->for_party === 'A') $witnessA = $s + ['subject_type' => $w->person_type, 'subject_id' => $w->person_id];
        else $witnessB = $s + ['subject_type' => $w->person_type, 'subject_id' => $w->person_id];
    }
    $contentHtml = old('content_html', optional($contract->currentRevision)->content_html ?? '');
    $resolved = optional($contract->currentRevision)->resolved_data_json ?? [];
    $workerRole = old('values.worker.role', data_get($resolved, 'worker.role'));
    $workerRate = old('values.worker.daily_rate', data_get($resolved, 'worker.daily_rate'));
    $estDays = old('values.work.estimated_days', data_get($resolved, 'work.estimated_days'));
@endphp
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="mb-4">
            <h1 class="ct-title">Edit Contract</h1>
            <p class="ct-subtitle">{{ $contract->number }} · {{ $contract->statusLabel() }} — changes save to this instance only (template unchanged).</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ct-card">
            <form method="POST" action="{{ route('contracts.update', $contract->id) }}" id="contract-edit-form">
                @csrf

                <h5 class="mb-3">Link &amp; details</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Link type</label>
                        <select name="link_type" id="link_type" class="ct-field">
                            <option value="">None</option>
                            @foreach(['quotation' => 'Quotation', 'sale' => 'Sale', 'booking' => 'Rental / Booking', 'shareholder' => 'Shareholder', 'event' => 'Event'] as $lt => $ll)
                                <option value="{{ $lt }}" @if(old('link_type', $contract->primary_link_type) === $lt) selected @endif>{{ $ll }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="ct-label">Linked record</label>
                        <select id="link_pick" class="ct-field"><option value="">Select…</option></select>
                        <input type="hidden" name="link_id" id="link_id" value="{{ old('link_id', $contract->primary_link_id) }}">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="ct-label">Title *</label>
                        <input type="text" name="title" id="ct-title" class="ct-field" required value="{{ old('title', $contract->title) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Effective date</label>
                        <input type="date" name="effective_date" id="ct-effective" class="ct-field"
                               value="{{ old('effective_date', $contract->effective_date ? \Carbon\Carbon::parse($contract->effective_date)->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Start date</label>
                        <input type="date" name="start_date" id="ct-start" class="ct-field"
                               value="{{ old('start_date', $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">End date</label>
                        <input type="date" name="end_date" id="ct-end" class="ct-field"
                               value="{{ old('end_date', $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Value</label>
                        <input type="number" step="0.01" name="value" id="ct-value" class="ct-field" value="{{ old('value', $contract->value) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="ct-label">Currency</label>
                        <input type="text" name="currency" id="ct-currency" class="ct-field" value="{{ old('currency', $contract->currency) }}">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="ct-label">Jurisdiction</label>
                        <input type="text" name="jurisdiction" class="ct-field" value="{{ old('jurisdiction', $contract->jurisdiction) }}">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label">Purpose</label>
                        <textarea name="purpose" class="ct-field" rows="2">{{ old('purpose', $contract->purpose) }}</textarea>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label">Payment schedule</label>
                        <textarea name="payment_schedule" class="ct-field" rows="2">{{ old('payment_schedule', $contract->payment_schedule) }}</textarea>
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Party A</h5>
                @include('contracts.partials.person_picker', [
                    'prefix' => 'party_a',
                    'roleDefault' => old('party_a_role', optional($contract->partyA)->role_label),
                    'requiredName' => true,
                ])

                <hr>
                <h5 class="mb-3">Party B</h5>
                @include('contracts.partials.person_picker', [
                    'prefix' => 'party_b',
                    'roleDefault' => old('party_b_role', optional($contract->partyB)->role_label),
                    'requiredName' => true,
                ])

                <hr>
                <h5 class="mb-3">Witnesses</h5>
                <div class="row">
                    <div class="col-lg-6">
                        <h6>Witness A</h6>
                        @include('contracts.partials.person_picker', ['prefix' => 'witness_a', 'showRole' => false])
                    </div>
                    <div class="col-lg-6">
                        <h6>Witness B</h6>
                        @include('contracts.partials.person_picker', ['prefix' => 'witness_b', 'showRole' => false])
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Values &amp; engineer rates</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">Rate category</label>
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
                        <input type="text" name="values[worker.role]" id="ct-worker-role" class="ct-field" value="{{ $workerRole }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="ct-label">worker.daily_rate</label>
                        <input type="text" name="values[worker.daily_rate]" id="ct-worker-rate" class="ct-field" value="{{ $workerRate }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">work.estimated_days</label>
                        <input type="text" name="values[work.estimated_days]" class="ct-field" value="{{ $estDays }}">
                    </div>
                </div>

                <hr>
                <h5 class="mb-3">Contract content</h5>
                <textarea name="content_html" id="ct-content-html" class="ct-field" rows="18">{{ $contentHtml }}</textarea>

                <div class="mt-4 d-flex" style="gap:8px;">
                    <button type="submit" class="ct-btn"><i class="dripicons-checkmark"></i> Save changes</button>
                    <a href="{{ route('contracts.show', $contract->id) }}" class="ct-btn-secondary">Cancel</a>
                    <a href="{{ route('contracts.preview', $contract->id) }}" target="_blank" class="ct-btn-secondary">Preview PDF</a>
                </div>
            </form>
        </div>
    </div>
</section>

@include('contracts.partials.customer_modal')
@endsection

@section('scripts')
<script>
// Prefill party fields from existing snapshots before picker JS binds
(function () {
    var partyA = @json([
        'name' => old('party_a.name', $partyA['name'] ?? ''),
        'email' => old('party_a.email', $partyA['email'] ?? ''),
        'phone' => old('party_a.phone', $partyA['phone'] ?? ''),
        'address' => old('party_a.address', $partyA['address'] ?? ''),
        'organization' => old('party_a.organization', $partyA['organization'] ?? ''),
        'subject_type' => old('party_a.subject_type', optional($contract->partyA)->subject_type),
        'subject_id' => old('party_a.subject_id', optional($contract->partyA)->subject_id),
    ]);
    var partyB = @json([
        'name' => old('party_b.name', $partyB['name'] ?? ''),
        'email' => old('party_b.email', $partyB['email'] ?? ''),
        'phone' => old('party_b.phone', $partyB['phone'] ?? ''),
        'address' => old('party_b.address', $partyB['address'] ?? ''),
        'organization' => old('party_b.organization', $partyB['organization'] ?? ''),
        'subject_type' => old('party_b.subject_type', optional($contract->partyB)->subject_type),
        'subject_id' => old('party_b.subject_id', optional($contract->partyB)->subject_id),
    ]);
    var witnessA = @json([
        'name' => old('witness_a.name', $witnessA['name'] ?? ''),
        'email' => old('witness_a.email', $witnessA['email'] ?? ''),
        'phone' => old('witness_a.phone', $witnessA['phone'] ?? ''),
        'address' => old('witness_a.address', $witnessA['address'] ?? ''),
        'subject_type' => old('witness_a.subject_type', $witnessA['subject_type'] ?? ''),
        'subject_id' => old('witness_a.subject_id', $witnessA['subject_id'] ?? ''),
    ]);
    var witnessB = @json([
        'name' => old('witness_b.name', $witnessB['name'] ?? ''),
        'email' => old('witness_b.email', $witnessB['email'] ?? ''),
        'phone' => old('witness_b.phone', $witnessB['phone'] ?? ''),
        'address' => old('witness_b.address', $witnessB['address'] ?? ''),
        'subject_type' => old('witness_b.subject_type', $witnessB['subject_type'] ?? ''),
        'subject_id' => old('witness_b.subject_id', $witnessB['subject_id'] ?? ''),
    ]);
    window.__ctPrefill = { party_a: partyA, party_b: partyB, witness_a: witnessA, witness_b: witnessB };
})();
</script>
@include('contracts.partials.wizard_scripts', [
    'mode' => 'edit',
    'linkCatalog' => [
        'quotation' => collect($quotations ?? [])->map(function ($q) {
            return ['id' => (string) $q->id, 'label' => ($q->reference_no ?: ('#'.$q->id)).' — '.number_format((float) ($q->grand_total ?? $q->total_price ?? 0), 0).' XAF'];
        })->values(),
        'sale' => collect($sales ?? [])->map(function ($s) {
            return ['id' => (string) $s->id, 'label' => ($s->reference_no ?: ('#'.$s->id)).' — '.number_format((float) ($s->grand_total ?? $s->total_price ?? 0), 0).' XAF'];
        })->values(),
        'booking' => collect($bookings ?? [])->map(function ($b) {
            return ['id' => (string) $b->id, 'label' => ($b->reference_no ?: ('#'.$b->id)).' — '.number_format((float) ($b->grand_total ?? 0), 0).' XAF'];
        })->values(),
        'event' => collect($events ?? [])->map(function ($e) {
            return ['id' => (string) $e->id, 'label' => ($e->name ?: 'Event').' ('.($e->reference_no ?: $e->id).')'];
        })->values(),
        'shareholder' => collect($shareholders ?? [])->map(function ($s) {
            return ['id' => (string) $s->id, 'label' => ($s->reference_number ?: ('#'.$s->id)).' — '.($s->full_name ?: 'Shareholder')];
        })->values(),
    ],
])
<script>
(function () {
    if (!window.__ctPrefill) return;
    ['party_a','party_b','witness_a','witness_b'].forEach(function (prefix) {
        var p = window.__ctPrefill[prefix];
        if (!p || !p.name) return;
        var box = document.querySelector('.ct-person-picker[data-prefix="'+prefix+'"]');
        if (!box) return;
        if (box.querySelector('.ct-pp-name')) box.querySelector('.ct-pp-name').value = p.name || '';
        if (box.querySelector('.ct-pp-email')) box.querySelector('.ct-pp-email').value = p.email || '';
        if (box.querySelector('.ct-pp-phone')) box.querySelector('.ct-pp-phone').value = p.phone || '';
        if (box.querySelector('.ct-pp-address')) box.querySelector('.ct-pp-address').value = p.address || '';
        if (box.querySelector('.ct-pp-org')) box.querySelector('.ct-pp-org').value = p.organization || '';
        if (box.querySelector('.ct-pp-stype')) box.querySelector('.ct-pp-stype').value = p.subject_type || '';
        if (box.querySelector('.ct-pp-sid')) box.querySelector('.ct-pp-sid').value = p.subject_id || '';
    });
})();
</script>
@endsection
