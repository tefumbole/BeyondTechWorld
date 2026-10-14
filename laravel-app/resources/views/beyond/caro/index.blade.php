<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>MSATA 2026 — Special Invitation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #140b2e; --gold: #e4c16a; --ink: #1d2433; --muted: #6d7380; --line: #e6e1d6; --cream: #f6f3ec; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif; color: var(--ink); background: var(--cream);
            overflow-x: hidden;
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
        .wrap { width: 100%; max-width: 1180px; margin: 0 auto; padding: 0 14px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0 14px; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; color: var(--navy); }
        .brand svg { width: 36px; height: 36px; flex: none; }
        .brand strong { display: block; font-size: 18px; line-height: 1.15; }
        .brand span { display: block; font-size: 13px; color: #5c6574; }
        .stage, .copy, .hero-copy, .card, .field, .phone-row, .positions { min-width: 0; max-width: 100%; }
        .stage {
            position: relative;
            min-height: 640px;
            border-radius: 28px;
            overflow: hidden;
            background: #1a0d33 url('{{ asset('public/caro/invitation.jpg') }}') 72% 18% / cover no-repeat;
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(300px, 430px);
            align-items: center;
            gap: 18px;
            padding: 28px 28px 28px 36px;
        }
        .stage:before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(16, 8, 36, .92) 0%, rgba(16, 8, 36, .72) 42%, rgba(16, 8, 36, .15) 68%, rgba(16, 8, 36, .05) 100%);
        }
        .hero-photo { display: none; }
        .copy { color: #fff; max-width: 430px; position: relative; z-index: 1; text-shadow: 0 2px 16px rgba(8, 20, 48, .35); }
        .kicker { margin: 0 0 8px; letter-spacing: .22em; font-size: 12px; font-weight: 700; color: var(--gold); }
        .kicker:before { content: ""; display: inline-block; width: 28px; height: 1px; background: var(--gold); vertical-align: middle; margin-right: 8px; }
        h1 { font-family: "Playfair Display", serif; font-weight: 700; font-size: clamp(42px, 5vw, 64px); line-height: .95; margin: 0; color: var(--gold); }
        .who { font-family: "Playfair Display", serif; font-size: clamp(26px, 3vw, 34px); line-height: 1.15; margin: 12px 0 18px; color: #fff; }
        .facts { list-style: none; margin: 0; padding: 0; }
        .facts li { display: flex; align-items: center; gap: 10px; margin: 8px 0; font-size: 16px; font-weight: 600; }
        .facts svg { width: 28px; height: 28px; flex: none; }
        .join { margin: 22px 0 0; max-width: min(280px, 100%); font-size: 16px; line-height: 1.45; }
        .card {
            position: relative; z-index: 1;
            background: #fff; border-radius: 22px; padding: 26px 22px 18px;
            box-shadow: 0 18px 50px rgba(16, 40, 79, .12);
        }
        .card h2 { font-family: "Playfair Display", serif; font-size: clamp(28px, 8vw, 34px); line-height: 1.12; margin: 0; color: var(--navy); font-weight: 700; }
        .lead { margin: 6px 0 16px; color: #5d6572; }
        label { display: block; font-size: 14px; font-weight: 700; color: var(--navy); margin: 0 0 6px; }
        .field { margin-top: 14px; }
        .hint, .status { font-size: 13px; color: var(--muted); margin: 6px 0 0; }
        .phone-row { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 8px; }
        input[type="tel"], input[type="text"], .cc-btn, .cc-search {
            width: 100%; min-width: 0; min-height: 46px; border: 1px solid #e4e0d8; border-radius: 12px; padding: 0 12px; font-size: 16px; background: #fff; color: var(--ink);
        }
        .cc { position: relative; }
        .cc-btn { display: flex; align-items: center; gap: 6px; width: auto; text-align: left; cursor: pointer; font-family: inherit; padding: 0 10px; }
        .cc-btn .flag { font-size: 20px; }
        .cc-btn .dial { font-weight: 700; color: var(--navy); }
        .cc-caret { margin-left: auto; color: #8b93a0; font-size: 12px; }
        .cc-menu { display: none; position: absolute; z-index: 20; left: 0; width: min(320px, 80vw); top: calc(100% + 6px); background: #fff; border: 1px solid #e4e0d8; border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,.16); overflow: hidden; }
        .cc.is-open .cc-menu { display: block; }
        .cc-search { border: 0; border-bottom: 1px solid #efeae2; border-radius: 0; }
        .cc-list { max-height: min(280px, 46vh); overflow: auto; margin: 0; padding: 6px; list-style: none; }
        .cc-list li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; cursor: pointer; }
        .cc-list li:hover, .cc-list li.is-on { background: #f8f1df; }
        .cc-list .name { flex: 1; font-weight: 600; color: var(--navy); }
        .cc-list .dial { color: var(--muted); font-weight: 700; }
        .positions { display: flex; flex-wrap: wrap; gap: 8px; }
        .choice { appearance: none; border: 1px solid #e4e0d8; background: #fff; border-radius: 999px; min-height: 44px; padding: 8px 12px; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 700; color: var(--navy); }
        .choice.is-on { border-color: #e6c56a; background: #f6e3a8; }
        .btn-navy { appearance: none; border: 0; border-radius: 12px; background: var(--navy); color: #fff; width: 100%; margin-top: 18px; min-height: 50px; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .fine { text-align: center; color: #8b93a0; font-size: 13px; margin: 10px 0 0; }
        .err { color: #991b1b; font-size: 13px; min-height: 1em; margin: 8px 0 0; }
        .later { display: none; }
        .later.is-on { display: block; }
        .count { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; width: min(520px, 100%); margin: 22px auto 6px; }
        .count div { text-align: center; background: #fff; border: 1px solid #e6dfd0; border-radius: 16px; padding: 12px 6px; }
        .count strong { display: block; font-family: "Playfair Display", serif; font-size: clamp(22px, 7vw, 32px); color: var(--navy); line-height: 1; }
        .count span { display: block; margin-top: 4px; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #8a8172; }
        footer { display: flex; align-items: center; gap: 16px; color: #8d93a0; font-size: 14px; text-align: center; padding: 22px 0 calc(28px + env(safe-area-inset-bottom)); }
        footer:before, footer:after { content: ""; flex: 1; height: 1px; background: #e3dccf; }
        @media (max-width: 900px) {
            .wrap { padding: 0 12px; }
            .stage { display: block; min-height: 0; padding: 0; overflow: visible; background: transparent; border-radius: 0; }
            .stage:before { display: none; }
            .copy { max-width: none; min-height: 0; padding: 0 0 18px; overflow: hidden; border-radius: 22px; background: #140b2e; text-shadow: none; }
            .hero-photo { display: block; height: 52vw; min-height: 190px; max-height: 320px; background: #140b2e url('{{ asset('public/caro/invitation.jpg') }}') center 18% / cover no-repeat; }
            .hero-copy { padding: 18px 16px 0; }
            h1 { font-size: clamp(36px, 11vw, 52px); }
            .who { font-size: clamp(22px, 6.4vw, 30px); }
            .card { margin-top: 14px; padding: 22px 16px 16px; }
            .positions { display: grid; grid-template-columns: 1fr 1fr; }
            .cc-menu { position: fixed; left: 12px; right: 12px; width: auto; top: auto; bottom: calc(12px + env(safe-area-inset-bottom)); }
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
    $selectedRole = old('role', 'guest');
@endphp
<div class="wrap">
    <header>
        <div class="brand">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#140b2e" d="M8 14c6 0 10 2 16 6 6-4 10-6 16-6v22c-6 0-10 2-16 6-6-4-10-6-16-6V14z"/>
                <path fill="#e4c16a" d="M24 18v22"/>
                <path fill="none" stroke="#fff" stroke-width="1.4" d="M14 20h6M14 24h6M28 20h6M28 24h6"/>
            </svg>
            <div>
                <strong>Spirit Waves Music</strong>
                <span>MSATA 2026</span>
            </div>
        </div>
    </header>
    <section class="stage">
        <div class="copy">
            <div class="hero-photo" aria-hidden="true"></div>
            <div class="hero-copy">
                <p class="kicker">YOU ARE CORDIALLY INVITED</p>
                <h1>MSATA<br>2026</h1>
                <p class="who">My Sacrifice<br>at the Altar</p>
                <ul class="facts">
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" fill="none" stroke="#e4c16a" stroke-width="1.4"/><path fill="none" stroke="#fff" stroke-width="1.6" d="M10 14h12M10 18h8M12 10h8"/></svg>
                        Sunday, 8 November 2026
                    </li>
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" fill="none" stroke="#e4c16a" stroke-width="1.4"/><path fill="none" stroke="#fff" stroke-width="1.6" d="M16 9v8l5 3"/></svg>
                        1:00 PM prompt
                    </li>
                    <li>
                        <svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" fill="none" stroke="#e4c16a" stroke-width="1.4"/><path fill="#fff" d="M16 8a6 6 0 0 0-6 6c0 5 6 10 6 10s6-5 6-10a6 6 0 0 0-6-6zm0 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg>
                        Presbyterian Hall, Ntamilung
                    </li>
                </ul>
                <p class="join">Spirit Waves Music cordially invites you. Enter your number, then choose your seat.</p>
            </div>
        </div>
        <form class="card" id="cform" method="POST" action="{{ $startUrl }}">
            @csrf
            <input type="hidden" name="country_code" id="countryCode" value="{{ old('country_code', '+237') }}">
            <h2>Receive your invitation</h2>
            <p class="lead">Start with your WhatsApp number. We will fill the name linked to it.</p>
            <div class="field">
                <label for="phone">WhatsApp number</label>
                <div class="phone-row">
                    <div class="cc" id="ccPicker">
                        <button type="button" class="cc-btn" id="ccBtn" aria-expanded="false">
                            <span class="flag" id="ccFlag">🇨🇲</span>
                            <span class="dial" id="ccDial">{{ old('country_code', '+237') }}</span>
                            <span class="cc-caret">▾</span>
                        </button>
                        <div class="cc-menu" id="ccMenu">
                            <input type="search" class="cc-search" id="ccSearch" placeholder="Search country" autocomplete="off">
                            <ul class="cc-list" id="ccList"></ul>
                        </div>
                    </div>
                    <input type="tel" name="phone" id="phone" placeholder="6XX XXX XXX" inputmode="numeric" autocomplete="tel" required value="{{ old('phone') }}">
                </div>
                <p class="status" id="phoneStatus"></p>
            </div>
            <div class="later {{ old('phone') ? 'is-on' : '' }}" id="afterPhone">
                <div class="field">
                    <label for="displayName">Your name, as it should appear</label>
                    <input type="text" name="name" id="displayName" maxlength="80" placeholder="Name on the invitation" value="{{ old('name') }}">
                    <p class="hint">You can edit the name linked to your number.</p>
                </div>
                <div class="field">
                    <label>You are attending as</label>
                    <input type="hidden" name="role" id="role" value="{{ $selectedRole }}">
                    <div class="positions" id="positions">
                        @foreach($roles as $key => $label)
                            <button type="button" class="choice {{ $selectedRole === $key ? 'is-on' : '' }}" data-role="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <p class="err" id="formErr">@if(session('not_permitted')){{ session('not_permitted') }}@elseif($errors->any()){{ $errors->first() }}@endif</p>
                <button type="submit" class="btn-navy" id="goBtn">Choose a seat →</button>
                <p class="fine">Your invitation will be sent to your WhatsApp number.</p>
            </div>
        </form>
    </section>
    <div class="count" aria-live="polite">
        <div><strong id="cd-d">0</strong><span>Days</span></div>
        <div><strong id="cd-h">0</strong><span>Hours</span></div>
        <div><strong id="cd-m">0</strong><span>Minutes</span></div>
        <div><strong id="cd-s">0</strong><span>Seconds</span></div>
    </div>
    <footer>Spirit Waves Music · MSATA 2026</footer>
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
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { search.value = ''; render(); search.focus(); }
    });
    search.addEventListener('input', render);
    document.addEventListener('click', function (e) {
        if (!picker.contains(e.target)) picker.classList.remove('is-open');
    });
    render();

    var phone = document.getElementById('phone');
    var after = document.getElementById('afterPhone');
    function reveal() {
        after.classList.toggle('is-on', phone.value.replace(/\D/g, '').length >= 8);
    }
    phone.addEventListener('input', reveal);
    reveal();

    document.getElementById('positions').addEventListener('click', function (e) {
        var button = e.target.closest('.choice');
        if (!button) return;
        document.getElementById('role').value = button.getAttribute('data-role');
        document.querySelectorAll('.choice').forEach(function (el) {
            el.classList.toggle('is-on', el === button);
        });
    });
    attachPhoneNameLookup({
        url: @json($lookupUrl),
        phone: '#phone',
        code: '#countryCode',
        name: '#displayName',
        status: '#phoneStatus'
    });

    document.getElementById('cform').addEventListener('submit', function (event) {
        var err = document.getElementById('formErr');
        if (phone.value.replace(/\D/g, '').length < 8) {
            event.preventDefault();
            err.textContent = 'Enter your WhatsApp number first.';
            return;
        }
        if (!document.getElementById('displayName').value.trim()) {
            event.preventDefault();
            err.textContent = 'Enter the name that should appear on the invitation.';
        }
    });
})();
</script>
</body>
</html>
