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
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">New leads</div><p class="wa-stat">{{ $stats['new_leads'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Unassigned leads</div><p class="wa-stat">{{ $stats['unassigned_leads'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Follow-ups due</div><p class="wa-stat">{{ $stats['followups_due'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting staff</div><p class="wa-stat">{{ $stats['awaiting_staff'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Rental enquiries</div><p class="wa-stat">{{ $stats['rental_new'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Quotes awaiting approval</div><p class="wa-stat">{{ $stats['rental_awaiting_approval'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Quotations sent</div><p class="wa-stat">{{ $stats['rental_sent'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Revision requests</div><p class="wa-stat">{{ $stats['rental_revisions'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">WhatsApp submissions</div><p class="wa-stat">{{ $stats['intern_submissions'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting internship review</div><p class="wa-stat">{{ $stats['intern_awaiting_review'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Internship corrections</div><p class="wa-stat">{{ $stats['intern_corrections'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Submission import failures</div><p class="wa-stat">{{ $stats['intern_media_failures'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Checked in now</div><p class="wa-stat">{{ $stats['checked_in_now'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Field staff on site</div><p class="wa-stat">{{ $stats['field_on_site'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Missing check-out</div><p class="wa-stat">{{ $stats['missing_checkout'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Pending corrections</div><p class="wa-stat">{{ $stats['pending_corrections'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Location review</div><p class="wa-stat">{{ $stats['location_review'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Document requests today</div><p class="wa-stat">{{ $stats['document_requests_today'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting code</div><p class="wa-stat">{{ $stats['document_awaiting_otp'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Documents verified</div><p class="wa-stat">{{ $stats['document_verified'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Documents sent</div><p class="wa-stat">{{ $stats['document_sent'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Failed document sends</div><p class="wa-stat">{{ $stats['document_failed'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Denied document requests</div><p class="wa-stat">{{ $stats['document_denied'] ?? 0 }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Expired codes</div><p class="wa-stat">{{ $stats['document_expired'] ?? 0 }}</p></div></div>
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
                    <h5>Needs attention</h5>
                    @forelse($stats['needs_attention'] as $c)
                        <div class="mb-2"><a href="{{ route('whatsapp.conversation', $c->id) }}">{{ optional($c->contact)->displayName() }}</a>
                            <div class="small text-muted">Waiting {{ $c->waitingMinutes() }} min · {{ $c->last_message }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No unanswered conversations.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Overdue follow-ups</h5>
                    @forelse($stats['overdue_followups'] as $lead)
                        <div class="mb-2"><a href="{{ route('whatsapp.leads.show', $lead->id) }}">{{ $lead->name ?: $lead->normalized_phone }}</a>
                            <div class="small text-muted">Due {{ $lead->follow_up_at }} · {{ optional($lead->assignee)->name ?: 'Unassigned' }}</div></div>
                    @empty
                        <p class="text-muted mb-0">No overdue follow-ups.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="wa-card">
                    <h5>Staff workload</h5>
                    @forelse($stats['staff_workload'] as $row)
                        <div class="mb-2">{{ optional($row['user'])->name ?: 'Staff' }}
                            <div class="small text-muted">{{ $row['open'] }} open · {{ $row['waiting'] }} waiting · {{ $row['leads'] }} leads</div></div>
                    @empty
                        <p class="text-muted mb-0">No assigned conversations.</p>
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
