@extends('layout.main')
@section('content')
@include('payout.partials.style')
<style>
    .pay-methods { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .pay-method { min-height: 40px; padding: 0 16px; border: 1px solid #d5deee; border-radius: 999px; background: #fff; color: #1f2a44; font-weight: 700; }
    .pay-method.is-on { background: #0b3f90; border-color: #0b3f90; color: #fff; }
    .pay-chosen { margin-top: 8px; font-weight: 700; }
</style>
<section class="container-fluid pay-app">
    @if(session()->has('message'))
        <div class="alert alert-success">{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger">{{ session()->get('not_permitted') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="pay-toolbar">
        <a class="pay-new {{ !empty($making) ? 'is-on' : '' }}" href="{{ route('deposit.index', ['new' => 1]) }}">+ Make Deposit</a>
    </div>

    @if(!empty($making))
        <div class="pay-card">
            <div class="pay-card-head"><h2>Make Deposit</h2></div>
            <div class="pay-card-body">
                <p class="pay-help">Choose Cash, Mobile Money, or card. Mobile Money asks the client to approve on their phone. A card payment sends them a link on WhatsApp, or by SMS if they have no WhatsApp.</p>
                <form method="POST" action="{{ route('deposit.store') }}" id="depositForm">
                    @csrf
                    <input type="hidden" name="customer_id" id="depositCustomerId" value="{{ old('customer_id') }}">
                    <input type="hidden" name="payment_method" id="depositMethod" value="{{ old('payment_method', '1') }}">
                    <div class="pay-search">
                        <label>Client</label>
                        <input type="search" id="depositSearch" class="form-control" placeholder="Type a name or number" autocomplete="off">
                        <div id="depositHits" class="list-group" style="position:absolute;z-index:5;width:100%;max-height:240px;overflow:auto"></div>
                        <div class="pay-chosen" id="depositChosen"></div>
                    </div>
                    <div class="form-group" style="max-width:460px;margin-top:14px">
                        <label>Account</label>
                        <select name="account_id" class="form-control" required>
                            <option value="">Choose an account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }} — {{ number_format((float) $account->total_balance, 0, '.', ' ') }} XAF</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="max-width:220px">
                        <label>Amount (XAF)</label>
                        <input type="number" name="amount" class="form-control" min="1" step="1" value="{{ old('amount') }}" required>
                    </div>
                    <label>Payment method</label>
                    <div class="pay-methods">
                        <button class="pay-method" type="button" data-method="1">Cash</button>
                        <button class="pay-method" type="button" data-method="3">Momo/Orange</button>
                        <button class="pay-method" type="button" data-method="4">VISA</button>
                    </div>
                    <p class="pay-help" id="depositHint">Cash is saved now. Choose a client if they should get a WhatsApp or SMS.</p>
                    <div class="form-group" style="max-width:460px">
                        <label>Note</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                    <button class="pay-go" type="submit">Save deposit</button>
                </form>
            </div>
        </div>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Deposits</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Client</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deposits as $deposit)
                        @php
                            $methodLabel = 'Unknown';
                            if ((int) $deposit->payment_method === 1) $methodLabel = 'Cash';
                            elseif ((int) $deposit->payment_method === 2) $methodLabel = 'JE Method';
                            elseif ((int) $deposit->payment_method === 3) $methodLabel = 'Momo/Orange';
                            elseif ((int) $deposit->payment_method === 4) $methodLabel = 'VISA';
                            $tone = (int) $deposit->status === 1 ? 'ok' : ((int) $deposit->status === 0 ? 'wait' : 'no');
                            $statusLabel = (int) $deposit->status === 1 ? 'Paid' : ((int) $deposit->status === 0 ? 'Waiting' : 'Not paid');
                        @endphp
                        <tr>
                            <td>{{ $deposit->created_at ? $deposit->created_at->format('M j H:i') : '' }}</td>
                            <td>{{ optional($deposit->customer)->name ?: '—' }}</td>
                            <td>{{ $deposit->account ? $deposit->account->name : '—' }}</td>
                            <td>{{ number_format((float) $deposit->amount, 0, '.', ' ') }} XAF</td>
                            <td>{{ $methodLabel }}</td>
                            <td><span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span></td>
                            <td>{{ $deposit->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pay-muted">No deposits yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('depositForm');
    if (!form) return;
    var methodInput = document.getElementById('depositMethod');
    var hint = document.getElementById('depositHint');
    var hints = {
        '1': 'Cash is saved now. Choose a client if they should get a WhatsApp or SMS.',
        '3': 'The client gets a prompt on their phone and approves the Mobile Money payment.',
        '4': 'A card link is sent to the client on WhatsApp, or by SMS if they have no WhatsApp.'
    };
    function showMethod(value) {
        methodInput.value = value;
        form.querySelectorAll('.pay-method').forEach(function (button) {
            button.classList.toggle('is-on', button.getAttribute('data-method') === value);
        });
        hint.textContent = hints[value] || hints['1'];
    }
    form.querySelectorAll('.pay-method').forEach(function (button) {
        button.addEventListener('click', function () { showMethod(button.getAttribute('data-method')); });
    });
    showMethod(methodInput.value || '1');

    var search = document.getElementById('depositSearch');
    var hits = document.getElementById('depositHits');
    var chosen = document.getElementById('depositChosen');
    var customerId = document.getElementById('depositCustomerId');
    var searchUrl = @json(route('deposit.search'));
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
                        button.addEventListener('click', function () {
                            customerId.value = person.id;
                            chosen.textContent = person.name + ' · ' + person.phone;
                            hits.innerHTML = '';
                            search.value = '';
                        });
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
    form.addEventListener('submit', function (event) {
        if (methodInput.value !== '1' && !customerId.value) {
            event.preventDefault();
            hint.textContent = 'Choose the client who is paying.';
        }
    });
})();
</script>
@endsection
