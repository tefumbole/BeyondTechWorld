@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">WhatsApp Diagnostics</h1>
        <p class="wa-sub">Operational health. Credentials are never displayed.</p>

        <div class="row">
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">WhatsApp connection</div><p class="wa-stat"><span class="wa-badge {{ !empty($session['connected']) ? 'wa-badge-ok' : 'wa-badge-bad' }}">{{ $session['status'] }}</span></p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Webhook secret</div><p class="wa-stat">{{ $diag['webhook_secret'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Signature verification</div><p class="wa-stat">{{ $diag['signature_configured'] ? 'Ready' : 'Not configured' }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Wasender API key</div><p class="wa-stat">{{ $diag['wasender_key'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Wasender session</div><p class="wa-stat">{{ $diag['wasender_session'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Queue</div><p class="wa-stat">{{ $diag['queue_connection'] }}</p><div class="small text-muted">{{ $diag['pending_jobs'] }} pending jobs · {{ $diag['failed_jobs'] }} failed jobs</div></div></div>
        </div>

        <div class="wa-card">
            <h5>Today</h5>
            <p>Webhook events: {{ $diag['events_today'] }} · Processed: {{ $diag['processed_today'] }} · Failed: {{ $diag['failed_today'] }} · Pending: {{ $diag['pending_today'] }}</p>
        </div>

        <div class="wa-card">
            <h5>Last events</h5>
            <ul class="mb-0">
                <li>Last webhook: {{ optional($diag['last_webhook'])->event_type ?: '—' }} {{ optional($diag['last_webhook'])->received_at }}</li>
                <li>Last incoming: {{ optional($diag['last_incoming'])->created_at ?: '—' }}</li>
                <li>Last outgoing: {{ optional($diag['last_outgoing'])->created_at ?: '—' }}</li>
                <li>Last delivery: {{ optional($diag['last_delivered'])->delivered_at ?: '—' }}</li>
                <li>Last read: {{ optional($diag['last_read'])->read_at ?: '—' }}</li>
                <li>Last call: {{ optional($diag['last_call'])->called_at ?: '—' }}</li>
                <li>Last successful processing: {{ optional($diag['last_processed'])->processed_at ?: '—' }}</li>
                <li>Last failed processing: {{ optional($diag['last_failed'])->processing_error ?: '—' }}</li>
            </ul>
        </div>

        <div class="wa-card">
            <h5>Recent processing failures</h5>
            @forelse($diag['recent_failures'] as $fail)
                <div class="mb-2">#{{ $fail->id }} {{ $fail->event_type }} — {{ $fail->processing_error }}</div>
            @empty
                <p class="text-muted mb-0">None.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
