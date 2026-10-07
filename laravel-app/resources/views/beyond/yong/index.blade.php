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
            color: #fff;
            background-color: #071433;
            background-image:
                linear-gradient(180deg, rgba(7,20,51,.78) 0%, rgba(11,36,92,.72) 42%, rgba(7,20,51,.84) 100%),
                url('{{ asset('public/yong/standard.jpg') }}');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }
        .page { width: min(720px, calc(100% - 20px)); margin: 0 auto; padding: 18px 0 28px; position: relative; }
        .hero { text-align: center; color: #fff; padding: 8px 8px 6px; }
        h1, .headline { font-family: Cinzel, serif; font-weight: 700; font-size: clamp(26px, 5vw, 42px); line-height: 1.2; margin: 0 auto; color: var(--gold2); }
        .headline { margin-top: 8px; }
        .sub { margin: 12px 0 0; color: #fff; font-size: 18px; font-weight: 600; }
        .count { display: flex; justify-content: center; gap: 10px; margin: 18px 0 0; }
        .count div { flex: 1; max-width: 130px; background: rgba(7,20,51,.55); border: 1px solid rgba(212,175,55,.55); border-radius: 16px; padding: 16px 6px 14px; }
        .count strong { display: block; font-size: clamp(36px, 8vw, 56px); line-height: 1; color: #fff; font-variant-numeric: tabular-nums; }
        .count span { display: block; margin-top: 8px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: var(--gold2); }
        .card { background: rgba(7,20,51,.62); border-radius: 22px; padding: 18px; margin-top: 16px; border: 1px solid rgba(212,175,55,.4); }
        label { display: block; font-size: 15px; font-weight: 700; color: var(--gold2); margin: 0 0 6px; }
        .field { margin-top: 12px; }
        .hint { font-size: 13px; color: #e8eef8; margin: 6px 0 0; }
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
        .status { font-size: 13px; margin-top: 8px; display: none; color: #fff; }
        .positions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .choice { appearance: none; border: 1px solid #e4d3a4; background: #fff; border-radius: 12px; padding: 10px 6px; cursor: pointer; font-family: inherit; font-weight: 700; color: var(--navy); }
        .choice.is-on { border-color: var(--gold); background: #fff8e8; box-shadow: 0 0 0 2px rgba(212,175,55,.35); }
        .btn-navy { appearance: none; border: 0; border-radius: 999px; background: var(--gold); color: var(--navy); width: 100%; margin-top: 14px; min-height: 46px; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .err { color: #fecaca; font-size: 13px; min-height: 1em; margin-top: 8px; }
        .tabs { display: flex; justify-content: center; gap: 8px; margin: 16px 0 0; flex-wrap: wrap; }
        .tabs a { color: var(--gold2); text-decoration: none; border: 1px solid rgba(212,175,55,.5); border-radius: 999px; padding: 8px 16px; font-weight: 700; }
        .tabs a.is-on { background: var(--gold); color: var(--navy); }
        .is-hidden { display: none; }
        select { width: 100%; min-height: 44px; border: 1px solid #e4d3a4; border-radius: 12px; padding: 0 12px; font-size: 16px; background: #fff; }
        textarea { width: 100%; min-height: 90px; border: 1px solid #e4d3a4; border-radius: 12px; padding: 10px 12px; font-size: 16px; font-family: inherit; }
        .review { border-top: 1px solid rgba(212,175,55,.35); padding: 10px 0; }
        .review strong { color: #fff; }
        .stars { color: var(--gold); letter-spacing: 2px; }
        .shots { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .shots figure { margin: 0; }
        .shots img { width: 100%; border-radius: 12px; display: block; }
        .shots figcaption { color: #e8eef8; font-size: 13px; margin-top: 4px; }
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
        <h1>Induction Service</h1>
        <p class="headline">Rev. Yong Nkiase and Family</p>
        <p class="headline">The Apostolic Church Obili</p>
        <p class="sub">Sunday 11 October 2026 · 10:00am</p>
    </div>
    <nav class="tabs">
        <a href="{{ url('/yong') }}" class="{{ $tab === 'invite' ? 'is-on' : '' }}">Invitation</a>
        <a href="{{ url('/yong?tab=reviews') }}" class="{{ $tab === 'reviews' ? 'is-on' : '' }}">Reviews</a>
        <a href="{{ url('/yong?tab=gallery') }}" class="{{ $tab === 'gallery' ? 'is-on' : '' }}">Gallery</a>
    </nav>
    <form class="card {{ $tab === 'invite' ? '' : 'is-hidden' }}" id="yform" method="POST" action="{{ $submitUrl }}">
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
            <label>(minimum 5,000frs)</label>
            <input type="number" name="pledge_amount" id="pledge" min="5000" step="1" inputmode="numeric" placeholder="Amount in FCFA, optional" value="{{ old('pledge_amount') }}">
        </div>
        <p class="err" id="formErr">@if($errors->any()){{ $errors->first() }}@endif</p>
        <button type="submit" class="btn-navy" id="goBtn">Receive My Invitation</button>
    </form>
    <section class="card {{ $tab === 'reviews' ? '' : 'is-hidden' }}" id="reviews">
        <form method="POST" action="{{ url('/yong/reviews') }}">
            @csrf
            <div class="field">
                <label>Your name</label>
                <input type="text" name="name" maxlength="80" required placeholder="Your name">
            </div>
            <div class="field">
                <label>Your review</label>
                <select name="rating" required>
                    <option value="5">5 — Excellent</option>
                    <option value="4">4 — Very good</option>
                    <option value="3">3 — Good</option>
                    <option value="2">2 — Fair</option>
                    <option value="1">1 — Poor</option>
                </select>
            </div>
            <div class="field">
                <label>Comment</label>
                <textarea name="comment" maxlength="1000" placeholder="How was the induction service?"></textarea>
            </div>
            <button type="submit" class="btn-navy">Share review</button>
        </form>
        @forelse($reviews as $review)
            <div class="review">
                <strong>{{ $review->name }}</strong>
                <div class="stars">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</div>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
            </div>
        @empty
            <p class="hint">Reviews will appear here.</p>
        @endforelse
    </section>
    <section class="card {{ $tab === 'gallery' ? '' : 'is-hidden' }}" id="gallery">
        <form method="POST" action="{{ url('/yong/gallery') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>Your name</label>
                <input type="text" name="name" maxlength="80" placeholder="Optional">
            </div>
            <div class="field">
                <label>A picture from the service</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
            </div>
            <div class="field">
                <label>Caption</label>
                <input type="text" name="caption" maxlength="160" placeholder="Optional">
            </div>
            <button type="submit" class="btn-navy">Upload picture</button>
        </form>
        <div class="shots">
            @foreach($photos as $photo)
                <figure>
                    <img src="{{ $photo->imageUrl() }}" alt="{{ $photo->caption ?: 'Induction service' }}">
                    @if($photo->caption || $photo->name)
                        <figcaption>{{ $photo->caption }}@if($photo->name) — {{ $photo->name }}@endif</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
        @if($photos->isEmpty())
            <p class="hint">Pictures shared here will be visible to everyone.</p>
        @endif
    </section>
    <div class="count" id="count" aria-live="polite">
        <div><strong id="cd-d">0</strong><span>Days</span></div>
        <div><strong id="cd-h">0</strong><span>Hours</span></div>
        <div><strong id="cd-m">0</strong><span>Minutes</span></div>
        <div><strong id="cd-s">0</strong><span>Seconds</span></div>
    </div>
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
    });
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
        if (pledge !== '' && (isNaN(pledge) || Number(pledge) < 5000)) {
            err.textContent = 'Enter at least 5,000 FCFA, or leave the pledge blank.';
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
