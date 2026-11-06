@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="pay-toolbar">
        <button class="pay-new {{ !empty($making) ? 'is-on' : '' }}" type="button" id="openNewWithdrawal">+ New Withdrawal</button>
    </div>

    @php
        $watchPayouts = false;
        foreach ($requests as $watchRow) {
            if (! empty($watchRow->list_state['waiting'])) {
                $watchPayouts = true;
                break;
            }
        }
    @endphp
    @php
        $deletablePayouts = $requests->filter(function ($row) {
            if ($row->lines->where('status', 'paid')->count() > 0) {
                return false;
            }
            return $row->lines->filter(function ($line) {
                return $line->status === 'pending' && trim((string) $line->campay_reference) !== '';
            })->count() < 1;
        });
    @endphp
    <div class="pay-card" @if($watchPayouts) data-payout-wait="1" @endif>
        <form method="POST" action="{{ route('payout.pending.delete') }}" id="pendingDeleteForm">@csrf</form>
        <form method="POST" action="{{ route('payout.approve_many') }}" id="pendingApproveForm">@csrf</form>
        <div class="pay-card-head">
            <h2>Withdrawals</h2>
            @php
                $canBulkPay = $deletablePayouts->count() > 0;
                if (! $canBulkPay) {
                    foreach ($requests as $bulkRow) {
                        if ($bulkRow->lines->whereIn('status', ['pending', 'failed'])->count() > 0) {
                            $canBulkPay = true;
                            break;
                        }
                    }
                }
            @endphp
            @if($canBulkPay)
                <button class="pay-go" type="button" id="pendingApprove">Approve and Pay</button>
                <button class="pay-danger" type="submit" form="pendingDeleteForm" id="pendingDeleteSelected">Delete selected</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="pay-table pay-stack">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="pendingAll" form="pendingDeleteForm" title="Select all"></th>
                        <th>When</th>
                        <th>People</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $row)
                        @php
                            $state = $row->list_state;
                            $unpaid = $row->lines->whereIn('status', ['pending', 'failed'])->count();
                            $canDelete = $deletablePayouts->contains('id', $row->id);
                            $unsent = $row->lines->filter(function ($line) {
                                return $line->status === 'pending' && trim((string) $line->campay_reference) === '';
                            })->count();
                        @endphp
                        <tr>
                            <td data-label="">
                                @if($canDelete || $unpaid > 0)
                                    <input type="checkbox" class="pending-row" form="pendingDeleteForm" name="ids[]" value="{{ $row->id }}">
                                @endif
                            </td>
                            <td data-label="When">{{ $row->created_at ? $row->created_at->format('M j, Y H:i') : '' }}</td>
                            <td class="pay-people" data-label="People">
                                @forelse($row->lines as $line)
                                    <div>{{ $line->person_name }} <span class="pay-muted">{{ number_format($line->amount, 0, '.', ' ') }} XAF</span></div>
                                @empty
                                    <span class="pay-muted">No one on this request.</span>
                                @endforelse
                                @if($row->requester_name)
                                    <div class="pay-muted">From {{ $row->requester_name }}</div>
                                @endif
                            </td>
                            <td data-label="Reason">{{ $row->note }}</td>
                            <td data-label="Status"><span class="pay-pill pay-pill-{{ $state['tone'] }}" data-payout-batch="{{ $row->id }}">{{ $state['label'] }}</span></td>
                            <td data-label="">
                                <div class="pay-row-actions">
                                    @if($unpaid > 0)
                                        <a class="pay-open" href="{{ route('payout.index', ['request' => $row->id]) }}#open-payout">Open</a>
                                    @endif
                                    @if($unsent > 0)
                                        <form method="POST" action="{{ route('payout.request.revise') }}">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $row->id }}">
                                            <button class="pay-open" type="submit" name="action" value="approve" style="border:0;cursor:pointer" onclick="return confirm('Approve and pay everyone still waiting on this request?')">Approve and Pay</button>
                                        </form>
                                    @elseif($row->lines->where('status', 'failed')->count() > 0)
                                        <form method="POST" action="{{ route('payout.mass', $row->id) }}">
                                            @csrf
                                            <button class="pay-open" type="submit" style="border:0;cursor:pointer" onclick="return confirm('Pay everyone still waiting? People already paid are left out.')">Pay</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-muted">No withdrawals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pay-modal" id="newWithdrawal" @if(empty($making)) hidden @endif>
        <div class="pay-card pay-modal-card">
            <div class="pay-card-head">
                <h2>New Withdrawal</h2>
                <button class="pay-modal-close" type="button" id="closeNewWithdrawal">Close</button>
            </div>
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
    </div>

    @if($open)
        <div class="pay-card" id="open-payout">
            <div class="pay-card-head">
                <h2>Edit the amount, then pay @if($open->note)<span class="pay-muted">· {{ $open->note }}</span>@endif</h2>
                <a href="{{ route('payout.index') }}">All pending</a>
            </div>
            <div class="pay-card-body">
                <p class="pay-help">{{ $open->requester_name }} Change the amount for anyone who should receive less, then pay. Save keeps the change without sending the money.</p>
                <form method="POST" action="{{ route('payout.pay') }}" id="payoutForm">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $open->id }}">
                    <input type="hidden" name="id" value="{{ $open->id }}">
                    <div class="table-responsive">
                        <table class="pay-table pay-stack">
                            <thead>
                                <tr>
                                    <th style="width:36px" title="Pay"><input type="checkbox" id="payoutAll"></th>
                                    <th style="width:36px" title="Remove"><input type="checkbox" id="payoutRemoveAll"></th>
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
                                        $canEdit = in_array($line->status, ['pending', 'failed'], true);
                                    @endphp
                                    <tr>
                                        <td data-label="Pay">
                                            @if($canEdit)
                                                <input type="checkbox" class="payout-line" name="lines[]" value="{{ $line->id }}" checked>
                                            @endif
                                        </td>
                                        <td data-label="Remove">
                                            @if($canEdit)
                                                <input type="checkbox" class="payout-remove" name="remove_ids[]" value="{{ $line->id }}">
                                            @endif
                                        </td>
                                        <td data-label="Name">{{ $line->person_name }}</td>
                                        <td data-label="Number">{{ $line->phone }} @if($line->network)<span class="pay-net">{{ $line->network }}</span>@endif</td>
                                        <td data-label="MoMo">{{ $line->momo_name !== '' && $line->momo_name !== null ? $line->momo_name : ((int) $line->momo_checked === 1 ? 'Not found on MoMo' : 'Looking up…') }}</td>
                                        <td data-label="Amount">
                                            @if($canEdit)
                                                <input type="number" class="form-control payout-amount" name="amounts[{{ $line->id }}]" min="100" max="1000000" step="1" value="{{ (int) $line->amount }}" inputmode="numeric">
                                            @else
                                                {{ number_format($line->amount, 0, '.', ' ') }}
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="pay-pill pay-pill-{{ $tone }}" data-payout-line="{{ $line->id }}">{{ $statusLabel }}</span>
                                            @if($line->error)<div class="pay-note">{{ $line->error }}</div>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($lines->whereIn('status', ['pending', 'failed'])->count() > 0)
                        <div class="pay-actions">
                            <button class="pay-open" type="submit" formaction="{{ route('payout.request.revise') }}" name="action" value="save" style="border:0;cursor:pointer">Save changes</button>
                            <button class="pay-danger" type="submit" formaction="{{ route('payout.request.revise') }}" name="action" value="delete" id="payoutRemove">Remove selected</button>
                            <button class="pay-go" type="submit" id="payoutButton" data-pay="1">Pay</button>
                            <span class="pay-muted" id="payoutTotal"></span>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif

