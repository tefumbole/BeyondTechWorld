@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    @include('payout.tabs', ['tab' => 'donations'])

    <div class="pay-balance">
        <div>
            <div class="pay-kicker">Donations received</div>
            <div class="pay-total">{{ number_format($paidTotal, 0, '.', ' ') }} XAF</div>
            <div class="pay-sub">Pending {{ number_format($pendingTotal, 0, '.', ' ') }} XAF</div>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h2>Donations</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $methodLabel = $row->method === 'visa' ? 'VISA' : ($row->method === 'crypto' ? 'Crypto' : 'Momo/OM');
                            $tone = $row->status === 'paid' ? 'ok' : ($row->status === 'failed' ? 'no' : 'wait');
                            $statusLabel = $row->status === 'paid' ? 'Paid' : ($row->status === 'failed' ? 'Not paid' : 'Pending');
                        @endphp
                        <tr>
                            <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ $row->person_name }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ number_format($row->amount, 0, '.', ' ') }} XAF</td>
                            <td>{{ $methodLabel }}</td>
                            <td>
                                <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                @if($row->status === 'failed' && $row->error)
                                    <div class="pay-note">{{ $row->error }}</div>
                                @endif
                            </td>
                            <td>{{ $row->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pay-muted">No donations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@if($pendingTotal > 0)
<script>
setTimeout(function () { window.location.reload(); }, 15000);
</script>
@endif
@endsection
