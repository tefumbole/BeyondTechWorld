@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Bill payments</h1>
        <div class="row">
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Requests</div><p class="wa-stat">{{ $metrics['bills_open'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting details</div><p class="wa-stat">{{ $metrics['bills_awaiting_details'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Under review</div><p class="wa-stat">{{ $metrics['bills_review'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Paid</div><p class="wa-stat">{{ $metrics['bills_paid'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Failed</div><p class="wa-stat">{{ $metrics['bills_failed'] }}</p></div></div>
        </div>
        <div class="wa-card">
            <table class="table table-sm">
                <thead><tr><th>Id</th><th>Status</th><th>Category</th><th>Amount</th></tr></thead>
                <tbody>
                @foreach($requests as $row)
                    <tr>
                        <td><a href="{{ route('property.bills') }}">{{ $row->id }}</a></td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->bill_category }}</td>
                        <td>{{ $row->amount }} {{ $row->currency }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
