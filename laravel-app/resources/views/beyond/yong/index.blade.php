<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Induction Service — Rev. Yong Nkiase</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #d4af37; --gold2: #f3dd8a; --navy: #0b245c; --ink: #1c160e; --paper: #fffaf1; --muted: #6b6258; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(60vw 40vw at 10% -10%, rgba(243,221,138,.25), transparent 55%),
                linear-gradient(180deg, #071433 0%, #0b245c 55%, #12306f 100%);
        }
        .page { width: min(720px, calc(100% - 20px)); margin: 0 auto; padding: 16px 0 32px; }
        .hero { text-align: center; color: #fff; padding: 4px 8px 14px; }
        .kicker { letter-spacing: .22em; text-transform: uppercase; font-size: 11px; color: var(--gold); margin: 0 0 6px; font-weight: 700; }
        h1 { font-family: Cinzel, serif; font-weight: 600; font-size: clamp(24px, 4.4vw, 36px); line-height: 1.15; margin: 0 auto; color: var(--gold2); }
        .sub { margin: 8px 0 0; color: #e8eef8; font-size: 15px; }
        .count { display: flex; justify-content: center; gap: 8px; margin: 14px 0 8px; }
        .count div { min-width: 64px; background: rgba(255,255,255,.08); border: 1px solid rgba(212,175,55,.45); border-radius: 12px; padding: 8px 6px; }
        .count strong { display: block; font-size: 22px; color: #fff; }
        .count span { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--gold2); }
        .preview { width: 100%; border-radius: 16px; display: none; border: 1px solid rgba(212,175,55,.4); }
        .preview.is-on { display: block; }
        .card { background: var(--paper); border-radius: 22px; padding: 18px; margin-top: 14px; border: 1px solid rgba(212,175,55,.28); }
        label { display: block; font-size: 13px; font-weight: 700; color: var(--navy); margin: 0 0 6px; }
        .field { margin-top: 12px; }
        .hint { font-size: 12px; color: var(--muted); margin: 6px 0 0; }
        .phone-row { display: grid; grid-template-columns: minmax(170px, .9fr) minmax(140px, 1.1fr); gap: 8px; }
        input[type="tel"], input[type="text"], input[type="number"], .cc-btn, .cc-search {
            width: 100%; min-height: 44px; border: 1px solid #e4d3a4; border-radius: 12px; padding: 0 12px; font-size: 16px; background: #fff;
        }
        .cc { position: relative; }
        .cc-btn { display: flex; align-items: center; gap: 10px; text-align: left; cursor: pointer; font-family: inherit; }
        .cc-btn .flag { font-size: 22px; }
        .cc-btn .meta { display: flex; flex-direction: column; min-width: 0; }
        .cc-btn .name { font-weight: 700; color: var(--navy); font-size: 14px; }
        .cc-btn .dial { color: var(--muted); font-size: 12px; }
        .cc-caret { margin-left: auto; color: var(--gold); }
        .cc-menu { display: none; position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 6px); background: #fff; border: 1px solid #e4d3a4; border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,.18); overflow: hidden; }
        .cc.is-open .cc-menu { display: block; }
        .cc-search { border: 0; border-bottom: 1px solid #efe3c4; border-radius: 0; }
        .cc-list { max-height: min(280px, 46vh); overflow: auto; margin: 0; padding: 6px; list-style: none; }
        .cc-list li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; cursor: pointer; }
        .cc-list li:hover, .cc-list li.is-on { background: #fff6df; }
        .cc-list .name { flex: 1; font-weight: 600; color: var(--navy); }
        .cc-list .dial { color: var(--muted); font-weight: 700; }
        .status { font-size: 13px; margin-top: 8px; display: none; }
        .positions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .choice { appearance: none; border: 1px solid #e4d3a4; background: #fff; border-radius: 12px; padding: 10px 6px; cursor: pointer; font-family: inherit; font-weight: 700; color: var(--navy); }
        .choice.is-on { border-color: var(--gold); background: #fff8e8; box-shadow: 0 0 0 2px rgba(212,175,55,.35); }
        .btn-navy { appearance: none; border: 0; border-radius: 999px; background: var(--navy); color: #fff; width: 100%; margin-top: 14px; min-height: 46px; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .err { color: #991b1b; font-size: 13px; min-height: 1em; margin-top: 8px; }
        .busy { opacity: .7; pointer-events: none; }
        @media (max-width: 700px) {
            .phone-row { grid-template-columns: 1fr; }
            .positions { grid-template-columns: 1fr 1fr; }
            .cc-menu { position: fixed; left: 12px; right: 12px; top: auto; bottom: 12px; }
        }
    </style>
</head>
<body>
@php
    $isoByCode = [
        '+237' => 'CM', '+250' => 'RW', '+256' => 'UG', '+254' => 'KE', '+255' => 'TZ',
        '+234' => 'NG', '+233' => 'GH', '+1' => 'US', '+44' => 'GB', '+33' => 'FR',
    ];
    $countryRows = [];
    foreach ($countries as $c) {
        $iso = isset($isoByCode[$c['code']]) ? $isoByCode[$c['code']] : '';
        $flag = '🌍';
        if (strlen($iso) === 2) {
            $flag = mb_convert_encoding('&#'.(127397 + ord($iso[0])).';', 'UTF-8', 'HTML-ENTITIES')
                .mb_convert_encoding('&#'.(127397 + ord($iso[1])).';', 'UTF-8', 'HTML-ENTITIES');
        }
        $name = trim(preg_replace('/\s*\(\+[0-9]+\)\s*$/', '', $c['label']));
        $countryRows[] = ['code' => $c['code'], 'name' => $name, 'flag' => $flag];
    }
@endphp
<div class="page">
    <div class="hero">
        <p class="kicker">The Apostolic Church Cameroon · Obili District</p>
        <h1>Induction Service</h1>
        <p class="sub">Rev. Yong Nkiase and Family<br>Sunday 11 October 2026 · 10:00am · The Apostolic Church Obili</p>
        <div class="count" id="count" aria-live="polite">
            <div><strong id="cd-d">0</strong><span>Days</span></div>
            <div><strong id="cd-h">0</strong><span>Hours</span></div>
            <div><strong id="cd-m">0</strong><span>Minutes</span></div>
            <div><strong id="cd-s">0</strong><span>Seconds</span></div>
        </div>
    </div>
    <img class="preview is-on" id="preview-standard" src="{{ $previews['standard'] }}" alt="Standard invitation">
    <img class="preview" id="preview-gold" src="{{ $previews['gold'] }}" alt="Gold invitation">
    <img class="preview" id="preview-clergy" src="{{ $previews['clergy'] }}" alt="Clergy invitation">
    <form class="card" id="yform" method="POST" action="{{ $submitUrl }}">
        @csrf
        <input type="hidden" name="country_code" id="countryCode" value="+237">
        <div class="field">
            <label>WhatsApp number</label>
            <div class="phone-row">
                <div class="cc" id="ccPicker">
                    <button type="button" class="cc-btn" id="ccBtn" aria-expanded="false">
                        <span class="flag" id="ccFlag">🇨🇲</span>
                        <span class="meta">
                            <span class="name" id="ccName">Cameroon</span>
                            <span class="dial" id="ccDial">+237</span>
                        </span>
                        <span class="cc-caret">▼</span>
                    </button>
                    <div class="cc-menu" id="ccMenu">
                        <input type="search" class="cc-search" id="ccSearch" placeholder="Search country" autocomplete="off">
                        <ul class="cc-list" id="ccList"></ul>
                    </div>
                </div>
                <input type="tel" name="phone" id="phone" placeholder="675321739" inputmode="numeric" autocomplete="tel" required>
            </div>
            <p class="status" id="phoneStatus"></p>
        </div>
        <div class="field">
            <label>Your name, as it should appear</label>
            <input type="text" name="name" id="displayName" required maxlength="80" placeholder="Your name" value="{{ old('name') }}">
            <p class="hint">Filled from your number when we know it. You can change it.</p>
        </div>
        <div class="field">
            <label>Your position</label>
            <input type="hidden" name="position" id="position" value="guest">
            <div class="positions" id="positions">
                <button type="button" class="choice" data-position="friend">Friend</button>
                <button type="button" class="choice" data-position="family">Family</button>
                <button type="button" class="choice" data-position="clergy">Clergy</button>
                <button type="button" class="choice is-on" data-position="guest">Guest</button>
            </div>
        </div>
        <div class="field">
            <label>Make a pledge</label>
            <input type="number" name="pledge_amount" id="pledge" min="100" step="1" inputmode="numeric" placeholder="Amount in FCFA, optional" value="{{ old('pledge_amount') }}">
            <p class="hint">Clergy receives the Clergy invitation. Family, friends, and guests who pledge at least 100 FCFA receive the Gold invitation, with a link to donate that amount. Without a pledge, they receive the Standard invitation.</p>
        </div>
        <p class="err" id="formErr">@if($errors->any()){{ $errors->first() }}@endif</p>
        <button type="submit" class="btn-navy" id="goBtn">Receive My Invitation</button>
    </form>
</div>
<script type="application/json" id="countryData">@json($countryRows)</script>
<script src="{{ asset('public/js/phone-name-lookup.js') }}"></script>
<script>
(function () {
    var target = new Date(@json($serviceAt)).getTime();
    function tick() {
        var left = Math.max(0, target - Date.now());
        var s = Math.floor(left / 1000);
        document.getElementById('cd-d').textContent = Math.floor(s / 86400);
        document.getElementById('cd-h').textContent = Math.floor((s % 86400) / 3600);
        document.getElementById('cd-m').textContent = Math.floor((s % 3600) / 60);
        document.getElementById('cd-s').textContent = s % 60;
    }
    tick();
    setInterval(tick, 1000);

    var countries = [];
    try { countries = JSON.parse(document.getElementById('countryData').textContent || '[]'); } catch (e) {}
    var hidden = document.getElementById('countryCode');
    var picker = document.getElementById('ccPicker');
    var btn = document.getElementById('ccBtn');
    var search = document.getElementById('ccSearch');
    var list = document.getElementById('ccList');
    function setCountry(row) {
        hidden.value = row.code;
        document.getElementById('ccFlag').textContent = row.flag;
        document.getElementById('ccName').textContent = row.name;
        document.getElementById('ccDial').textContent = row.code;
        hidden.dispatchEvent(new Event('change'));
    }
    function render() {
        var q = (search.value || '').toLowerCase().trim();
        var rows = !q ? countries : countries.filter(function (c) {
            return (c.name + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
        });
        list.innerHTML = '';
        rows.forEach(function (c) {
            var li = document.createElement('li');
            if (c.code === hidden.value) li.className = 'is-on';
            li.innerHTML = '<span class="flag"></span><span class="name"></span><span class="dial"></span>';
            li.querySelector('.flag').textContent = c.flag;
            li.querySelector('.name').textContent = c.name;
            li.querySelector('.dial').textContent = c.code;
            li.addEventListener('click', function () {
                setCountry(c);
                picker.classList.remove('is-open');
            });
            list.appendChild(li);
        });
    }
    btn.addEventListener('click', function () {
        var open = picker.classList.toggle('is-open');
        if (open) { search.value = ''; render(); search.focus(); }
    });
    search.addEventListener('input', render);
    document.addEventListener('click', function (e) {
        if (!picker.contains(e.target)) picker.classList.remove('is-open');
    });
    render();
    document.getElementById('positions').addEventListener('click', function (e) {
        var button = e.target.closest('.choice');
        if (!button) return;
        document.getElementById('position').value = button.getAttribute('data-position');
        document.querySelectorAll('.choice').forEach(function (el) {
            el.classList.toggle('is-on', el === button);
        });
        showPreview();
    });
    document.getElementById('pledge').addEventListener('input', showPreview);
    function showPreview() {
        var position = document.getElementById('position').value;
        var pledge = document.getElementById('pledge').value.trim();
        var pledged = pledge !== '' && !isNaN(pledge) && Number(pledge) >= 100;
        var type = position === 'clergy' ? 'clergy' : (pledged ? 'gold' : 'standard');
        ['standard', 'gold', 'clergy'].forEach(function (key) {
            document.getElementById('preview-' + key).classList.toggle('is-on', key === type);
        });
    }
    showPreview();
    attachPhoneNameLookup({
        url: @json($lookupUrl),
        phone: '#phone',
        code: '#countryCode',
        name: '#displayName',
        status: '#phoneStatus'
    });

    var form = document.getElementById('yform');
    var err = document.getElementById('formErr');
    var go = document.getElementById('goBtn');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.textContent = '';
        var pledge = document.getElementById('pledge').value.trim();
        if (pledge !== '' && (isNaN(pledge) || Number(pledge) < 100)) {
            err.textContent = 'Enter at least 100 FCFA, or leave the pledge blank.';
            return;
        }
        if (!document.getElementById('displayName').value.trim()) {
            err.textContent = 'Enter the name that should appear on the invitation.';
            return;
        }
        if (!document.getElementById('position').value) {
            err.textContent = 'Select Friend, Family, Clergy, or Guest.';
            return;
        }
        go.disabled = true;
        form.classList.add('busy');
        var body = new FormData(form);
        if (pledge === '') body.delete('pledge_amount');
        fetch(form.action, {
            method: 'POST',
            body: body,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            if (res.data && res.data.redirect) {
                window.location = res.data.redirect;
                return;
            }
            err.textContent = (res.data && res.data.message) ? res.data.message : 'Could not send the invitation.';
            go.disabled = false;
            form.classList.remove('busy');
        }).catch(function () {
            form.submit();
        });
    });
})();
</script>
</body>
</html>
