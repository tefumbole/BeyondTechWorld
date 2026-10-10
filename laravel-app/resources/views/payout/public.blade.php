<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request for Payment</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f6fb; color: #1f2a44; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 80px; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        p { color: #6f7b91; }
        .card { background: #fff; border: 1px solid #e3e9f4; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-size: 13px; margin-bottom: 4px; }
        input[type="text"], input[type="search"], input[type="number"], input[type="tel"] { width: 100%; border: 1px solid #d5deee; border-radius: 8px; padding: 10px 12px; font-size: 16px; }
        .row { display: flex; gap: 12px; }
        .row > div { flex: 1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 4px; border-bottom: 1px solid #e3e9f4; vertical-align: middle; }
        th { font-size: 13px; color: #6f7b91; }
        .btn { background: #0b3f90; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font-size: 16px; cursor: pointer; }
        .plus { width: 44px; height: 44px; border-radius: 22px; font-size: 24px; line-height: 1; }
        .ghost { background: #e7eef8; color: #0b3f90; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 12px; }
        .ok { background: #e7f6ee; color: #146c43; }
        .bad { background: #fdecec; color: #9b1c1c; }
        .bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .amt { max-width: 140px; }
        .hit { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e3e9f4; }
        .hit strong { display: block; }
        .hit span { color: #6f7b91; font-size: 13px; }
        .muted { color: #6f7b91; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Request for Payment</h1>
    <p>Search for a person, add them, then enter the amount. The name on their Mobile Money number appears beside them. Money is not taken until the payment is approved.</p>
    @if(session('message'))<div class="alert ok">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert bad">{{ session('not_permitted') }}</div>@endif

    <div class="card">
        <div class="bar">
            <input type="search" id="searchBox" placeholder="Search a name or number">
            <button class="btn" type="button" id="searchBtn">Search</button>
        </div>
        <div id="results"></div>
    </div>

    <form method="POST" action="{{ route('payout.request.store', ['token' => $token]) }}" id="requestForm">
        @csrf
        <div class="card">
            <div class="row">
                <div>
                    <label>Your name</label>
                    <input type="text" name="requester_name" maxlength="80" required value="{{ old('requester_name') }}">
                </div>
                <div>
                    <label>Note</label>
                    <input type="text" name="note" maxlength="180" placeholder="What this payment is for" value="{{ old('note') }}">
                </div>
            </div>
        </div>
        <div class="card" style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Number</th>
                        <th>Name on MoMo</th>
                        <th>Amount (XAF)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="addedBody">
                    <tr id="addedEmpty"><td colspan="5" class="muted">No one added yet. Search above, then click Add.</td></tr>
                </tbody>
            </table>
            <div class="bar" style="margin-top:12px">
                <button class="btn plus" type="button" id="addPhone" title="Add a phone number">+</button>
                <span id="requestTotal"></span>
            </div>
        </div>
        <button class="btn" type="submit">Submit request</button>
    </form>
</div>
<script>
(function () {
    var form = document.getElementById('requestForm');
    var body = document.getElementById('addedBody');
    var empty = document.getElementById('addedEmpty');
    var results = document.getElementById('results');
    var total = document.getElementById('requestTotal');
    var searchUrl = @json(route('payout.request.search', ['token' => $token]));
    var lookupUrl = @json(route('payout.request.lookup', ['token' => $token]));
    function phones() {
        var found = {};
        form.querySelectorAll('[data-phone]').forEach(function (row) { found[row.getAttribute('data-phone')] = true; });
        return found;
    }
    function refresh() {
        var count = 0;
        var sum = 0;
        form.querySelectorAll('.amt').forEach(function (input) {
            var row = input.closest('tr');
            if (!row || row.id === 'addedEmpty') return;
            var phone = row.getAttribute('data-phone') || '';
            var extra = row.querySelector('.extra-phone');
            if (extra) phone = extra.value.trim();
            if (phone === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        total.textContent = count ? count + ' people · ' + sum.toLocaleString() + ' XAF' : '';
        if (empty) empty.style.display = body.querySelectorAll('tr[data-phone], tr.extra').length ? 'none' : '';
    }
    function lookup(phone, label, hidden) {
        label.textContent = 'Looking up…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(phone), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    label.textContent = data.error || 'Not found on MoMo';
                    return;
                }
                var text = data.name ? data.name : 'Name not found on MoMo';
                if (data.operator) text += ' · ' + data.operator;
                label.textContent = text;
                if (hidden) hidden.value = data.name || '';
            })
            .catch(function () { label.textContent = 'Could not look up that number'; });
    }
    function addRow(person) {
        if (phones()[person.phone]) return;
        var row = document.createElement('tr');
        row.setAttribute('data-phone', person.phone);
        var name = document.createElement('td');
        name.textContent = person.name;
        var number = document.createElement('td');
        number.textContent = person.phone;
        var momo = document.createElement('td');
        var label = document.createElement('span');
        momo.appendChild(label);
        var hiddenName = document.createElement('input');
        hiddenName.type = 'hidden';
        hiddenName.value = '';
        var idField = document.createElement('input');
        idField.type = 'hidden';
        idField.value = person.id;
        var amount = document.createElement('td');
        var amountInput = document.createElement('input');
        amountInput.className = 'amt';
        amountInput.type = 'number';
        amountInput.min = '100';
        amountInput.max = '1000000';
        amountInput.step = '1';
        amountInput.placeholder = 'Amount';
        amountInput.required = true;
        amount.appendChild(amountInput);
        if (person.kind === 'user') {
            idField.name = 'user_id[]';
            amountInput.name = 'user_amount[' + person.id + ']';
            hiddenName.name = 'user_momo[' + person.id + ']';
        } else {
            idField.name = 'customer_id[]';
            amountInput.name = 'amount[' + person.id + ']';
            hiddenName.name = 'customer_momo[' + person.id + ']';
        }
        momo.appendChild(hiddenName);
        var remove = document.createElement('td');
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn ghost';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', function () { row.remove(); refresh(); });
        remove.appendChild(removeBtn);
        row.appendChild(name);
        row.appendChild(number);
        row.appendChild(momo);
        row.appendChild(amount);
        row.appendChild(remove);
        row.appendChild(idField);
        body.appendChild(row);
        amountInput.addEventListener('input', refresh);
        lookup(person.phone, label, hiddenName);
        refresh();
    }
    function search() {
        var q = document.getElementById('searchBox').value.trim();
        results.innerHTML = '';
        if (q.length < 1) {
            return;
        }
        results.innerHTML = '<p>Searching…</p>';
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                results.innerHTML = '';
                var people = data.people || [];
                if (!people.length) {
                    results.innerHTML = '<p>No users or customers match that search.</p>';
                    return;
                }
                people.forEach(function (person) {
                    var hit = document.createElement('div');
                    hit.className = 'hit';
                    var text = document.createElement('div');
                    var strong = document.createElement('strong');
                    strong.textContent = person.name;
                    var meta = document.createElement('span');
                    meta.textContent = person.phone + ' · ' + (person.kind === 'user' ? 'User' : 'Customer');
                    text.appendChild(strong);
                    text.appendChild(meta);
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn';
                    button.textContent = 'Add';
                    button.addEventListener('click', function () {
                        addRow(person);
                        button.disabled = true;
                        button.textContent = 'Added';
                    });
                    hit.appendChild(text);
                    hit.appendChild(button);
                    results.appendChild(hit);
                });
            })
            .catch(function () { results.innerHTML = '<p>The search did not answer. Try again.</p>'; });
    }
    document.getElementById('searchBtn').addEventListener('click', search);
    var searchWait = null;
    document.getElementById('searchBox').addEventListener('input', function () {
        clearTimeout(searchWait);
        searchWait = setTimeout(search, 200);
    });
    document.getElementById('searchBox').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            search();
        }
    });
    document.getElementById('addPhone').addEventListener('click', function () {
        var row = document.createElement('tr');
        row.className = 'extra';
        row.innerHTML = '<td colspan="2"><input class="extra-phone" type="tel" name="extra_phone[]" placeholder="Phone number, 6xxxxxxxx"></td><td><span class="momo-label"></span><input type="hidden" name="extra_momo[]" value=""></td><td><input class="extra-amount amt" type="number" name="extra_amount[]" min="100" max="1000000" step="1" placeholder="Amount"></td><td></td>';
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn ghost';
        remove.textContent = 'Remove';
        remove.addEventListener('click', function () { row.remove(); refresh(); });
        row.lastChild.appendChild(remove);
        body.appendChild(row);
        var phone = row.querySelector('.extra-phone');
        var timer = null;
        phone.addEventListener('input', function () {
            row.setAttribute('data-phone', phone.value.trim());
            refresh();
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (phone.value.trim().length >= 9) {
                    lookup(phone.value.trim(), row.querySelector('.momo-label'), row.querySelector('input[type="hidden"]'));
                }
            }, 500);
        });
        row.querySelector('.extra-amount').addEventListener('input', refresh);
        phone.focus();
        refresh();
    });
    form.addEventListener('submit', function (event) {
        if (!body.querySelector('tr[data-phone], tr.extra')) event.preventDefault();
    });
})();
</script>
</body>
</html>
