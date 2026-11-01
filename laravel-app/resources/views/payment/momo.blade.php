@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    <div class="pay-card">
        <div class="pay-card-head"><h2>Momo Payments</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Person</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['at'] ? $row['at']->format('M j, Y H:i') : '' }}</td>
                            <td>{{ $row['person'] }}</td>
                            <td>{{ $row['kind'] }}</td>
                            <td>{{ number_format($row['amount'], 0, '.', ' ') }} XAF</td>
                            <td><span class="pay-pill pay-pill-{{ $row['tone'] }}">{{ $row['status'] }}</span></td>
                            <td>{{ $row['note'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-muted">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
