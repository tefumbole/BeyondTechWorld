@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
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
            <h5>Beyond Assistant</h5>
            <p>Enabled: {{ !empty($diag['assistant_enabled']) ? 'Yes' : 'No' }} · Provider key: {{ $diag['assistant_provider'] }}</p>
            <p>Last success: {{ optional($diag['assistant_last_ok'])->created_at ?: '—' }} · Last failure: {{ optional($diag['assistant_last_fail'])->error ?: '—' }}</p>
            <p>Average processing: {{ $diag['assistant_avg_ms'] }} ms · Handovers: {{ $diag['assistant_handovers'] }}</p>
            @foreach($diag['assistant_tool_failures'] as $tf)
                <div class="small">Tool {{ $tf->tools_executed }} · {{ $tf->tool_status }}</div>
            @endforeach
        </div>
        <div class="wa-card">
            <h5>Rental quotations</h5>
            <p>Awaiting approval: {{ $diag['rental_pending_approval'] ?? 0 }}</p>
            <p>Last availability check: {{ $diag['rental_last_check'] ?? '—' }}</p>
            <p>Availability failures: {{ $diag['rental_availability_failures'] ?? 0 }} · Pricing failures: {{ $diag['rental_pricing_failures'] ?? 0 }}</p>
            <p>PDF or send failures: {{ $diag['rental_send_failures'] ?? 0 }}</p>
        </div>
        <div class="wa-card">
            <h5>Documents and verification</h5>
            @php $docs = $diag['documents'] ?? []; @endphp
            <p>OTP service: {{ $docs['otp_service'] ?? '—' }}</p>
            <p>Last code generated: {{ $docs['last_otp_generated'] ?? '—' }}</p>
            <p>Last code verified: {{ $docs['last_otp_verified'] ?? '—' }}</p>
            <p>Code failures: {{ $docs['otp_failures'] ?? 0 }} · Rate limited: {{ $docs['rate_limited'] ?? 0 }}</p>
            <p>Registry documents: {{ $docs['registry_available'] ?? 0 }}</p>
            <p>PDF failures: {{ $docs['generation_failures'] ?? 0 }} · Send failures: {{ $docs['send_failures'] ?? 0 }}</p>
            <p>Unauthorized attempts: {{ $docs['unauthorized'] ?? 0 }}</p>
        </div>
        <div class="wa-card">
            <h5>Attendance</h5>
            @php $att = $diag['attendance'] ?? []; @endphp
            <p>Last check-in: {{ $att['last_check_in'] ?? '—' }}</p>
            <p>Last check-out: {{ $att['last_check_out'] ?? '—' }}</p>
            <p>Last failure: {{ $att['last_fail'] ?? '—' }}</p>
            <p>Open sessions: {{ $att['open_sessions'] ?? 0 }} · Duplicates prevented: {{ $att['duplicates_prevented'] ?? 0 }}</p>
        </div>
        <div class="wa-card">
            <h5>Internship assistant</h5>
            @php $intern = $diag['internship'] ?? []; @endphp
            <p>Last task lookup: {{ $intern['last_task'] ?? '—' }}</p>
            <p>Last media download: {{ $intern['last_media'] ?? '—' }}</p>
            <p>Last successful submission: {{ $intern['last_submit'] ?? '—' }}</p>
            <p>Last failed submission: {{ $intern['last_fail'] ?? '—' }}</p>
            <p>Pending media jobs: {{ $intern['pending_media'] ?? 0 }} · Duplicate submissions prevented: {{ $intern['duplicates_prevented'] ?? 0 }}</p>
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
