@extends('layout.main')
@section('content')
<section class="container-fluid">
    @include('payout.tabs', ['tab' => 'payout'])
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted">Campay balance</div>
                <h4 class="mb-0">{{ $balance ?: 'Balance could not be read' }}</h4>
            </div>
            <div class="text-muted">MTN and Orange are separate balances.</div>
        </div>
    </div>

    @if($open)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">{{ $open->requester_name }} @if($open->note)<span class="text-muted">· {{ $open->note }}</span>@endif</h4>
                <a href="{{ route('payout.index') }}">All pending</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('payout.pay') }}" id="payoutForm">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $open->id }}">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:36px"><input type="checkbox" id="payoutAll"></th>
                                    <th>Name</th>
                                    <th>Number</th>
                                    <th>Name on MoMo</th>
                                    <th>Amount (XAF)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $line)
                                    <tr>
                                        <td>
                                            @if($line->status === 'pending')
                                                <input type="checkbox" class="payout-line" name="lines[]" value="{{ $line->id }}" checked>
                                            @endif
                                        </td>
                                        <td>{{ $line->person_name }}</td>
                                        <td>{{ $line->phone }} @if($line->network)<span class="text-muted">{{ $line->network }}</span>@endif</td>
                                        <td>{{ $line->momo_name !== '' && $line->momo_name !== null ? $line->momo_name : ((int) $line->momo_checked === 1 ? 'Not found on MoMo' : 'Looking up…') }}</td>
                                        <td>
                                            @if($line->status === 'pending')
                                                <input type="number" class="form-control form-control-sm payout-amount" name="amounts[{{ $line->id }}]" min="100" max="1000000" step="1" value="{{ $line->amount }}" style="max-width:140px">
                                            @else
                                                {{ number_format($line->amount, 0, '.', ' ') }}
                                            @endif
                                        </td>
                                        <td>{{ $line->status === 'paid' ? 'Paid' : ($line->status === 'pending' ? 'Waiting' : 'Not paid') }}@if($line->error)<div class="small text-danger">{{ $line->error }}</div>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($open->status === 'pending')
                        <button class="btn btn-primary" type="submit" id="payoutButton">Payout</button>
                        <span class="ml-2 text-muted" id="payoutTotal"></span>
                    @endif
                </form>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h4 class="mb-0">Pending</h4></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>When</th><th>From</th><th>Note</th><th>People</th><th>Total</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($pending as $row)
                        @php $sum = $totals->get($row->id); @endphp
                        <tr>
                            <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ $row->requester_name }}</td>
                            <td>{{ $row->note }}</td>
                            <td>{{ $sum ? $sum->people : 0 }}</td>
                            <td>{{ number_format($sum ? $sum->total : 0, 0, '.', ' ') }} XAF</td>
                            <td><a class="btn btn-sm btn-primary" href="{{ route('payout.index', ['request' => $row->id]) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No payment requests are waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h4 class="mb-0">Recent payouts</h4></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>When</th><th>Person</th><th>Number</th><th>Name on MoMo</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr>
                            <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ $row->person_name }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->momo_name }}</td>
                            <td>{{ number_format($row->amount, 0, '.', ' ') }} XAF</td>
                            <td>{{ $row->status === 'paid' ? 'Paid' : 'Not paid' }}@if($row->error)<div class="small text-danger">{{ $row->error }}</div>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('payoutForm');
    if (!form) return;
    var total = document.getElementById('payoutTotal');
    var all = document.getElementById('payoutAll');
    function rows() { return form.querySelectorAll('.payout-line'); }
    function amountFor(box) {
        var input = form.querySelector('[name="amounts[' + box.value + ']"]');
        return input ? parseInt(input.value, 10) || 0 : 0;
    }
    function refresh() {
        var count = 0;
        var sum = 0;
        rows().forEach(function (box) {
            if (!box.checked) return;
            count += 1;
            sum += amountFor(box);
        });
        if (total) total.textContent = count ? count + ' selected · ' + sum.toLocaleString() + ' XAF' : '';
    }
    if (all) all.addEventListener('change', function () {
        rows().forEach(function (box) { box.checked = all.checked; });
        refresh();
    });
    rows().forEach(function (box) { box.addEventListener('change', refresh); });
    form.querySelectorAll('.payout-amount').forEach(function (input) { input.addEventListener('input', refresh); });
    form.addEventListener('submit', function (event) {
        var count = 0;
        var sum = 0;
        rows().forEach(function (box) {
            if (!box.checked) return;
            count += 1;
            sum += amountFor(box);
        });
        if (count < 1) {
            event.preventDefault();
            return;
        }
        var ok = window.confirm('Pay ' + count + ' ' + (count === 1 ? 'person' : 'people') + ' a total of ' + sum.toLocaleString() + ' XAF from your Campay balance?');
        if (!ok) event.preventDefault();
    });
    refresh();
})();
</script>
@endsection
