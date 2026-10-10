@extends('layout.main')
@section('content')
<section class="container-fluid">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="mb-3">
        <a class="btn {{ !empty($making) ? 'btn-primary' : 'btn-default' }}" href="{{ route('payout.index', ['new' => 1]) }}">New Payout</a>
    </div>

    @if(!empty($making))
        <div class="card mb-3">
            <div class="card-header"><h4 class="mb-0">New Payout</h4></div>
            <div class="card-body">
                <p>Select customers, enter each amount, or add a number with the plus button. Campay shows the name on that number. Pay sends the money from your Campay balance.</p>
                <form method="GET" class="form-inline mb-3">
                    <input type="hidden" name="new" value="1">
                    <input type="search" name="q" value="{{ $q }}" class="form-control mr-2" placeholder="Search a name or number" style="min-width:260px">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>
                <form method="POST" action="{{ route('payout.direct') }}" id="newPayoutForm">
                    @csrf
                    <div class="form-group" style="max-width:420px">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control" maxlength="180" placeholder="Salary, refund, allowance">
                    </div>
                    <div class="table-responsive" style="max-height:460px;overflow:auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:36px"></th>
                                    <th>Customer</th>
                                    <th>Number</th>
                                    <th>Name on MoMo</th>
                                    <th>Amount (XAF)</th>
                                </tr>
                            </thead>
                            <tbody id="newPayoutBody">
                                @forelse($people as $person)
                                    <tr>
                                        <td><input type="checkbox" class="pick" name="customer_id[]" value="{{ $person->id }}" data-phone="{{ $person->phone_number }}"></td>
                                        <td>{{ $person->name }}</td>
                                        <td>{{ $person->phone_number }}</td>
                                        <td class="momo"><span class="momo-label"></span><input type="hidden" name="customer_momo[{{ $person->id }}]" value=""></td>
                                        <td><input class="form-control form-control-sm amt" type="number" name="amount[{{ $person->id }}]" min="100" max="1000000" step="1" style="max-width:140px" disabled></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5">No customers match that search. Add a number with the plus button.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <button class="btn btn-default" type="button" id="addPhone" title="Add a number" style="width:44px;height:44px;border-radius:22px;font-size:24px;line-height:1">+</button>
                        <span class="ml-3 text-muted" id="newPayoutTotal"></span>
                    </div>
                    <button class="btn btn-primary" type="submit">Pay</button>
                </form>
            </div>
        </div>
    @endif

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
<script>
(function () {
    var form = document.getElementById('newPayoutForm');
    if (!form) return;
    var body = document.getElementById('newPayoutBody');
    var total = document.getElementById('newPayoutTotal');
    var lookupUrl = @json(route('payout.lookup'));
    function picks() { return form.querySelectorAll('.pick'); }
    function extras() { return form.querySelectorAll('.extra-amount'); }
    function refresh() {
        var count = 0;
        var sum = 0;
        picks().forEach(function (box) {
            var input = form.querySelector('[name="amount[' + box.value + ']"]');
            if (input) input.disabled = !box.checked;
            if (!box.checked) return;
            count += 1;
            sum += input ? (parseInt(input.value, 10) || 0) : 0;
        });
        extras().forEach(function (input) {
            var phone = input.closest('tr').querySelector('.extra-phone');
            if (!phone || phone.value.trim() === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        total.textContent = count ? count + ' selected · ' + sum.toLocaleString() + ' XAF' : '';
    }
    function showName(cell, hidden, data) {
        var label = cell.querySelector('.momo-label');
        if (!data || !data.ok) {
            label.textContent = (data && data.error) ? data.error : 'Not found on MoMo';
            if (hidden) hidden.value = '';
            return;
        }
        var text = data.name ? data.name : 'Name not found on MoMo';
        if (data.operator) text += ' · ' + data.operator;
        label.textContent = text;
        if (hidden) hidden.value = data.name || '';
    }
    function resolve(phone, cell, hidden) {
        if (!phone) return;
        cell.querySelector('.momo-label').textContent = 'Looking up…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(phone), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) { showName(cell, hidden, data); })
            .catch(function () { cell.childNodes[0].textContent = 'Could not look up that number'; });
    }
    picks().forEach(function (box) {
        box.addEventListener('change', function () {
            refresh();
            if (!box.checked) return;
            var cell = box.closest('tr').querySelector('.momo');
            var hidden = cell.querySelector('input');
            if (hidden && hidden.value) return;
            resolve(box.getAttribute('data-phone'), cell, hidden);
        });
    });
    form.querySelectorAll('.amt').forEach(function (input) { input.addEventListener('input', refresh); });
    document.getElementById('addPhone').addEventListener('click', function () {
        var row = document.createElement('tr');
        row.innerHTML = '<td></td><td colspan="2"><input class="form-control form-control-sm extra-phone" type="tel" name="extra_phone[]" placeholder="Phone number, 6xxxxxxxx"></td><td class="momo"><span class="momo-label"></span><input type="hidden" name="extra_momo[]" value=""></td><td><input class="form-control form-control-sm extra-amount amt" type="number" name="extra_amount[]" min="100" max="1000000" step="1" style="max-width:140px" placeholder="Amount"></td>';
        body.appendChild(row);
        var phone = row.querySelector('.extra-phone');
        var cell = row.querySelector('.momo');
        var hidden = cell.querySelector('input');
        var timer = null;
        phone.addEventListener('input', function () {
            refresh();
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (phone.value.trim().length >= 9) resolve(phone.value.trim(), cell, hidden);
            }, 500);
        });
        row.querySelector('.extra-amount').addEventListener('input', refresh);
        phone.focus();
    });
    form.addEventListener('submit', function (event) {
        var count = 0;
        var sum = 0;
        picks().forEach(function (box) {
            if (!box.checked) return;
            count += 1;
            var input = form.querySelector('[name="amount[' + box.value + ']"]');
            sum += input ? (parseInt(input.value, 10) || 0) : 0;
        });
        extras().forEach(function (input) {
            var phone = input.closest('tr').querySelector('.extra-phone');
            if (!phone || phone.value.trim() === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        if (count < 1) {
            event.preventDefault();
            return;
        }
        var ok = window.confirm('Pay ' + count + ' ' + (count === 1 ? 'person' : 'people') + ' a total of ' + sum.toLocaleString() + ' XAF from your Campay balance?');
        if (!ok) event.preventDefault();
    });
})();
</script>
@endsection
