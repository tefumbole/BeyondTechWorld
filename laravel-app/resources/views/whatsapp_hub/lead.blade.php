@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p><a href="{{ route('whatsapp.leads') }}">&larr; All leads</a></p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="row">
            <div class="col-md-8">
                <div class="wa-card">
                    <h4>{{ $lead->name ?: 'Unknown lead' }}</h4>
                    <p class="mb-1">{{ $lead->normalized_phone }} @if($lead->company)· {{ $lead->company }}@endif</p>
                    <p class="mb-1"><span class="wa-badge wa-badge-info">{{ $lead->categoryLabel() }}</span>
                        <span class="wa-badge">{{ $lead->statusLabel() }}</span>
                        <span class="wa-badge">{{ $lead->priority }}</span>
                    </p>
                    <p class="small text-muted mb-0">Source: {{ $lead->source }} · Assigned: {{ optional($lead->assignee)->name ?: 'Unassigned' }}</p>
                    @if($lead->conversation_id)
                        <p class="mt-2 mb-0"><a href="{{ route('whatsapp.conversation', $lead->conversation_id) }}">Open WhatsApp conversation</a></p>
                    @endif
                    @if($lead->converted_customer_id)
                        <p class="mt-2 mb-0">Converted customer #{{ $lead->converted_customer_id }}
                            <a href="{{ url('customers/'.$lead->converted_customer_id.'/edit') }}">View customer</a>
                        </p>
                    @endif
                </div>

                <div class="wa-card">
                    <h5>Enquiry</h5>
                    <div class="small text-muted">First</div>
                    <p>{{ $lead->first_enquiry ?: '—' }}</p>
                    <div class="small text-muted">Latest</div>
                    <p class="mb-0">{{ $lead->latest_enquiry ?: '—' }}</p>
                </div>

                <div class="wa-card">
                    <h5>Activity</h5>
                    @forelse($lead->activities as $act)
                        <div class="mb-2">
                            <div class="small text-muted">{{ $act->created_at }} · {{ $act->type }} · {{ optional($act->actor)->name }}</div>
                            <div>{{ $act->body }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No activity yet.</p>
                    @endforelse
                </div>

                <div class="wa-card">
                    <h5>Internal notes</h5>
                    <p class="small text-muted">Never sent to WhatsApp.</p>
                    @foreach($lead->notes as $note)
                        <div class="mb-2" style="background:#fff8e1;border:1px dashed #f0ad4e;padding:8px;">
                            <div class="small text-muted">{{ optional($note->author)->name }} · {{ $note->created_at }}</div>
                            <div>{{ $note->body }}</div>
                        </div>
                    @endforeach
                    <form method="post" action="{{ route('whatsapp.leads.note', $lead->id) }}">
                        @csrf
                        <textarea name="body" class="form-control mb-2" rows="2" required placeholder="Internal note"></textarea>
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Save note</button>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="wa-card">
                    <h5>Assign</h5>
                    <form method="post" action="{{ route('whatsapp.leads.assign', $lead->id) }}">
                        @csrf
                        <select name="assigned_user_id" class="form-control mb-2">
                            <option value="">Unassigned</option>
                            @foreach($staff as $u)
                                <option value="{{ $u->id }}" {{ (int) $lead->assigned_user_id === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-secondary btn-sm" type="submit">Assign</button>
                    </form>
                </div>
                <div class="wa-card">
                    <h5>Status</h5>
                    <form method="post" action="{{ route('whatsapp.leads.status', $lead->id) }}">
                        @csrf
                        <select name="status" class="form-control mb-2">
                            @foreach(\App\WhatsApp\LeadCatalog::statuses() as $k=>$label)
                                @if($k !== 'CONVERTED')
                                    <option value="{{ $k }}" {{ $lead->status === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input type="text" name="lost_reason" class="form-control mb-2" placeholder="Lost reason (if lost)" value="{{ $lead->lost_reason }}">
                        <button class="btn btn-primary btn-sm" type="submit">Update status</button>
                    </form>
                </div>
                <div class="wa-card">
                    <h5>Follow-up</h5>
                    <form method="post" action="{{ route('whatsapp.leads.followup', $lead->id) }}">
                        @csrf
                        <input type="datetime-local" name="follow_up_at" class="form-control mb-2" value="{{ $lead->follow_up_at ? $lead->follow_up_at->format('Y-m-d\TH:i') : '' }}">
                        <input type="text" name="note" class="form-control mb-2" placeholder="Follow-up note">
                        <select name="assigned_user_id" class="form-control mb-2">
                            <option value="">Keep / me</option>
                            @foreach($staff as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Save follow-up</button>
                    </form>
                </div>
                <div class="wa-card">
                    <h5>Convert / quote</h5>
                    @if($lead->status !== 'CONVERTED')
                        <form method="post" action="{{ route('whatsapp.leads.convert', $lead->id) }}" class="mb-2">
                            @csrf
                            <input type="hidden" name="create" value="1">
                            <button class="btn btn-success btn-sm btn-block" type="submit">Convert to customer</button>
                        </form>
                    @endif
                    <a class="btn btn-outline-success btn-sm btn-block" href="{{ url('quotations/create?phone='.$lead->normalized_phone.'&note='.urlencode($lead->latest_enquiry)) }}">Create Quotation</a>
                    @if($lead->converted_customer_id)
                        <a class="btn btn-link btn-sm btn-block" href="{{ url('quotations?customer_id='.$lead->converted_customer_id) }}">View quotations</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
