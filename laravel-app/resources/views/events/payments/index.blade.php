@extends('layout.main')
@section('content')
@include('events.partials.modern')
<section class="forms events-shell"><div class="container-fluid">
    <div class="ev-page-head"><div><div class="ev-kicker">Events</div><h4>Payment requests</h4></div></div>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <strong>Staff and labour on events</strong>
            <span class="ev-offer is-yes">{{ $acceptedCount }} accepted</span>
        </div>
        <div class="ev-offer-filters">
            <a class="{{ $offer === '' ? 'is-on' : '' }}" href="{{ route('events.payments.index') }}">Everyone</a>
            <a class="{{ $offer === 'accepted' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'accepted']) }}">Accepted</a>
            <a class="{{ $offer === 'waiting' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'waiting']) }}">Waiting</a>
            <a class="{{ $offer === 'declined' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'declined']) }}">Declined</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead style="background:#0b3f90;color:#fff"><tr><th>Event</th><th>Staff</th><th>Role</th><th>Offer</th><th>Labour</th><th>Pay</th></tr></thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        @php $acceptance = $assignment->acceptance_status ?: 'pending'; @endphp
                        <tr>
                            <td><a href="{{ route('events.show', ['id' => $assignment->event_id, 'tab' => 'payments']) }}">{{ optional($assignment->event)->name }}</a></td>
                            <td>{{ optional($assignment->workerProfile)->displayName() }}</td>
                            <td>{{ $assignment->assignment_role ?: '—' }}</td>
                            <td>
                                @if($acceptance === 'accepted')
                                    <span class="ev-offer is-yes">Accepted</span>
                                    @if($assignment->accepted_at)
                                        <div class="small text-muted">{{ $assignment->accepted_at->format('d M Y, H:i') }}</div>
                                    @endif
                                @elseif($acceptance === 'rejected')
                                    <span class="ev-offer is-no">Declined</span>
                                @else
                                    <span class="ev-offer is-wait">Waiting</span>
                                @endif
                            </td>
                            <td>{{ number_format($assignment->expected_total) }} XAF</td>
                            <td>{{ $assignment->payment_status ?: 'pending' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No engineers match this list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="table-responsive card"><table class="table mb-0">
        <thead style="background:#0b3f90;color:#fff"><tr><th>Ref</th><th>Event</th><th>Worker</th><th>Day's work</th><th>MoMo</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($payments as $p)
                @php
                    $work = (string) $p->notes;
                    if (strpos($work, ' | ') !== false) {
                        $bits = explode(' | ', $work, 2);
                        $work = $bits[0].' — '.$bits[1];
                    }
                @endphp
                <tr>
                    <td><code>{{ $p->reference_no }}</code></td>
                    <td><a href="{{ route('events.show', ['id'=>$p->event_id,'tab'=>'payments']) }}">{{ $p->event->name }}</a></td>
                    <td>{{ $p->workerProfile->displayName() }}</td>
                    <td>{{ $work !== '' ? $work : '—' }}</td>
                    <td>{{ $p->mobile_money_number ?: '—' }}</td>
                    <td>{{ number_format($p->amount) }} XAF</td>
                    <td>{{ $p->statusLabel() }}</td>
                    <td>
                        @if($p->status==='pending' && in_array('event_payments.approve',$all_permission))
                            <form method="POST" action="{{ route('events.payments.mark-paid', $p->id) }}" class="d-inline">@csrf<button class="btn btn-xs btn-success">Approve</button></form>
                        @endif
                        @if($p->receipt_path)<a href="{{ url($p->receipt_path) }}" target="_blank" class="btn btn-xs btn-light">Receipt</a>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table><div class="card-footer">{{ $payments->links() }}</div></div>
</div></section>
@endsection
