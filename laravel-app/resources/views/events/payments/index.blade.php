@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h4>Event Labour Payments</h4>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="table-responsive card"><table class="table mb-0">
        <thead style="background:#0b3f90;color:#fff"><tr><th>Ref</th><th>Event</th><th>Worker</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($payments as $p)
                <tr>
                    <td><code>{{ $p->reference_no }}</code></td>
                    <td><a href="{{ route('events.show', ['id'=>$p->event_id,'tab'=>'payments']) }}">{{ $p->event->name }}</a></td>
                    <td>{{ $p->workerProfile->displayName() }}</td>
                    <td>{{ number_format($p->amount) }} XAF</td>
                    <td>{{ $p->statusLabel() }}</td>
                    <td>
                        @if($p->status==='pending' && in_array('event_payments.approve',$all_permission))
                            <form method="POST" action="{{ route('events.payments.mark-paid', $p->id) }}" class="d-inline">@csrf<button class="btn btn-xs btn-success">Mark paid</button></form>
                        @endif
                        @if($p->receipt_path)<a href="{{ url($p->receipt_path) }}" target="_blank" class="btn btn-xs btn-light">Receipt</a>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table><div class="card-footer">{{ $payments->links() }}</div></div>
</div></section>
@endsection
