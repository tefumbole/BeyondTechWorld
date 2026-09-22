@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title"><i class="fa fa-whatsapp"></i> WhatsApp Command Center</h1>
        <p class="wa-sub">Operational health from recorded Hub events and WaSender session status.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        @include('whatsapp_hub.partials.range')

        <div class="row">
            <div class="col-md-3">
                <div class="wa-card">
                    <div class="wa-stat-label">Session</div>
                    @php
                        $st = $session['status'] ?? 'UNKNOWN';
                        $cls = !empty($session['connected']) ? 'wa-badge-ok' : (in_array($st, ['CONNECTING']) ? 'wa-badge-warn' : 'wa-badge-bad');
                    @endphp
                    <p class="wa-stat"><span class="wa-badge {{ $cls }}">{{ $st }}</span></p>
                    <div class="small text-muted">{{ $session['session_name'] ?: ($session['configured'] ? 'Configured' : 'Not configured') }}</div>
                </div>
            </div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Messages</div><p class="wa-stat">{{ $stats['messages_today'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Incoming</div><p class="wa-stat">{{ $stats['incoming_today'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Outgoing</div><p class="wa-stat">{{ $stats['outgoing_today'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Unread conversations</div><p class="wa-stat">{{ $stats['unread'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Failed messages</div><p class="wa-stat">{{ $stats['failed'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Calls</div><p class="wa-stat">{{ $stats['calls'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Webhook events</div><p class="wa-stat">{{ $stats['webhooks'] }}</p><div class="small text-muted">{{ $stats['webhooks_processed'] }} processed · {{ $stats['webhooks_failed'] }} failed</div></div></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Recent conversations</h5>
                    @forelse($stats['recent_conversations'] as $c)
                        <div class="mb-2"><a href="{{ route('whatsapp.conversation', $c->id) }}">{{ optional($c->contact)->displayName() }}</a>
                            <div class="small text-muted">{{ $c->last_message }} · {{ $c->last_activity_at }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No conversations yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Recent failed messages</h5>
                    @forelse($stats['recent_failed'] as $m)
                        <div class="mb-2">{{ optional($m->contact)->displayName() }} — {{ \Illuminate\Support\Str::limit($m->body, 80) }}
                            <div class="small text-danger">{{ $m->error }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No failed messages in Hub.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Recent calls</h5>
                    @forelse($stats['recent_calls'] as $call)
                        <div class="mb-2">{{ $call->caller_phone }} · {{ $call->call_type }} · {{ $call->status }}
                            <div class="small text-muted">{{ $call->called_at }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No calls recorded.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Recent webhook events</h5>
                    @forelse($stats['recent_events'] as $ev)
                        <div class="mb-2">{{ $ev->event_type }} · {{ $ev->status }}
                            <div class="small text-muted">{{ $ev->received_at }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No webhook events yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
