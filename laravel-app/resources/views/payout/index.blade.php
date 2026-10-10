@extends('layout.main')
@section('content')
<section class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Payout</h4>
            @if($balance)<span class="text-muted">Campay balance: {{ $balance }}</span>@endif
        </div>
        <div class="card-body">
            @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
            @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
            <p>Select people, enter one amount, and pay. Each person receives that amount on MTN or Orange. The money leaves your Campay balance.</p>
            <form method="GET" class="form-inline mb-3">
                <input type="search" name="q" value="{{ $q }}" class="form-control mr-2" placeholder="Search a name or number" style="min-width:260px">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
            <form method="POST" action="{{ route('payout.store') }}" id="payoutForm">
                @csrf
                <div class="form-row align-items-end mb-3">
                    <div class="col-md-3">
                        <label>Amount each (XAF)</label>
                        <input type="number" name="amount" id="payoutAmount" class="form-control" min="100" max="1000000" step="1" required value="{{ old('amount') }}">
                    </div>
                    <div class="col-md-5">
                        <label>Note on the payment</label>
                        <input type="text" name="note" class="form-control" maxlength="180" placeholder="Salary, refund, allowance" value="{{ old('note') }}">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit" id="payoutButton">Payout</button>
                        <span class="ml-2 text-muted" id="payoutTotal"></span>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:420px;overflow:auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="payoutAll"></th>
                                <th>Name</th>
                                <th>Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($people as $person)
                                <tr>
                                    <td><input type="checkbox" class="payout-person" name="people[]" value="{{ $person->id }}"></td>
                                    <td>{{ $person->name }}</td>
                                    <td>{{ $person->phone_number }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">No people with a phone number match that search.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    <div class="card mt-3">
        <div class="card-header"><h4 class="mb-0">Recent payouts</h4></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>When</th><th>Person</th><th>Number</th><th>Amount</th><th>Status</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr>
                            <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ $row->person_name }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ number_format($row->amount, 0, '.', ' ') }} XAF</td>
                            <td>{{ $row->status === 'paid' ? 'Paid' : 'Not paid' }}@if($row->error)<div class="small text-danger">{{ $row->error }}</div>@endif</td>
                            <td>{{ $row->note }}</td>
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
    var amount = document.getElementById('payoutAmount');
    var total = document.getElementById('payoutTotal');
    var all = document.getElementById('payoutAll');
    function boxes() { return form ? form.querySelectorAll('.payout-person') : []; }
    function refresh() {
        var count = 0;
        boxes().forEach(function (box) { if (box.checked) count += 1; });
        var each = amount ? parseInt(amount.value, 10) : 0;
        if (!each || count < 1) {
            if (total) total.textContent = count ? count + ' selected' : '';
            return;
        }
        if (total) total.textContent = count + ' × ' + each.toLocaleString() + ' = ' + (count * each).toLocaleString() + ' XAF from Campay';
    }
    if (all) all.addEventListener('change', function () {
        boxes().forEach(function (box) { box.checked = all.checked; });
        refresh();
    });
    boxes().forEach(function (box) { box.addEventListener('change', refresh); });
    if (amount) amount.addEventListener('input', refresh);
    if (form) form.addEventListener('submit', function (event) {
        var count = 0;
        boxes().forEach(function (box) { if (box.checked) count += 1; });
        var each = amount ? parseInt(amount.value, 10) : 0;
        if (count < 1 || !each) {
            event.preventDefault();
            return;
        }
        var ok = window.confirm('Pay ' + count + ' ' + (count === 1 ? 'person' : 'people') + ' ' + each.toLocaleString() + ' XAF each? This takes ' + (count * each).toLocaleString() + ' XAF from your Campay balance.');
        if (!ok) event.preventDefault();
    });
})();
</script>
@endsection
