@extends('layout.main')
@section('content')
@include('payout.partials.style')
<style>
    .txn-in { color: #146c43; }
    .txn-out { color: #9b1c1c; }
</style>
<section class="container-fluid pay-app">
    <div class="pay-card">
        <div class="pay-card-head">
            <h2>Transactions</h2>
            <span class="pay-muted">{{ count($rows) }}</span>
        </div>
        <div class="table-responsive">
            <table class="pay-table pay-stack">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Person</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td data-label="When">{{ $row['when'] }}</td>
                            <td data-label="Person">{{ $row['person'] }}</td>
                            <td data-label="Type">{{ $row['type'] }}</td>
                            <td class="pay-amount-col {{ $row['direction'] === 'out' ? 'txn-out' : 'txn-in' }}" data-label="Amount">{{ $row['direction'] === 'out' ? '−' : '+' }}{{ number_format($row['amount'], 0, '.', ' ') }} XAF</td>
                            <td data-label="Reason">{{ $row['note'] }}</td>
                            <td data-label="Status"><span class="pay-pill pay-pill-{{ $row['tone'] }}">{{ $row['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-muted">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
