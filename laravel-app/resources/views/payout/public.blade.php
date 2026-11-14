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
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px 14px 128px; }
        .sheet {
            background: #fff;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(7, 24, 56, .12);
            border: 1px solid #d7e2f2;
        }
        .sheet-head {
            background: #071833;
            color: #fff;
            padding: 16px 18px 18px;
            position: relative;
        }
        .kicker {
            margin: 0;
            color: #e4c36a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
        }
        .sheet-head h1 { font-size: 22px; line-height: 1.15; margin: 4px 0 0; letter-spacing: -.02em; color: #fff; }
        .sheet-head .sub { margin: 6px 48px 0 0; color: rgba(255,255,255,.72); font-size: 13px; line-height: 1.4; }
        .close {
            position: absolute; top: 12px; right: 12px;
            width: 36px; height: 36px; border-radius: 999px;
            display: grid; place-items: center;
            background: rgba(255,255,255,.12); color: #fff;
            text-decoration: none; font-size: 22px; line-height: 1;
        }
        .sheet-body { padding: 14px; background: #f4f7fb; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            margin-bottom: 12px;
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
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn.gold {
            background: #c9a24a;
            color: #1a1406;
            width: 100%;
            min-height: 52px;
            border-radius: 14px;
            font-size: 17px;
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
        .dock-actions { display: flex; gap: 8px; flex: 1 1 220px; }
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
    <div class="sheet">
        <div class="sheet-head">
            <a class="close" href="{{ route('payments.hub') }}" aria-label="Back to Payments">×</a>
            <p class="kicker">PAYOUT</p>
            <h1>Request a payment</h1>
            <p class="sub">Enter a Mobile Money number. The Campay name fills in, then the amount is ready to type.</p>
        </div>
        <div class="sheet-body">
            @if(session('message'))<div class="alert ok">{{ session('message') }}</div>@endif
            @if(session('not_permitted'))<div class="alert bad">{{ session('not_permitted') }}</div>@endif

            <div class="card">
                <label for="searchBox">Phone number</label>
                <div class="search-row">
                    <input type="tel" id="searchBox" placeholder="6xxxxxxxx" autocomplete="off" enterkeyhint="search" inputmode="tel">
                    <button class="btn icon-btn" type="button" id="addPhoneTop" title="Add a number" aria-label="Add a number">+</button>
                </div>
                <p class="note" id="addedNote" hidden></p>
                <div id="results"></div>
            </div>

            <form method="POST" action="{{ route('payout.request.store', ['token' => $token]) }}" id="requestForm">
                @csrf
                <div class="card">
                    <div class="people-head">
                        <h2>Amounts</h2>
                    </div>
                    <p class="empty" id="addedEmpty">No one added yet. Enter a phone number above.</p>
                    <div id="addedBody"></div>
                </div>
                <div class="card">
                    <label for="requestNote">Note</label>
                    <input id="requestNote" type="text" name="note" maxlength="180" placeholder="What this payment is for" value="{{ old('note') }}">
                </div>
                <div class="card">
                    <label for="requesterPhone">Requestor's WhatsApp number</label>
                    <input id="requesterPhone" type="tel" name="requester_phone" maxlength="32" required inputmode="tel" autocomplete="tel" placeholder="6xxxxxxxx" value="{{ old('requester_phone') }}">
                </div>
            </form>
        </div>
    </div>
</div>
<div class="dock">
    <div class="dock-inner">
        <div class="total" id="requestTotal">0 people</div>
        <div class="dock-actions">
            <button class="btn gold" type="submit" form="requestForm">Submit</button>
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
    function lookup(phone, label, hidden, amountInput) {
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
                if (amountInput) amountInput.focus();
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
        if (person.kind === 'phone') {
            idField.name = 'extra_phone[]';
            idField.value = person.phone;
            money.input.name = 'extra_amount[]';
            hiddenName.name = 'extra_momo[]';
            hiddenName.value = person.momo || '';
            momo.textContent = person.operator ? person.operator : (person.momo ? '' : 'Name not found on MoMo');
            if (!momo.textContent) momo.hidden = true;
        } else if (person.kind === 'user') {
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
        if (person.kind !== 'phone') lookup(person.phone, momo, hiddenName, money.input);
        flash('Added ' + person.name);
        searchBox.value = '';
        results.innerHTML = '';
        refresh();
        money.input.focus();
    }
    function search() {
        var q = searchBox.value.trim();
        results.innerHTML = '';
        if (q.replace(/\D/g, '').length < 9) return;
        results.innerHTML = '<p class="empty">Looking up the Campay name…</p>';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (searchBox.value.trim() !== q) return;
                results.innerHTML = '';
                if (!data || !data.ok) {
                    results.innerHTML = '<p class="empty">' + ((data && data.error) ? data.error : 'Not an MTN or Orange number.') + '</p>';
                    return;
                }
                addRow({
                    kind: 'phone',
                    id: data.phone,
                    name: data.name || 'Name not found on MoMo',
                    phone: data.phone,
                    momo: data.name || '',
                    operator: data.operator || ''
                });
            })
            .catch(function () { results.innerHTML = '<p class="empty">The lookup did not answer. Try again.</p>'; });
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
                if (phone.value.trim().length >= 9) lookup(phone.value.trim(), momo, hidden, money.input);
            }, 500);
        });
        resetSearch();
        phone.focus();
        refresh();
    }
    var addPhone = document.getElementById('addPhone');
    if (addPhone) addPhone.addEventListener('click', openExtraPhone);
    document.getElementById('addPhoneTop').addEventListener('click', openExtraPhone);
    searchBox.focus();
    form.addEventListener('submit', function (event) {
        var ready = false;
        form.querySelectorAll('.person').forEach(function (row) {
            var phone = row.getAttribute('data-phone') || '';
            var extra = row.querySelector('.extra-phone');
            if (extra) phone = extra.value.trim();
            if (phone !== '') ready = true;
        });
        if (!ready || form.getAttribute('data-sent') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-sent', '1');
        var button = document.querySelector('button[form="requestForm"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Sending…';
        }
    });
    refresh();
})();
</script>
</body>
</html>
