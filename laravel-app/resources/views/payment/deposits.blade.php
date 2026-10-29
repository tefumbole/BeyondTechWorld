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

    <div class="pay-balance">
        <div>
            <div class="pay-kicker">Mobile Money balance</div>
            <div class="pay-total">{{ isset($campayBalance) && $campayBalance !== null ? $campayBalance.' XAF' : '— XAF' }}</div>
            <div class="pay-sub">
                @if(!isset($campayBalance) || $campayBalance === null)
                    Campay did not answer. Try again in a moment.
                @else
                    Live balance from Campay.
                @endif
            </div>
        </div>
    </div>

    <div class="pay-toolbar">
        <a class="pay-new {{ !empty($making) ? 'is-on' : '' }}" href="{{ route('deposit.index', ['new' => 1]) }}">+ Add Deposit</a>
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
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="search" id="depositSearch" class="form-control" placeholder="Type a name or number" autocomplete="off">
                            <button class="pay-plus" type="button" id="depositAddPhone" title="Add a number">+</button>
                        </div>
                        <div id="depositHits" class="list-group" style="position:absolute;z-index:5;width:100%;max-height:240px;overflow:auto"></div>
                        <div id="depositPhoneRow" style="display:none;margin-top:8px">
                            <input type="tel" id="depositPhone" name="client_phone" class="form-control" placeholder="Phone number, 6xxxxxxxx" autocomplete="off">
                        </div>
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
                    <button class="pay-go" type="submit" id="depositSubmit">Save Deposit</button>
                </form>
            </div>
        </div>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Deposits</h2></div>
        <form method="POST" action="{{ route('deposit.delete_selection') }}" id="depositsListForm">
            @csrf
            <div class="table-responsive">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="depositsAll" title="Select all"></th>
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
                                elseif ((int) $deposit->payment_method === 5) $methodLabel = 'Bitcoin';
                                $tone = (int) $deposit->status === 1 ? 'ok' : ((int) $deposit->status === 0 ? 'wait' : 'no');
                                $statusLabel = (int) $deposit->status === 1 ? 'Paid' : ((int) $deposit->status === 0 ? 'Waiting' : 'Not paid');
                            @endphp
                            <tr>
                                <td><input type="checkbox" class="deposits-line" name="ids[]" value="{{ $deposit->id }}"></td>
                                <td>{{ $deposit->created_at ? $deposit->created_at->format('M j H:i') : '' }}</td>
                                <td>{{ optional($deposit->customer)->name ?: '—' }}</td>
                                <td>{{ $deposit->account ? $deposit->account->name : '—' }}</td>
                                <td>{{ number_format((float) $deposit->amount, 0, '.', ' ') }} XAF</td>
                                <td>{{ $methodLabel }}</td>
                                <td>
                                    <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                    @if($deposit->failure_reason)
                                        <div class="pay-note">{{ strpos(strtoupper($deposit->failure_reason), 'LOW_BALANCE') !== false ? 'Not enough money on the phone' : (strpos(strtoupper($deposit->failure_reason), 'CANCEL') !== false || strpos(strtoupper($deposit->failure_reason), 'APPROV') !== false ? 'Not approved' : 'Payment was not completed') }}</div>
                                    @endif
                                </td>
                                <td>{{ $deposit->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="pay-muted">No deposits yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($deposits))
                <div class="pay-actions">
                    <button class="pay-go" type="submit" id="depositsDelete" style="background:#fdecec;color:#9b1c1c">Delete selected</button>
                </div>
            @endif
        </form>
    </div>
    <script>
    (function () {
        var form = document.getElementById('depositsListForm');
        if (!form) return;
        var all = document.getElementById('depositsAll');
        var del = document.getElementById('depositsDelete');
        function boxes() { return form.querySelectorAll('.deposits-line'); }
        if (all) {
            all.addEventListener('change', function () {
                boxes().forEach(function (box) { box.checked = all.checked; });
            });
        }
        if (del) {
            del.addEventListener('click', function (event) {
                var count = 0;
                boxes().forEach(function (box) { if (box.checked) count++; });
                if (!count) { event.preventDefault(); alert('Select the deposits you want to delete.'); return; }
                if (!confirm('Delete ' + count + (count === 1 ? ' deposit?' : ' deposits?'))) { event.preventDefault(); }
            });
        }
    })();
    </script>
</section>
<script>
(function () {
    var form = document.getElementById('depositForm');
    if (!form) return;
    var methodInput = document.getElementById('depositMethod');
    var hint = document.getElementById('depositHint');
    var submit = document.getElementById('depositSubmit');
    var labels = {
        '1': 'Save Deposit',
        '3': 'Query the phone for approval',
        '4': 'Send card link'
    };
    var hints = {
        '1': 'Cash is saved now. Choose a client if they should get a WhatsApp or SMS.',
        '3': 'This stays on the screen until the client approves the prompt on their phone.',
        '4': 'The client receives a link on WhatsApp, or by SMS, and enters the card details there.'
    };
    var clientGuard = {block_amount: 0, warning: ''};
    function showMethod(value) {
        methodInput.value = value;
        form.querySelectorAll('.pay-method').forEach(function (button) {
            button.classList.toggle('is-on', button.getAttribute('data-method') === value);
        });
        hint.textContent = hints[value] || hints['1'];
        if (submit) submit.textContent = labels[value] || labels['1'];
    }
    form.querySelectorAll('.pay-method').forEach(function (button) {
        button.addEventListener('click', function () { showMethod(button.getAttribute('data-method')); });
    });
    showMethod(methodInput.value || '1');

    var search = document.getElementById('depositSearch');
    var hits = document.getElementById('depositHits');
    var chosen = document.getElementById('depositChosen');
    var customerId = document.getElementById('depositCustomerId');
    var phoneRow = document.getElementById('depositPhoneRow');
    var phoneInput = document.getElementById('depositPhone');
    var phoneUrl = @json(route('deposit.phone'));
    var searchUrl = @json(route('deposit.search'));
    var clientUrl = @json(route('deposit.client'));
    var statusBase = @json(url('payment/desposits'));
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
                    var people = data.people || [];
                    hits.innerHTML = '';
                    people.forEach(function (person) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = person.name + ' (' + person.phone + ')';
                        button.addEventListener('click', function () { selectPerson(person); });
                        hits.appendChild(button);
                    });
                    var digits = q.replace(/\D/g, '');
                    if (digits.length >= 9) {
                        var matched = people.filter(function (person) {
                            return (person.phone || '').replace(/\D/g, '').indexOf(digits) !== -1;
                        });
                        if (matched.length === 1) {
                            selectPerson(matched[0]);
                            return;
                        }
                    }
                    if (!hits.children.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item text-muted';
                        none.textContent = 'No matching name';
                        hits.appendChild(none);
                    }
                });
        }, 200);
    });
    function selectPerson(person) {
        customerId.value = person.id;
        phoneInput.value = person.phone || '';
        chosen.textContent = person.name + ' · ' + person.phone;
        hits.innerHTML = '';
        search.value = person.name + ' (' + person.phone + ')';
        fetch(clientUrl + '?customer_id=' + encodeURIComponent(person.id), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (info) {
                if (!info || !info.ok) return;
                clientGuard = info;
                chosen.textContent = person.name + ' · ' + person.phone;
            });
    }
    function token() {
        var input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }
    function watchDeposit(id) {
        var started = Date.now();
        function tick() {
            fetch(statusBase + '/' + id + '/status', {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.status === 'paid') {
                        hint.textContent = data.message;
                        window.setTimeout(function () { window.location = statusBase; }, 1200);
                        return;
                    }
                    if (data.status === 'failed') {
                        hint.textContent = data.message;
                        submit.disabled = false;
                        submit.textContent = labels['3'];
                        return;
                    }
                    if (Date.now() - started > 180000) {
                        hint.textContent = 'Still waiting. The prompt may have expired. You can query the phone again.';
                        submit.disabled = false;
                        submit.textContent = labels['3'];
                        return;
                    }
                    window.setTimeout(tick, 4000);
                })
                .catch(function () { window.setTimeout(tick, 4000); });
        }
        tick();
    }
    document.getElementById('depositAddPhone').addEventListener('click', function () {
        phoneRow.style.display = 'block';
        customerId.value = '';
        phoneInput.focus();
    });
    var phoneWait = null;
    phoneInput.addEventListener('input', function () {
        clearTimeout(phoneWait);
        var raw = phoneInput.value.trim();
        if (raw.replace(/\D/g, '').length < 9) {
            chosen.textContent = '';
            return;
        }
        chosen.textContent = 'Looking up…';
        phoneWait = setTimeout(function () {
            fetch(phoneUrl + '?phone=' + encodeURIComponent(raw), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (info) {
                    if (!info || !info.ok) {
                        chosen.textContent = (info && info.error) ? info.error : 'Name not found';
                        return;
                    }
                    customerId.value = '';
                    phoneInput.value = info.phone;
                    chosen.textContent = (info.name ? info.name : 'Name not found on MoMo') + ' · ' + info.phone;
                });
        }, 400);
    });
    form.addEventListener('submit', function (event) {
        if (methodInput.value !== '1' && !customerId.value) {
            var visible = hits.querySelector('button');
            if (visible && hits.querySelectorAll('button').length === 1) {
                visible.click();
            }
            var typed = (search.value || '').replace(/\D/g, '');
            if (!customerId.value && typed.length >= 9 && !phoneInput.value.trim()) {
                phoneInput.value = typed;
            }
        }
        if (methodInput.value !== '1' && !customerId.value && !phoneInput.value.trim()) {
            event.preventDefault();
            hint.textContent = 'Choose the client who is paying.';
            return;
        }
        if (methodInput.value === '3') {
            var amount = parseInt(form.querySelector('[name="amount"]').value, 10) || 0;
            if (clientGuard.block_amount && amount >= clientGuard.block_amount) {
                event.preventDefault();
                hint.textContent = clientGuard.warning || 'This number does not have enough money for that amount.';
                return;
            }
            event.preventDefault();
            submit.disabled = true;
            submit.textContent = 'Waiting for approval…';
            hint.textContent = 'Ask the client to approve the prompt on their phone. This screen stays here until they do.';
            var body = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token()},
                body: body
            }).then(function (response) {
                return response.json().then(function (data) { return {ok: response.ok, data: data}; });
            }).then(function (result) {
                if (!result.ok || !result.data.ok) {
                    hint.textContent = (result.data && result.data.message) ? result.data.message : 'The phone could not be queried.';
                    submit.disabled = false;
                    submit.textContent = labels['3'];
                    return;
                }
                watchDeposit(result.data.id);
            }).catch(function () {
                hint.textContent = 'The phone could not be queried.';
                submit.disabled = false;
                submit.textContent = labels['3'];
            });
        }
    });
})();
</script>
@endsection
