@extends('layout.main')
@section('content')
@include('payout.partials.style')
<style>
    .momo-ledger { border-collapse: separate; border-spacing: 0; }
    .momo-pane { max-height: calc(100vh - 280px); overflow: auto; }
    .momo-ledger thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #0b3f90;
        color: #fff;
        white-space: nowrap;
    }
    .momo-ledger td { white-space: nowrap; }
    .momo-ledger td.momo-note { white-space: normal; min-width: 180px; }
    .momo-ledger tbody tr:nth-child(odd) td { background: #fff; }
    .momo-ledger tbody tr:nth-child(even) td { background: #f3f6fb; }
    .momo-in { color: #146c43; font-weight: 800; }
    .momo-out { color: #9b1c1c; font-weight: 800; }
    .momo-search { display: flex; gap: 8px; align-items: center; margin: 0; }
    .momo-search input { min-width: 220px; }
</style>
<section class="container-fluid pay-app">
    <div class="pay-balance">
        <div>
            <div class="pay-kicker">MoMo ledger</div>
            <div class="pay-total">{{ number_format($paidIn - $paidOut, 0, '.', ' ') }} XAF</div>
            <div class="pay-sub">Paid deposits minus paid withdrawals.</div>
        </div>
        <div class="pay-chips">
            <span class="pay-chip">Deposits <strong>{{ number_format($paidIn, 0, '.', ' ') }}</strong></span>
            <span class="pay-chip">Withdrawals <strong>{{ number_format($paidOut, 0, '.', ' ') }}</strong></span>
        </div>
    </div>

    <div class="pay-toolbar">
        <a class="pay-new {{ $kind === '' ? 'is-on' : '' }}" href="{{ route('momo.payments', ['q' => $search]) }}">All {{ $depositCount + $withdrawalCount }}</a>
        <a class="pay-new {{ $kind === 'deposit' ? 'is-on' : '' }}" href="{{ route('momo.payments', ['kind' => 'deposit', 'q' => $search]) }}">Deposits {{ $depositCount }}</a>
        <a class="pay-new {{ $kind === 'withdrawal' ? 'is-on' : '' }}" href="{{ route('momo.payments', ['kind' => 'withdrawal', 'q' => $search]) }}">Withdrawals {{ $withdrawalCount }}</a>
        <form class="momo-search" method="GET" action="{{ route('momo.payments') }}">
            @if($kind !== '')<input type="hidden" name="kind" value="{{ $kind }}">@endif
            <input class="form-control" type="search" name="q" value="{{ $search }}" placeholder="Name, phone, or reference">
            <button class="pay-go" type="submit">Search</button>
        </form>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h2>MoMo payments</h2></div>
        <div class="momo-pane">
            <table class="pay-table momo-ledger">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Person</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td data-label="When">{{ $row['at'] ? $row['at']->format('d M Y H:i') : '—' }}</td>
                            <td data-label="Person">{{ $row['person'] }}</td>
                            <td data-label="Phone">{{ $row['phone'] }}</td>
                            <td data-label="Type">{{ $row['kind'] }}</td>
                            <td data-label="Amount" class="{{ $row['kind_key'] === 'withdrawal' ? 'momo-out' : 'momo-in' }}">{{ $row['kind_key'] === 'withdrawal' ? '−' : '+' }}{{ number_format($row['amount'], 0, '.', ' ') }} XAF</td>
                            <td data-label="Status"><span class="pay-pill pay-pill-{{ $row['tone'] }}">{{ $row['status'] }}</span></td>
                            <td data-label="Reference">{{ $row['reference'] }}</td>
                            <td class="momo-note" data-label="Note">{{ $row['note'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="pay-muted">No MoMo deposits or withdrawals match this list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
    $("ul#payments").siblings('a').attr('aria-expanded','true');
    $("ul#payments").addClass("show");
    $("ul#payments #momo-payments-menu").addClass("active");
</script>
@endsection
