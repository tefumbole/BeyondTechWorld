@extends('layout.main')
@section('content')
@include('payout.partials.style')
@php
    $balanceParts = [];
    foreach (explode(' · ', (string) $balance) as $piece) {
        $piece = trim($piece);
        $space = strpos($piece, ' ');
        if ($piece === '' || $space === false) {
            continue;
        }
        $balanceParts[substr($piece, 0, $space)] = trim(substr($piece, $space + 1));
    }
    $totalBalance = isset($balanceParts['Total']) ? $balanceParts['Total'] : null;
@endphp
<section class="container-fluid pay-app">
    @include('payout.tabs', ['tab' => 'payout'])
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="pay-toolbar">
        <a class="pay-new {{ !empty($making) ? 'is-on' : '' }}" href="{{ route('payout.index', ['new' => 1]) }}">+ New Payout</a>
    </div>

    @if(!empty($making))
        <div class="pay-card">
            <div class="pay-card-head"><h2>New Payout</h2></div>
            <div class="pay-card-body">
                <p class="pay-help">Start typing a name. Matching users and customers appear, the same way they do on the POS screen. Add the ones to pay, then enter each amount. One person or many, the payout is sent the same way. A number already paid is left out.</p>
                <div class="pay-search">
                    <input type="search" id="payoutSearch" class="form-control" placeholder="Type a name or number" autocomplete="off">
                    <div id="payoutHits" class="list-group" style="position:absolute;z-index:5;width:100%;max-height:240px;overflow:auto"></div>
                </div>
                <form method="POST" action="{{ route('payout.direct') }}" id="newPayoutForm">
                    @csrf
                    <div class="form-group" style="max-width:420px;margin-top:14px">
                        <label>Note</label>
                        <input type="text" name="note" class="form-control" maxlength="180" placeholder="Salary, refund, allowance">
                    </div>
                    <div class="table-responsive">
                        <table class="pay-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Number</th>
                                    <th>Name on MoMo</th>
                                    <th>Amount (XAF)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="newPayoutBody">
                                <tr id="newPayoutEmpty"><td colspan="5" class="pay-muted">No one added yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pay-actions">
                        <button class="pay-plus" type="button" id="addPhone" title="Add a number">+</button>
                        <span class="pay-muted" id="newPayoutTotal"></span>
                        <button class="pay-go" type="submit">Pay</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="pay-balance">
        <div>
            <div class="pay-kicker">Campay balance</div>
            <div class="pay-total">{{ $totalBalance !== null ? $totalBalance.' XAF' : ($balance ?: 'Balance could not be read') }}</div>
            <div class="pay-sub">MTN and Orange are separate balances.</div>
        </div>
        <div class="pay-chips">
            @foreach($balanceParts as $label => $value)
                @if($label !== 'Total')
                    <span class="pay-chip">{{ $label }} <strong>{{ $value }}</strong></span>
                @endif
            @endforeach
        </div>
    </div>

    @if($open)
        <div class="pay-card">
            <div class="pay-card-head">
                <h2>{{ $open->requester_name }} @if($open->note)<span class="pay-muted">· {{ $open->note }}</span>@endif</h2>
                <a href="{{ route('payout.index') }}">All pending</a>
            </div>
            <div class="pay-card-body">
                <form method="POST" action="{{ route('payout.pay') }}" id="payoutForm">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $open->id }}">
                    <div class="table-responsive">
                        <table class="pay-table">
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
                                    @php
                                        $tone = $line->status === 'paid' ? 'ok' : ($line->status === 'pending' ? 'wait' : 'no');
                                        $statusLabel = $line->status === 'paid' ? 'Paid' : ($line->status === 'pending' ? 'Waiting' : ($line->status === 'rejected' ? 'Rejected' : 'Not paid'));
                                    @endphp
                                    <tr>
                                        <td>
                                            @if($line->status === 'pending')
                                                <input type="checkbox" class="payout-line" name="lines[]" value="{{ $line->id }}" checked>
                                            @endif
                                        </td>
                                        <td>{{ $line->person_name }}</td>
                                        <td>{{ $line->phone }} @if($line->network)<span class="pay-net">{{ $line->network }}</span>@endif</td>
                                        <td>{{ $line->momo_name !== '' && $line->momo_name !== null ? $line->momo_name : ((int) $line->momo_checked === 1 ? 'Not found on MoMo' : 'Looking up…') }}</td>
                                        <td>
                                            @if($line->status === 'pending')
                                                <input type="number" class="form-control form-control-sm payout-amount" name="amounts[{{ $line->id }}]" min="100" max="1000000" step="1" value="{{ $line->amount }}" style="max-width:140px">
                                            @else
                                                {{ number_format($line->amount, 0, '.', ' ') }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                            @if($line->error)<div class="pay-note">{{ $line->error }}</div>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($open->status === 'pending')
                        <div class="pay-actions">
                            <button class="pay-go" type="submit" id="payoutButton">Payout</button>
                            <span class="pay-muted" id="payoutTotal"></span>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Pending</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
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
                            <td><a class="pay-open" href="{{ route('payout.index', ['request' => $row->id]) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-muted">No payment requests are waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h2>Recent payouts</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
                <thead>
                    <tr><th>When</th><th>Person</th><th>Number</th><th>Name on MoMo</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        @php
                            $tone = $row->status === 'paid' ? 'ok' : 'no';
                            $statusLabel = $row->status === 'paid' ? 'Paid' : ($row->status === 'rejected' ? 'Rejected' : 'Not paid');
                        @endphp
                        <tr>
                            <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ $row->person_name }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->momo_name }}</td>
                            <td>{{ number_format($row->amount, 0, '.', ' ') }} XAF</td>
                            <td>
                                <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                @if($row->error)<div class="pay-note">{{ $row->error }}</div>@endif
                                @if($row->status === 'failed')
                                    <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
                                        <form method="POST" action="{{ route('payout.retry') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $row->id }}">
                                            <button class="pay-open" type="submit" style="border:0;cursor:pointer" onclick="return confirm('Send this payment as a Mass Payout? One person is sent the same way as many.')">Retry</button>
                                        </form>
                                        <form method="POST" action="{{ route('payout.drop') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $row->id }}">
                                            <button class="pay-open" type="submit" style="border:0;cursor:pointer;background:#fdecec;color:#9b1c1c" onclick="return confirm('Delete this failed payment? A retry will not send it.')">Delete</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-muted">No payouts yet.</td></tr>
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
    var empty = document.getElementById('newPayoutEmpty');
    var total = document.getElementById('newPayoutTotal');
    var search = document.getElementById('payoutSearch');
    var hits = document.getElementById('payoutHits');
    var lookupUrl = @json(route('payout.lookup'));
    var searchUrl = @json(route('payout.search'));
    var added = {};
    function amounts() { return form.querySelectorAll('.amt'); }
    function refresh() {
        var count = 0;
        var sum = 0;
        amounts().forEach(function (input) {
            var row = input.closest('tr');
            var phone = row.querySelector('.extra-phone');
            if (phone && phone.value.trim() === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        total.textContent = count ? count + ' selected · ' + sum.toLocaleString() + ' XAF' : '';
        if (empty) empty.style.display = body.querySelectorAll('tr').length > 1 ? 'none' : '';
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
            .catch(function () { cell.querySelector('.momo-label').textContent = 'Could not look up that number'; });
    }
    function addPerson(person) {
        var key = person.kind + ':' + person.id;
        if (added[key]) return;
        added[key] = true;
        var idName = person.kind === 'user' ? 'user_id[]' : 'customer_id[]';
        var amountName = person.kind === 'user' ? 'user_amount[' + person.id + ']' : 'amount[' + person.id + ']';
        var momoName = person.kind === 'user' ? 'user_momo[' + person.id + ']' : 'customer_momo[' + person.id + ']';
        var row = document.createElement('tr');
        var nameCell = document.createElement('td');
        nameCell.textContent = person.name;
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = idName;
        idInput.value = person.id;
        nameCell.appendChild(idInput);
        var phoneCell = document.createElement('td');
        phoneCell.textContent = person.phone;
        var momoCell = document.createElement('td');
        momoCell.className = 'momo';
        var momoLabel = document.createElement('span');
        momoLabel.className = 'momo-label';
        var momoInput = document.createElement('input');
        momoInput.type = 'hidden';
        momoInput.name = momoName;
        momoCell.appendChild(momoLabel);
        momoCell.appendChild(momoInput);
        var amountCell = document.createElement('td');
        var amountInput = document.createElement('input');
        amountInput.className = 'form-control form-control-sm amt';
        amountInput.type = 'number';
        amountInput.name = amountName;
        amountInput.min = '100';
        amountInput.max = '1000000';
        amountInput.step = '1';
        amountInput.style.maxWidth = '140px';
        amountInput.placeholder = 'Amount';
        amountCell.appendChild(amountInput);
        var removeCell = document.createElement('td');
        var remove = document.createElement('button');
        remove.className = 'btn btn-sm btn-link text-danger remove';
        remove.type = 'button';
        remove.textContent = 'Remove';
        removeCell.appendChild(remove);
        row.appendChild(nameCell);
        row.appendChild(phoneCell);
        row.appendChild(momoCell);
        row.appendChild(amountCell);
        row.appendChild(removeCell);
        body.appendChild(row);
        row.querySelector('.amt').addEventListener('input', refresh);
        row.querySelector('.remove').addEventListener('click', function () {
            delete added[key];
            row.remove();
            refresh();
        });
        resolve(person.phone, row.querySelector('.momo'), row.querySelector('.momo input'));
        hits.innerHTML = '';
        search.value = '';
        refresh();
    }
    var timer = null;
    search.addEventListener('input', function () {
        clearTimeout(timer);
        var q = search.value.trim();
        if (q.length < 1) {
            hits.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    hits.innerHTML = '';
                    (data.people || []).forEach(function (person) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = person.name + ' (' + person.phone + ')';
                        button.addEventListener('click', function () { addPerson(person); });
                        hits.appendChild(button);
                    });
                    if (!hits.children.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item text-muted';
                        none.textContent = 'No matching name';
                        hits.appendChild(none);
                    }
                });
        }, 200);
    });
    document.getElementById('addPhone').addEventListener('click', function () {
        var row = document.createElement('tr');
        row.innerHTML = '<td colspan="2"><input class="form-control form-control-sm extra-phone" type="tel" name="extra_phone[]" placeholder="Phone number, 6xxxxxxxx"></td><td class="momo"><span class="momo-label"></span><input type="hidden" name="extra_momo[]" value=""></td><td><input class="form-control form-control-sm extra-amount amt" type="number" name="extra_amount[]" min="100" max="1000000" step="1" style="max-width:140px" placeholder="Amount"></td><td><button class="btn btn-sm btn-link text-danger remove" type="button">Remove</button></td>';
        body.appendChild(row);
        var phone = row.querySelector('.extra-phone');
        var cell = row.querySelector('.momo');
        var hidden = cell.querySelector('input');
        var wait = null;
        phone.addEventListener('input', function () {
            refresh();
            clearTimeout(wait);
            wait = setTimeout(function () {
                if (phone.value.trim().length >= 9) resolve(phone.value.trim(), cell, hidden);
            }, 500);
        });
        row.querySelector('.amt').addEventListener('input', refresh);
        row.querySelector('.remove').addEventListener('click', function () { row.remove(); refresh(); });
        phone.focus();
        refresh();
    });
    form.addEventListener('submit', function (event) {
        var count = 0;
        var sum = 0;
        amounts().forEach(function (input) {
            var row = input.closest('tr');
            var phone = row.querySelector('.extra-phone');
            if (phone && phone.value.trim() === '') return;
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
