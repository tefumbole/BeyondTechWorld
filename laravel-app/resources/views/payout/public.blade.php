<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Request for Payment</title>
    <style>
        :root {
            --ink: #12203a;
            --muted: #66748c;
            --line: #e4ebf5;
            --blue: #0b3f90;
            --blue-soft: #e8f0fb;
            --gold: #b8891a;
            --bg: #eef3fb;
            --ok: #146c43;
            --bad: #9b1c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(720px 320px at 110% -8%, rgba(184, 137, 26, .22), transparent 60%),
                radial-gradient(640px 280px at -20% 0%, rgba(11, 63, 144, .16), transparent 55%),
                var(--bg);
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 18px 14px 128px; }
        .back { color: var(--blue); font-weight: 700; text-decoration: none; font-size: 14px; }
        h1 { font-size: 30px; line-height: 1.1; margin: 10px 0 6px; letter-spacing: -.03em; }
        .lead { color: var(--muted); font-size: 14px; line-height: 1.5; margin: 0 0 16px; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 10px 28px rgba(16, 33, 61, .05);
        }
        label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 6px; }
        input[type="text"], input[type="search"], input[type="number"], input[type="tel"] {
            width: 100%;
            border: 1px solid #d5deee;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 16px;
            color: var(--ink);
            background: #fff;
        }
        input:focus { outline: 2px solid rgba(11, 63, 144, .25); border-color: var(--blue); }
        .search-row { display: flex; gap: 8px; align-items: center; }
        .search-row input { flex: 1; min-width: 0; }
        .btn {
            background: var(--blue);
            color: #fff;
            border: 0;
            border-radius: 999px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn:active { transform: translateY(1px); }
        .icon-btn {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            font-size: 26px;
            line-height: 1;
            padding: 0;
            flex: 0 0 auto;
        }
        .ghost { background: var(--blue-soft); color: var(--blue); }
        .tiny { padding: 8px 12px; font-size: 13px; }
        .note {
            margin: 10px 0 0;
            padding: 10px 12px;
            border-radius: 12px;
            background: #e7f6ee;
            color: var(--ok);
            font-size: 14px;
            font-weight: 700;
        }
        .results { margin-top: 4px; }
        .hit {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 2px;
            border-top: 1px solid var(--line);
        }
        .hit strong { display: block; font-size: 15px; }
        .hit span { color: var(--muted); font-size: 13px; }
        .hit .btn { margin-left: auto; }
        .fields { display: grid; gap: 12px; }
        .people-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 4px; }
        .people-head h2 { margin: 0; font-size: 16px; }
        .empty { color: var(--muted); font-size: 14px; margin: 8px 0 0; }
        .person { padding: 14px 0; border-top: 1px solid var(--line); }
        .who { display: grid; grid-template-columns: 44px minmax(0, 1fr) auto; gap: 10px; align-items: start; }
        .avatar {
            width: 44px; height: 44px; border-radius: 14px;
            display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 16px;
        }
        .avatar[data-tone="0"] { background: #0b3f90; }
        .avatar[data-tone="1"] { background: #0f766e; }
        .avatar[data-tone="2"] { background: #b45309; }
        .avatar[data-tone="3"] { background: #7c3aed; }
        .avatar[data-tone="4"] { background: #be123c; }
        .avatar[data-tone="5"] { background: #0369a1; }
        .who strong { display: block; font-size: 15px; line-height: 1.25; }
        .phone, .momo { display: block; color: var(--muted); font-size: 13px; line-height: 1.35; margin-top: 2px; word-break: break-word; }
        .momo { color: #1d4e89; }
        .amt-wrap { margin-top: 10px; }
        .alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; font-size: 14px; }
        .ok { background: #e7f6ee; color: var(--ok); }
        .bad { background: #fdecec; color: var(--bad); }
        .dock {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
            background: rgba(255, 255, 255, .94);
            border-top: 1px solid var(--line);
            backdrop-filter: blur(10px);
        }
        .dock-inner { max-width: 720px; margin: 0 auto; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .total { font-size: 13px; color: var(--muted); font-weight: 700; min-width: 0; flex: 1 1 120px; }
        .total strong { display: block; color: var(--ink); font-size: 16px; }
        .dock-actions { display: flex; gap: 8px; flex: 1 1 240px; }
        .dock-actions .btn { flex: 1; text-align: center; text-decoration: none; }
        @media (min-width: 720px) {
            .wrap { padding-top: 28px; }
            .fields { grid-template-columns: 1fr 1fr; }
            .fields .wide { grid-column: 1 / -1; }
            .person { display: grid; grid-template-columns: minmax(0, 1fr) 180px; gap: 16px; align-items: center; }
            .amt-wrap { margin-top: 0; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="{{ route('payments.hub') }}">Back to Payments</a>
    <h1>Payout</h1>
    <p class="lead">Search for a person, add them, then enter the amount. The name on their Mobile Money number appears beside them. Money is not taken until the payment is approved.</p>
    @if(session('message'))<div class="alert ok">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert bad">{{ session('not_permitted') }}</div>@endif

    <div class="card">
        <div class="search-row">
            <input type="search" id="searchBox" placeholder="Search by name or phone number" autocomplete="off" enterkeyhint="search">
            <button class="btn icon-btn" type="button" id="addPhoneTop" title="Add a number" aria-label="Add a number">+</button>
        </div>
        <p class="note" id="addedNote" hidden></p>
        <div id="results"></div>
    </div>

    <form method="POST" action="{{ route('payout.request.store', ['token' => $token]) }}" id="requestForm">
        @csrf
        <div class="card">
            <div class="fields">
                <div>
                    <label for="requesterName">Your name</label>
                    <input id="requesterName" type="text" name="requester_name" maxlength="80" required value="{{ old('requester_name') }}">
                </div>
                <div>
                    <label for="requesterPhone">Your WhatsApp number</label>
                    <input id="requesterPhone" type="tel" name="requester_phone" maxlength="32" required placeholder="+237 6 00 00 00 00" value="{{ old('requester_phone') }}">
                </div>
                <div class="wide">
                    <label for="requestNote">Note</label>
                    <input id="requestNote" type="text" name="note" maxlength="180" placeholder="What this payment is for" value="{{ old('note') }}">
                </div>
            </div>
        </div>
        <div class="card">
            <div class="people-head">
                <h2>People</h2>
                <button class="btn ghost tiny" type="button" id="addPhone">Add a number</button>
            </div>
            <p class="empty" id="addedEmpty">No one added yet. Search above, then tap Add.</p>
            <div id="addedBody"></div>
        </div>
    </form>
</div>
<div class="dock">
    <div class="dock-inner">
        <div class="total" id="requestTotal">0 people</div>
        <div class="dock-actions">
            <a class="btn ghost" href="{{ route('payments.hub') }}">Back to Payments</a>
            <button class="btn" type="submit" form="requestForm">Submit request</button>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('requestForm');
    var body = document.getElementById('addedBody');
    var empty = document.getElementById('addedEmpty');
    var results = document.getElementById('results');
    var total = document.getElementById('requestTotal');
    var searchBox = document.getElementById('searchBox');
    var addedNote = document.getElementById('addedNote');
    var noteTimer = null;
    var searchUrl = @json(route('payout.request.search', ['token' => $token]));
    var lookupUrl = @json(route('payout.request.lookup', ['token' => $token]));
    var tones = ['0', '1', '2', '3', '4', '5'];

    function phones() {
        var found = {};
        form.querySelectorAll('[data-phone]').forEach(function (row) {
            var phone = row.getAttribute('data-phone');
            if (phone) found[phone] = true;
        });
        return found;
    }
    function toneFor(name) {
        var sum = 0;
        var text = name || '?';
        for (var i = 0; i < text.length; i++) sum += text.charCodeAt(i);
        return tones[sum % tones.length];
    }
    function initial(name) {
        var text = (name || '?').trim();
        return text ? text.charAt(0).toUpperCase() : '?';
    }
    function flash(text) {
        addedNote.textContent = text;
        addedNote.hidden = false;
        clearTimeout(noteTimer);
        noteTimer = setTimeout(function () { addedNote.hidden = true; }, 2200);
    }
    function resetSearch() {
        searchBox.value = '';
        results.innerHTML = '';
        searchBox.focus();
    }
    function refresh() {
        var count = 0;
        var sum = 0;
        form.querySelectorAll('.amt').forEach(function (input) {
            var row = input.closest('.person');
            if (!row) return;
            var phone = row.getAttribute('data-phone') || '';
            var extra = row.querySelector('.extra-phone');
            if (extra) phone = extra.value.trim();
            if (phone === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        total.innerHTML = '<strong>' + (count ? sum.toLocaleString() + ' XAF' : '0 XAF') + '</strong>' + count + (count === 1 ? ' person' : ' people');
        empty.hidden = body.querySelectorAll('.person').length > 0;
    }
    function lookup(phone, label, hidden) {
        label.textContent = 'Looking up the Mobile Money name…';
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
    function removeButton(row) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn ghost tiny';
        button.textContent = 'Remove';
        button.addEventListener('click', function () { row.remove(); refresh(); });
        return button;
    }
    function amountField() {
        var wrap = document.createElement('div');
        wrap.className = 'amt-wrap';
        var label = document.createElement('label');
        label.textContent = 'Amount (XAF)';
        var input = document.createElement('input');
        input.className = 'amt';
        input.type = 'number';
        input.min = '100';
        input.max = '1000000';
        input.step = '1';
        input.placeholder = 'Amount';
        input.required = true;
        input.inputMode = 'numeric';
        wrap.appendChild(label);
        wrap.appendChild(input);
        input.addEventListener('input', refresh);
        return {wrap: wrap, input: input};
    }
    function addRow(person) {
        if (phones()[person.phone]) {
            flash(person.name + ' is already on the list');
            resetSearch();
            return;
        }
        var row = document.createElement('article');
        row.className = 'person';
        row.setAttribute('data-phone', person.phone);
        var who = document.createElement('div');
        who.className = 'who';
        var avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.setAttribute('data-tone', toneFor(person.name));
        avatar.textContent = initial(person.name);
        var text = document.createElement('div');
        var name = document.createElement('strong');
        name.textContent = person.name;
        var phone = document.createElement('span');
        phone.className = 'phone';
        phone.textContent = person.phone;
        var momo = document.createElement('span');
        momo.className = 'momo';
        text.appendChild(name);
        text.appendChild(phone);
        text.appendChild(momo);
        who.appendChild(avatar);
        who.appendChild(text);
        who.appendChild(removeButton(row));
        var money = amountField();
        var hiddenName = document.createElement('input');
        hiddenName.type = 'hidden';
        hiddenName.value = '';
        var idField = document.createElement('input');
        idField.type = 'hidden';
        idField.value = person.id;
        if (person.kind === 'user') {
            idField.name = 'user_id[]';
            money.input.name = 'user_amount[' + person.id + ']';
            hiddenName.name = 'user_momo[' + person.id + ']';
        } else {
            idField.name = 'customer_id[]';
            money.input.name = 'amount[' + person.id + ']';
            hiddenName.name = 'customer_momo[' + person.id + ']';
        }
        row.appendChild(who);
        row.appendChild(money.wrap);
        row.appendChild(hiddenName);
        row.appendChild(idField);
        body.appendChild(row);
        lookup(person.phone, momo, hiddenName);
        flash('Added ' + person.name);
        resetSearch();
        refresh();
    }
    function search() {
        var q = searchBox.value.trim();
        results.innerHTML = '';
        if (q.length < 2) return;
        results.innerHTML = '<p class="empty">Searching…</p>';
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (searchBox.value.trim() !== q) return;
                results.innerHTML = '';
                var people = data.people || [];
                if (!people.length) {
                    results.innerHTML = '<p class="empty">No users or customers match that search.</p>';
                    return;
                }
                people.forEach(function (person) {
                    var hit = document.createElement('div');
                    hit.className = 'hit';
                    var copy = document.createElement('div');
                    var strong = document.createElement('strong');
                    strong.textContent = person.name;
                    var meta = document.createElement('span');
                    meta.textContent = person.phone + ' · ' + (person.kind === 'user' ? 'User' : 'Customer');
                    copy.appendChild(strong);
                    copy.appendChild(meta);
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn tiny';
                    button.textContent = phones()[person.phone] ? 'Added' : 'Add';
                    button.disabled = !!phones()[person.phone];
                    button.addEventListener('click', function () { addRow(person); });
                    hit.appendChild(copy);
                    hit.appendChild(button);
                    results.appendChild(hit);
                });
            })
            .catch(function () { results.innerHTML = '<p class="empty">The search did not answer. Try again.</p>'; });
    }
    var searchWait = null;
    searchBox.addEventListener('input', function () {
        addedNote.hidden = true;
        clearTimeout(searchWait);
        searchWait = setTimeout(search, 200);
    });
    searchBox.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            search();
        }
    });
    function openExtraPhone() {
        var row = document.createElement('article');
        row.className = 'person extra';
        var who = document.createElement('div');
        who.className = 'who';
        var avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.setAttribute('data-tone', '1');
        avatar.textContent = '#';
        var text = document.createElement('div');
        var phone = document.createElement('input');
        phone.className = 'extra-phone';
        phone.type = 'tel';
        phone.name = 'extra_phone[]';
        phone.placeholder = 'Phone number, 6xxxxxxxx';
        var momo = document.createElement('span');
        momo.className = 'momo momo-label';
        text.appendChild(phone);
        text.appendChild(momo);
        who.appendChild(avatar);
        who.appendChild(text);
        who.appendChild(removeButton(row));
        var money = amountField();
        money.input.classList.add('extra-amount');
        money.input.name = 'extra_amount[]';
        money.input.required = false;
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'extra_momo[]';
        hidden.value = '';
        row.appendChild(who);
        row.appendChild(money.wrap);
        row.appendChild(hidden);
        body.appendChild(row);
        var timer = null;
        phone.addEventListener('input', function () {
            row.setAttribute('data-phone', phone.value.trim());
            refresh();
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (phone.value.trim().length >= 9) lookup(phone.value.trim(), momo, hidden);
            }, 500);
        });
        resetSearch();
        phone.focus();
        refresh();
    }
    document.getElementById('addPhone').addEventListener('click', openExtraPhone);
    document.getElementById('addPhoneTop').addEventListener('click', openExtraPhone);
    form.addEventListener('submit', function (event) {
        var ready = false;
        form.querySelectorAll('.person').forEach(function (row) {
            var phone = row.getAttribute('data-phone') || '';
            var extra = row.querySelector('.extra-phone');
            if (extra) phone = extra.value.trim();
            if (phone !== '') ready = true;
        });
        if (!ready) event.preventDefault();
    });
    refresh();
})();
</script>
</body>
</html>