</section>
<script>
(function () {
    var card = document.querySelector('[data-payout-wait]');
    if (!card) return;
    var url = @json(route('payout.live'));
    function paint(selector, row) {
        var pill = document.querySelector(selector);
        if (!pill) return;
        pill.className = 'pay-pill pay-pill-' + row.tone;
        pill.textContent = row.status;
        if (row.status === 'Paid') {
            var actions = pill.closest('tr') ? pill.closest('tr').querySelector('.pay-row-actions') : null;
            if (actions) actions.innerHTML = '';
        }
    }
    function tick() {
        fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                (data.batches || []).forEach(function (row) { paint('[data-payout-batch="' + row.id + '"]', row); });
                (data.lines || []).forEach(function (row) { paint('[data-payout-line="' + row.id + '"]', row); });
                if (data.pending) setTimeout(tick, 3000);
            })
            .catch(function () { setTimeout(tick, 5000); });
    }
    setTimeout(tick, 700);
})();
(function () {
    var form = document.getElementById('pendingDeleteForm');
    if (!form) return;
    var all = document.getElementById('pendingAll');
    function boxes() { return document.querySelectorAll('.pending-row'); }
    if (all) all.addEventListener('change', function () {
        boxes().forEach(function (box) { box.checked = all.checked; });
    });
    form.addEventListener('submit', function (event) {
        var count = 0;
        boxes().forEach(function (box) { if (box.checked) count += 1; });
        if (count < 1) {
            event.preventDefault();
            window.alert('Select the payouts to delete.');
            return;
        }
        if (!window.confirm('Delete ' + count + ' ' + (count === 1 ? 'payout' : 'payouts') + '? People already paid are left in place.')) {
            event.preventDefault();
        }
    });
    var approve = document.getElementById('pendingApprove');
    var approveForm = document.getElementById('pendingApproveForm');
    if (approve && approveForm) {
        approve.addEventListener('click', function () {
            var ids = [];
            boxes().forEach(function (box) { if (box.checked) ids.push(box.value); });
            if (!ids.length) {
                window.alert('Select the payments to approve and pay.');
                return;
            }
            if (!window.confirm('Approve and pay ' + ids.length + ' ' + (ids.length === 1 ? 'request' : 'requests') + '?')) return;
            approveForm.querySelectorAll('input[name="ids[]"]').forEach(function (input) { input.remove(); });
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                approveForm.appendChild(input);
            });
            approveForm.submit();
        });
    }
})();
</script>
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
    var removeAll = document.getElementById('payoutRemoveAll');
    function removeBoxes() { return form.querySelectorAll('.payout-remove'); }
    if (removeAll) removeAll.addEventListener('change', function () {
        removeBoxes().forEach(function (box) { box.checked = removeAll.checked; });
    });
    var removeBtn = document.getElementById('payoutRemove');
    if (removeBtn) removeBtn.addEventListener('click', function (event) {
        var count = 0;
        removeBoxes().forEach(function (box) { if (box.checked) count += 1; });
        if (count < 1) {
            event.preventDefault();
            window.alert('Select the names to remove.');
            return;
        }
        if (!window.confirm('Remove ' + count + ' ' + (count === 1 ? 'name' : 'names') + ' from this request?')) {
            event.preventDefault();
        }
    });
    rows().forEach(function (box) { box.addEventListener('change', refresh); });
    form.querySelectorAll('.payout-amount').forEach(function (input) { input.addEventListener('input', refresh); });
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter || document.activeElement;
        if (!submitter || submitter.getAttribute('data-pay') !== '1') return;
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
    var modal = document.getElementById('newWithdrawal');
    var open = document.getElementById('openNewWithdrawal');
    var close = document.getElementById('closeNewWithdrawal');
    if (modal && open) {
        open.addEventListener('click', function () {
            modal.hidden = false;
            open.classList.add('is-on');
            var search = document.getElementById('payoutSearch');
            if (search) search.focus();
        });
    }
    if (modal && close) {
        close.addEventListener('click', function () {
            modal.hidden = true;
            if (open) open.classList.remove('is-on');
        });
    }
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
