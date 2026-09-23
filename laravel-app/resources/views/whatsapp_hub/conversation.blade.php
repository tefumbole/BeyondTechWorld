@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p>
            @if($conversation->mode === 'AI')
                <a href="{{ route('whatsapp.assistant', ['tab' => 'conversations']) }}">&larr; AI conversations</a>
            @else
                <a href="{{ route('whatsapp.conversations', ['mode' => 'HUMAN']) }}">&larr; Human conversations</a>
            @endif
        </p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="wa-inbox">
            <div class="wa-inbox-side wa-card">
                <div class="small text-muted">Conversation</div>
                <strong>{{ optional($conversation->contact)->displayName() }}</strong>
                <div>{{ optional($conversation->contact)->display_phone }}</div>
                <div class="small mt-2">Mode: {{ $conversation->mode }}</div>
                <div class="small">Status: {{ $conversation->status }}</div>
                <div class="small">Assigned: {{ optional($conversation->assignee)->name ?: 'Unassigned' }}</div>
                @if(!empty($rentalRequest))
                    <div class="small mt-2">
                        <div>Event: {{ $rentalRequest->event_type ?: '—' }}</div>
                        <div>Date: {{ $rentalRequest->event_date ? $rentalRequest->event_date->toDateString() : ($rentalRequest->event_date_text ?: '—') }}</div>
                        <div>Location: {{ $rentalRequest->location ?: '—' }}</div>
                        <div>Guests: {{ $rentalRequest->attendance ?: '—' }}</div>
                        <div>Status: {{ $rentalRequest->status }}</div>
                        @if($rentalRequest->proposal_total !== null)
                            <div>Proposal: {{ number_format($rentalRequest->proposal_total, 0) }}</div>
                        @endif
                        @if($rentalRequest->quotation_id)
                            <a href="{{ route('quotations.edit', $rentalRequest->quotation_id) }}">Edit quotation</a>
                        @endif
                    </div>
                @endif
                @if(!empty($attendancePanel))
                    <div class="small mt-2">
                        <div class="text-muted">Attendance</div>
                        <div>{{ $attendancePanel['name'] }} · {{ $attendancePanel['state'] }}</div>
                        @if($attendancePanel['started'])
                            <div>Since {{ $attendancePanel['started'] }} · {{ $attendancePanel['duration'] }}</div>
                        @endif
                        @if($attendancePanel['location_status'])
                            <div>Location: {{ $attendancePanel['location_status'] }}</div>
                        @endif
                        <div>Pending corrections: {{ $attendancePanel['pending_corrections'] }}</div>
                        <a href="{{ url('admin/attendance') }}">View attendance</a>
                    </div>
                        @endif
                        @if(!empty($documentPanel))
                    <div class="small mt-2">
                        <div class="text-muted">Document request</div>
                        <div>{{ $documentPanel['identity'] ?: 'Identity pending' }} · {{ $documentPanel['document_type'] ?: '—' }}</div>
                        <div>Status: {{ $documentPanel['status'] ?: '—' }} · Verification: {{ $documentPanel['verification'] }}</div>
                        @if($documentPanel['verified_until'])
                            <div>Verified until {{ $documentPanel['verified_until'] }}</div>
                        @endif
                    </div>
                @endif
                @if(!empty($internshipPanel))
                    <div class="small mt-2">
                        <div class="text-muted">Intern</div>
                        <div>{{ $internshipPanel['name'] }}</div>
                        <div>{{ $internshipPanel['program'] ?: 'Programme' }} · {{ $internshipPanel['enrolment_status'] }}</div>
                        <div>Day {{ $internshipPanel['day'] ?: '—' }} · {{ $internshipPanel['task'] ?: 'No released task' }}</div>
                        <div>Task {{ $internshipPanel['task_status'] ?: '—' }} · Submission {{ $internshipPanel['submission_status'] ?: 'none' }}</div>
                        <div>Supervisor: {{ $internshipPanel['supervisor'] ?: '—' }}</div>
                        <div>Progress: {{ $internshipPanel['completed'] }} / {{ $internshipPanel['planned'] ?: '—' }}</div>
                        @if($internshipPanel['assignment_id'])
                            <a href="{{ route('internship.student.task', $internshipPanel['assignment_id']) }}">View task</a>
                        @endif
                        @if($internshipPanel['submission_id'])
                            <a href="{{ route('internship.supervisor.show', $internshipPanel['submission_id']) }}">View submission</a>
                        @endif
                        <a href="{{ route('internship.enrol.edit', $internshipPanel['enrolment_id']) }}">View enrolment</a>
                    </div>
                @endif
                @if(!empty($rentalDraft))
                    <div class="small mt-1">AI Generated quotation: {{ $rentalDraft }}</div>
                @endif
                @if($conversation->isAwaitingStaff())
                    @php
                        $wait = $conversation->waitingMinutes();
                        $slaClass = 'wa-badge-ok';
                        if ($wait >= ($sla['critical'] ?? 240)) { $slaClass = 'wa-badge-bad'; }
                        elseif ($wait >= ($sla['warning'] ?? 60)) { $slaClass = 'wa-badge-warn'; }
                    @endphp
                    <div class="small mt-2">Awaiting response <span class="wa-badge {{ $slaClass }}">{{ $wait }} min</span></div>
                @endif
                @if(!empty($events) && $events->count())
                    <hr>
                    <div class="small text-muted">Recent events</div>
                    @foreach($events as $ev)
                        <div class="small mb-1">{{ $ev->created_at }} · {{ $ev->body }}</div>
                    @endforeach
                @endif
                <hr>
                <form method="post" action="{{ route('whatsapp.conversation.enable_ai', $conversation->id) }}" class="mb-1">@csrf<button class="btn btn-sm btn-success btn-block" type="submit">Enable AI</button></form>
                <form method="post" action="{{ route('whatsapp.conversation.takeover', $conversation->id) }}" class="mb-1">@csrf<button class="btn btn-sm btn-primary btn-block" type="submit">Take Over</button></form>
                <form method="post" action="{{ route('whatsapp.conversation.release', $conversation->id) }}" class="mb-1">@csrf<button class="btn btn-sm btn-outline-secondary btn-block" type="submit">Release</button></form>
                <form method="post" action="{{ route('whatsapp.conversation.pause', $conversation->id) }}" class="mb-1">@csrf<button class="btn btn-sm btn-outline-warning btn-block" type="submit">Pause</button></form>
                @if($conversation->status === 'CLOSED')
                    <form method="post" action="{{ route('whatsapp.conversation.reopen', $conversation->id) }}">@csrf<button class="btn btn-sm btn-outline-success btn-block" type="submit">Reopen</button></form>
                @else
                    <form method="post" action="{{ route('whatsapp.conversation.close', $conversation->id) }}">@csrf<button class="btn btn-sm btn-outline-dark btn-block" type="submit">Close</button></form>
                @endif
                <form method="post" action="{{ route('whatsapp.conversation.assign', $conversation->id) }}" class="mt-2">
                    @csrf
                    <select name="assigned_user_id" class="form-control form-control-sm mb-1">
                        <option value="">Unassigned</option>
                        @foreach($staff as $u)
                            <option value="{{ $u->id }}" {{ (int) $conversation->assigned_user_id === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-secondary" type="submit">Assign to Staff</button>
                    <button class="btn btn-sm btn-outline-primary" type="submit" name="me" value="1">Assign to Me</button>
                </form>
            </div>

            <div>
                <div class="wa-thread" id="wa-thread">
                    @php
                        $thread = $messages->map(function ($m) { $m->_kind = 'msg'; return $m; })
                            ->concat($notes->map(function ($n) { $n->_kind = 'note'; return $n; }))
                            ->sortBy('created_at');
                    @endphp
                    @foreach($thread as $item)
                        @if($item->_kind === 'note')
                            <div class="wa-bubble wa-note">
                                <div class="small text-muted">INTERNAL NOTE · {{ optional($item->author)->name }} · {{ $item->created_at }}</div>
                                <div>{{ $item->body }}</div>
                            </div>
                        @else
                            <div class="wa-bubble {{ $item->sender_type === 'ASSISTANT' ? 'wa-ai' : ($item->direction === 'OUTGOING' ? 'wa-out' : 'wa-in') }}">
                                @if($item->sender_type === 'ASSISTANT')<div class="small text-muted">Beyond Assistant</div>@endif
                                @if($item->sender_type === 'STAFF')<div class="small text-muted">Staff</div>@endif
                                <div>{{ $item->body ?: '['.$item->type.']' }}</div>
                                <div class="wa-meta">
                                    {{ $item->created_at }}
                                    @if($item->direction === 'OUTGOING')
                                        @php $tick = $item->ticks(); @endphp
                                        <span class="wa-ticks-{{ $tick }}">
                                            @if($tick === 'failed') failed
                                            @elseif($tick === 'read' || $tick === 'played') ✓✓ Read
                                            @elseif($tick === 'delivered') ✓✓ Delivered
                                            @elseif($tick === 'sent') ✓ Sent
                                            @else queued
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if($canReply)
                    <form method="post" action="{{ route('whatsapp.conversation.reply', $conversation->id) }}" class="mt-3">
                        @csrf
                        <textarea name="body" class="form-control mb-2" rows="3" required placeholder="WhatsApp reply…">{{ session('suggested_reply') }}</textarea>
                        <button class="btn btn-primary" type="submit">Send WhatsApp</button>
                    </form>
                    <form method="post" action="{{ route('whatsapp.conversation.suggest', $conversation->id) }}" class="mt-1">
                        @csrf
                        <button class="btn btn-outline-info btn-sm" type="submit">Suggest Reply</button>
                        <span class="small text-muted">Draft only — not sent until you click Send WhatsApp.</span>
                    </form>
                @endif
                <form method="post" action="{{ route('whatsapp.conversation.note', $conversation->id) }}" class="mt-2">
                    @csrf
                    <textarea name="body" class="form-control mb-2" rows="2" required placeholder="Internal note (never sent to WhatsApp)"></textarea>
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Save internal note</button>
                </form>
                @if(!$lead)
                    <form method="post" action="{{ route('whatsapp.conversation.lead', $conversation->id) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-success" type="submit">Create Lead</button>
                    </form>
                @else
                    <a class="btn btn-outline-success mt-2" href="{{ route('whatsapp.leads.show', $lead->id) }}">Open lead</a>
                @endif
            </div>

            <div class="wa-inbox-contact wa-card">
                <h5>Contact</h5>
                <div><strong>{{ optional($conversation->contact)->wa_name ?: '—' }}</strong></div>
                <div>{{ optional($conversation->contact)->display_phone }}</div>
                <hr>
                <div class="small text-muted">Linked ERP identities</div>
                @forelse(optional($conversation->contact)->links ?? [] as $link)
                    <div class="mb-1"><span class="wa-badge wa-badge-info">{{ ucfirst($link->role) }}</span>
                        #{{ $link->linkable_id }}
                        @if($link->linkable)
                            {{ $link->linkable->name ?? $link->linkable->full_name ?? '' }}
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Unknown number — no ERP identity yet.</p>
                @endforelse

                @if(!empty($context['customer']))
                    <hr><div class="small text-muted">Customer</div>
                    <div>{{ $context['customer']['name'] }} · #{{ $context['customer']['id'] }}</div>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('customers/'.$context['customer']['id'].'/edit') }}">View Customer</a>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('bookings?customer_id='.$context['customer']['id']) }}">View Rentals</a>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('quotations?customer_id='.$context['customer']['id']) }}">View Quotations</a>
                @endif
                @if(!empty($context['intern']))
                    <hr><div class="small text-muted">Intern</div>
                    <div>{{ $context['intern']['name'] }}</div>
                    <div class="small">Status: {{ $context['intern']['status'] ?: '—' }}</div>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('admin/internship/students/'.$context['intern']['user_id']) }}">View Profile</a>
                @endif
                @if(!empty($context['employee']))
                    <hr><div class="small text-muted">Employee</div>
                    <div>{{ $context['employee']['name'] }} · #{{ $context['employee']['id'] }}</div>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('employees/'.$context['employee']['id'].'/edit') }}">View Employee</a>
                @endif
                @if($lead)
                    <hr><div class="small text-muted">Lead</div>
                    <div>{{ $lead->categoryLabel() }} · {{ $lead->statusLabel() }}</div>
                    <div class="small">Follow-up: {{ $lead->follow_up_at ?: '—' }}</div>
                    <a class="btn btn-sm btn-link p-0" href="{{ route('whatsapp.leads.show', $lead->id) }}">Open lead</a>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('quotations/create?phone='.$conversation->contact->normalized_phone.'&note='.urlencode($lead->latest_enquiry)) }}">Create Quotation</a>
                @else
                    <form method="post" action="{{ route('whatsapp.conversation.lead', $conversation->id) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-sm btn-success btn-block" type="submit">Create Lead</button>
                    </form>
                    <a class="btn btn-sm btn-link p-0" href="{{ url('quotations/create?phone='.optional($conversation->contact)->normalized_phone) }}">Create Quotation</a>
                @endif
                @if(!empty($documents))
                    <hr>
                    <form method="post" action="{{ route('whatsapp.conversation.document', $conversation->id) }}">
                        @csrf
                        <select name="path" class="form-control form-control-sm mb-1">
                            @foreach($documents as $doc)
                                <option value="{{ $doc['path'] }}">{{ $doc['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="name" value="">
                        <button class="btn btn-sm btn-outline-success" type="submit">Send Existing Document</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</section>
<script>
(function () {
    var lastId = {{ (int) ($messages->max('id') ?: 0) }};
    setInterval(function () {
        if (document.hidden || !window.jQuery) return;
        jQuery.getJSON(window.location.pathname + '?poll=1', function (data) {
            var max = 0;
            (data.messages || []).forEach(function (m) { if (m.id > max) max = m.id; });
            if (max > lastId) { window.location.reload(); }
        });
    }, 12000);
})();
</script>
@endsection
