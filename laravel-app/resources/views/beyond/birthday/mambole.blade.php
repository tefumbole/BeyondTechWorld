<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wish Amah Willort a Happy Birthday</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Cinzel:wght@500;600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold2: #f3dd8a;
            --navy: #0b2a5c;
            --ink: #1c160e;
            --paper: #fffaf1;
            --muted: #6b6258;
            --card: clamp(18px, 3vw, 36px);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif;
            color: var(--ink);
            min-height: 100dvh;
            background:
                radial-gradient(60vw 40vw at 12% -10%, rgba(243,221,138,.22), transparent 55%),
                radial-gradient(50vw 36vw at 92% 8%, rgba(11,42,92,.55), transparent 50%),
                linear-gradient(180deg, #09070e 0%, #14101d 42%, #0b2a5c 100%);
        }
        .page {
            width: min(960px, calc(100% - 24px));
            margin: 0 auto;
            padding: clamp(20px, 4vw, 56px) 0 clamp(40px, 6vw, 80px);
        }
        .hero { text-align: center; color: #fff; padding: 0 8px clamp(18px, 3vw, 32px); }
        .kicker {
            letter-spacing: .28em;
            text-transform: uppercase;
            font-size: clamp(11px, 1.4vw, 13px);
            color: var(--gold);
            margin: 0 0 10px;
            font-weight: 700;
        }
        .hero h1 {
            font-family: Cinzel, serif;
            font-weight: 600;
            font-size: clamp(28px, 5.2vw, 52px);
            line-height: 1.18;
            margin: 0 auto;
            max-width: 18ch;
            color: var(--gold2);
            text-wrap: balance;
        }
        .flourish {
            width: min(220px, 40vw);
            height: 18px;
            margin: 18px auto 0;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            position: relative;
        }
        .flourish:before, .flourish:after {
            content: "";
            position: absolute;
            top: 50%;
            width: 7px;
            height: 7px;
            border: 1px solid var(--gold);
            transform: translateY(-50%) rotate(45deg);
        }
        .flourish:before { left: 18%; }
        .flourish:after { right: 18%; }
        .card {
            background: var(--paper);
            border-radius: clamp(18px, 2.4vw, 28px);
            padding: var(--card);
            box-shadow: 0 28px 70px rgba(0,0,0,.38);
            border: 1px solid rgba(212,175,55,.28);
        }
        label { display: block; font-size: clamp(13px, 1.5vw, 15px); font-weight: 700; color: var(--navy); margin: 0 0 8px; }
        label.btn, label.btn-ghost, label.btn-gold { display: inline-flex; align-items: center; justify-content: center; margin: 0; font-size: 15px; }
        .field { margin-top: clamp(16px, 2vw, 22px); }
        .hint { font-size: clamp(12px, 1.3vw, 14px); color: var(--muted); margin: 8px 0 0; }
        .phone-row {
            display: grid;
            grid-template-columns: minmax(220px, .95fr) minmax(180px, 1.15fr);
            gap: 12px;
            align-items: stretch;
        }
        input[type="tel"], input[type="text"], .cc-btn, .cc-search {
            width: 100%;
            min-height: clamp(50px, 6.5vw, 58px);
            border: 1px solid #e4d3a4;
            border-radius: 14px;
            padding: 0 16px;
            font-size: 16px;
            background: #fff;
        }
        .cc {
            position: relative;
        }
        .cc-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
            cursor: pointer;
            background: #fff;
            font-family: inherit;
        }
        .cc-btn .flag { font-size: 22px; line-height: 1; }
        .cc-btn .meta { display: flex; flex-direction: column; min-width: 0; }
        .cc-btn .name { font-weight: 700; color: var(--navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
        .cc-btn .dial { color: var(--muted); font-size: 12px; }
        .cc-caret { margin-left: auto; color: var(--gold); font-size: 11px; }
        .cc-menu {
            display: none;
            position: absolute;
            z-index: 20;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            background: #fff;
            border: 1px solid #e4d3a4;
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(0,0,0,.18);
            overflow: hidden;
        }
        .cc.is-open .cc-menu { display: block; }
        .cc-search { border: 0; border-bottom: 1px solid #efe3c4; border-radius: 0; }
        .cc-list { max-height: min(320px, 50vh); overflow: auto; margin: 0; padding: 6px; list-style: none; }
        .cc-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
        }
        .cc-list li:hover, .cc-list li.is-on { background: #fff6df; }
        .cc-list .name { flex: 1; font-weight: 600; color: var(--navy); }
        .cc-list .dial { color: var(--muted); font-weight: 700; }
        .cc-list .empty { color: var(--muted); padding: 14px; cursor: default; }
        .status { font-size: 13px; margin-top: 8px; display: none; }
        .choices { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .choice {
            appearance: none;
            border: 1px solid #e4d3a4;
            background: #fff;
            border-radius: 16px;
            padding: 16px 10px;
            cursor: pointer;
            text-align: center;
            font-family: inherit;
            min-height: 92px;
        }
        .choice strong { display: block; color: var(--navy); font-size: 16px; margin-bottom: 4px; }
        .choice span { display: block; color: var(--muted); font-size: 12px; line-height: 1.35; font-weight: 400; }
        .choice.is-on { border-color: var(--gold); background: #fff8e8; box-shadow: 0 0 0 2px rgba(212,175,55,.35); }
        .panel { display: none; margin-top: 16px; }
        .panel.is-on { display: block; }
        .selfie-box { border: 1px dashed #d4af37; border-radius: 18px; padding: clamp(14px, 2vw, 22px); background: #fff; }
        .selfie-stage {
            display: none;
            position: relative;
            width: min(220px, 56vw);
            height: min(220px, 56vw);
            margin: 0 auto 14px;
            border-radius: 50%;
            overflow: hidden;
            background: #0b2a5c;
            box-shadow: 0 0 0 6px #d4af37, 0 0 28px rgba(212,175,55,.45);
        }
        .selfie-stage.is-live, .selfie-stage.is-shot { display: block; margin-left: auto; margin-right: auto; }
        .selfie-stage video, .selfie-stage img { width: 100%; height: 100%; object-fit: cover; object-position: center 18%; display: none; }
        .selfie-stage.is-live video, .selfie-stage.is-shot img { display: block; }
        .sig-wrap { border: 1px dashed #d4af37; border-radius: 18px; padding: 12px; background: #fff; }
        .sig-wrap canvas { width: 100%; height: 180px; display: block; background: #fffdf7; border-radius: 12px; touch-action: none; cursor: crosshair; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        button, .btn { appearance: none; border: 0; border-radius: 999px; padding: 12px 18px; font-weight: 700; cursor: pointer; font-size: 15px; font-family: inherit; }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #c9a227); color: #1c160e; }
        .btn-navy { background: var(--navy); color: #fff; width: 100%; margin-top: 18px; min-height: 54px; font-size: 16px; }
        .btn-ghost { background: #fff; color: var(--navy); border: 1px solid #d4af37; }
        .err { color: #991b1b; font-size: 14px; min-height: 1em; margin-top: 10px; }
        .busy { opacity: .7; pointer-events: none; }
        .step { display: none; }
        .step.is-on { display: block; }
        @media (max-width: 700px) {
            .phone-row, .choices { grid-template-columns: 1fr; }
            .cc-menu { position: fixed; left: 12px; right: 12px; top: auto; bottom: 12px; }
        }
        @media (min-width: 900px) {
            .page { width: min(1040px, calc(100% - 48px)); }
        }
    </style>
</head>
<body>
@php
    $isoByCode = [
        '+237' => 'CM', '+250' => 'RW', '+256' => 'UG', '+254' => 'KE', '+255' => 'TZ',
        '+243' => 'CD', '+242' => 'CG', '+235' => 'TD', '+236' => 'CF', '+241' => 'GA',
        '+234' => 'NG', '+233' => 'GH', '+225' => 'CI', '+221' => 'SN', '+212' => 'MA',
        '+213' => 'DZ', '+216' => 'TN', '+20' => 'EG', '+27' => 'ZA', '+251' => 'ET',
        '+249' => 'SD', '+211' => 'SS', '+1' => 'US', '+44' => 'GB', '+33' => 'FR',
        '+49' => 'DE', '+32' => 'BE', '+31' => 'NL', '+41' => 'CH', '+39' => 'IT',
        '+34' => 'ES', '+351' => 'PT', '+91' => 'IN', '+86' => 'CN', '+81' => 'JP',
        '+82' => 'KR', '+61' => 'AU', '+64' => 'NZ', '+55' => 'BR', '+52' => 'MX',
        '+971' => 'AE', '+966' => 'SA', '+974' => 'QA', '+965' => 'KW', '+973' => 'BH',
        '+968' => 'OM', '+90' => 'TR', '+7' => 'RU', '+380' => 'UA', '+48' => 'PL',
        '+46' => 'SE', '+47' => 'NO', '+45' => 'DK', '+358' => 'FI', '+353' => 'IE',
        '+43' => 'AT', '+36' => 'HU', '+420' => 'CZ', '+40' => 'RO', '+30' => 'GR',
        '+972' => 'IL', '+92' => 'PK', '+880' => 'BD', '+63' => 'PH', '+62' => 'ID',
        '+60' => 'MY', '+65' => 'SG', '+66' => 'TH', '+84' => 'VN', '+852' => 'HK',
        '+886' => 'TW', '+54' => 'AR', '+56' => 'CL', '+57' => 'CO', '+58' => 'VE',
        '+51' => 'PE', '+593' => 'EC',
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
        <p class="kicker">18 September 2026</p>
        <h1>Wish Amah Willort a Happy Birthday</h1>
        <div class="flourish"></div>
    </div>

    <form class="card" id="bform" method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">
        <input type="hidden" name="country_code" id="countryCode" value="+237">

        <div class="step is-on" id="step1">
            <div class="field">
                <label>WhatsApp number *</label>
                <div class="phone-row">
                    <div class="cc" id="ccPicker">
                        <button type="button" class="cc-btn" id="ccBtn" aria-haspopup="listbox" aria-expanded="false">
                            <span class="flag" id="ccFlag">🇨🇲</span>
                            <span class="meta">
                                <span class="name" id="ccName">Cameroon</span>
                                <span class="dial" id="ccDial">+237</span>
                            </span>
                            <span class="cc-caret">▼</span>
                        </button>
                        <div class="cc-menu" id="ccMenu">
                            <input type="search" class="cc-search" id="ccSearch" placeholder="Search country or code" autocomplete="off">
                            <ul class="cc-list" id="ccList" role="listbox"></ul>
                        </div>
                    </div>
                    <input type="tel" name="phone" id="phone" placeholder="675321739" inputmode="numeric" autocomplete="tel" required>
                </div>
                <p class="status" id="phoneStatus"></p>
            </div>

            <div class="field">
                <label>Your name (as it should appear) *</label>
                <input type="text" name="display_name" id="displayName" required maxlength="80" placeholder="Your name">
                <p class="hint">Filled from your number when we know it. You can edit it.</p>
            </div>

            <div class="field">
                <label>What do you call her? *</label>
                <input type="text" name="call_name" id="callName" required maxlength="80" value="Ma Mbole" placeholder="Ma Mbole">
            </div>

            <p class="err" id="step1Err"></p>
            <button type="button" class="btn-navy" id="nextBtn">Next</button>
        </div>

        <div class="step" id="step2">
            <div class="field">
                <label>Add to the flyer</label>
                <input type="hidden" name="mark" id="markField" value="none">
                <div class="choices" id="choices">
                    <button type="button" class="choice" data-choice="picture">
                        <strong>Picture</strong>
                        <span>Upload or selfie</span>
                    </button>
                    <button type="button" class="choice" data-choice="sign">
                        <strong>Sign</strong>
                        <span>Draw on a sign pad</span>
                    </button>
                    <button type="button" class="choice is-on" data-choice="none">
                        <strong>None</strong>
                        <span>Send without a picture</span>
                    </button>
                </div>
            </div>

            <div class="panel" id="panelPicture">
                <div class="selfie-box">
                    <div class="selfie-stage" id="selfieStage">
                        <video id="selfieVideo" playsinline autoplay muted></video>
                        <img id="selfieShot" alt="Selfie">
                    </div>
                    <div class="toolbar">
                        <button type="button" class="btn-gold" id="camBtn">Take selfie</button>
                        <button type="button" class="btn-ghost" id="captureBtn" style="display:none;">Capture</button>
                        <button type="button" class="btn-ghost" id="retakeBtn" style="display:none;">Remove</button>
                        <label class="btn-ghost" for="selfieFile">Upload photo</label>
                    </div>
                    <input type="file" id="selfieFile" accept="image/*" style="position:absolute;left:-9999px;">
                    <p class="hint" id="selfieHint">The background is removed automatically and your face is placed in the gold ring.</p>
                </div>
            </div>

            <div class="panel" id="panelSign">
                <div class="sig-wrap">
                    <canvas id="signPad" width="640" height="180"></canvas>
                    <div class="toolbar" style="margin-top:10px;">
                        <button type="button" class="btn-ghost" id="sigClear">Clear signature</button>
                    </div>
                    <p class="hint">Sign with your finger or mouse. This is placed on the flyer.</p>
                </div>
            </div>

            <p class="err" id="formErr">@if($errors->any()){{ $errors->first() }}@endif</p>
            <button type="submit" class="btn-navy" id="goBtn">Submit</button>
            <button type="button" class="btn-ghost" id="backBtn" style="width:100%;margin-top:10px;">Back</button>
        </div>
    </form>
</div>
<script type="application/json" id="countryData">@json($countryRows)</script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script src="{{ asset('public/js/phone-name-lookup.js') }}"></script>
<script>
(function () {
    var countries = [];
    try { countries = JSON.parse(document.getElementById('countryData').textContent || '[]'); } catch (e) {}
    var hidden = document.getElementById('countryCode');
    var picker = document.getElementById('ccPicker');
    var btn = document.getElementById('ccBtn');
    var menu = document.getElementById('ccMenu');
    var search = document.getElementById('ccSearch');
    var list = document.getElementById('ccList');

    function setCountry(row) {
        if (!row) return;
        hidden.value = row.code;
        document.getElementById('ccFlag').textContent = row.flag;
        document.getElementById('ccName').textContent = row.name;
        document.getElementById('ccDial').textContent = row.code;
        hidden.dispatchEvent(new Event('change'));
    }
    function filtered() {
        var q = (search.value || '').toLowerCase().replace(/\s+/g, ' ').trim();
        if (!q) return countries;
        return countries.filter(function (c) {
            return (c.name + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
        });
    }
    function render() {
        var rows = filtered();
        list.innerHTML = '';
        if (!rows.length) {
            var empty = document.createElement('li');
            empty.className = 'empty';
            empty.textContent = 'No country matches.';
            list.appendChild(empty);
            return;
        }
        rows.forEach(function (c) {
            var li = document.createElement('li');
            if (c.code === hidden.value) li.className = 'is-on';
            var flag = document.createElement('span');
            flag.className = 'flag';
            flag.textContent = c.flag;
            var name = document.createElement('span');
            name.className = 'name';
            name.textContent = c.name;
            var dial = document.createElement('span');
            dial.className = 'dial';
            dial.textContent = c.code;
            li.appendChild(flag);
            li.appendChild(name);
            li.appendChild(dial);
            li.addEventListener('click', function () {
                setCountry(c);
                picker.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            });
            list.appendChild(li);
        });
    }
    btn.addEventListener('click', function () {
        var open = picker.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            render();
            search.value = '';
            search.focus();
        }
    });
    search.addEventListener('input', render);
    document.addEventListener('click', function (e) {
        if (!picker.contains(e.target)) {
            picker.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    render();

    attachPhoneNameLookup({
        url: @json($lookupUrl),
        phone: '#phone',
        code: '#countryCode',
        name: '#displayName',
        status: '#phoneStatus'
    });

    var step1 = document.getElementById('step1');
    var step2 = document.getElementById('step2');
    var nextBtn = document.getElementById('nextBtn');
    var backBtn = document.getElementById('backBtn');
    var video = document.getElementById('selfieVideo');
    var shot = document.getElementById('selfieShot');
    var stage = document.getElementById('selfieStage');
    var camBtn = document.getElementById('camBtn');
    var captureBtn = document.getElementById('captureBtn');
    var retakeBtn = document.getElementById('retakeBtn');
    var fileInput = document.getElementById('selfieFile');
    var hint = document.getElementById('selfieHint');
    var form = document.getElementById('bform');
    var err = document.getElementById('formErr');
    var step1Err = document.getElementById('step1Err');
    var goBtn = document.getElementById('goBtn');
    var stream = null;
    var selfieFile = null;
    var cutoutMod = null;
    var mark = 'none';
    var signPad = null;

    function currentMark() {
        return mark;
    }
    function setMark(next) {
        mark = next;
        document.getElementById('markField').value = next;
        document.querySelectorAll('.choice').forEach(function (el) {
            el.classList.toggle('is-on', el.getAttribute('data-choice') === next);
        });
        document.getElementById('panelPicture').classList.toggle('is-on', next === 'picture');
        document.getElementById('panelSign').classList.toggle('is-on', next === 'sign');
        if (next !== 'picture') stopCam();
        if (next === 'sign') {
            window.setTimeout(fitSignPad, 50);
        }
    }
    document.getElementById('choices').addEventListener('click', function (e) {
        var btn = e.target.closest('.choice');
        if (!btn) return;
        setMark(btn.getAttribute('data-choice'));
    });

    function fitSignPad() {
        var canvas = document.getElementById('signPad');
        if (!canvas || typeof SignaturePad === 'undefined') return;
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var w = canvas.offsetWidth || 640;
        var h = 180;
        canvas.width = Math.floor(w * ratio);
        canvas.height = Math.floor(h * ratio);
        canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
        if (!signPad) {
            signPad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255,253,247)',
                penColor: 'rgb(11, 42, 92)',
                minWidth: 1.2,
                maxWidth: 2.8
            });
            document.getElementById('sigClear').addEventListener('click', function () {
                signPad.clear();
            });
        } else {
            signPad.clear();
        }
    }

    import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/+esm').then(function (mod) {
        cutoutMod = mod;
    }).catch(function () {});

    function showStep(n) {
        step1.classList.toggle('is-on', n === 1);
        step2.classList.toggle('is-on', n === 2);
        window.scrollTo(0, 0);
    }

    nextBtn.addEventListener('click', function () {
        step1Err.textContent = '';
        var phone = (document.getElementById('phone').value || '').replace(/\D/g, '');
        if (phone.length < 8) {
            step1Err.textContent = 'Enter a valid WhatsApp number.';
            return;
        }
        if (!document.getElementById('displayName').value.trim()) {
            step1Err.textContent = 'Enter the name that should appear.';
            return;
        }
        if (!document.getElementById('callName').value.trim()) {
            step1Err.textContent = 'Enter what you call her.';
            return;
        }
        showStep(2);
        if (currentMark() === 'sign') fitSignPad();
    });
    backBtn.addEventListener('click', function () {
        stopCam();
        showStep(1);
    });

    function setStage(mode) {
        stage.classList.remove('is-live', 'is-shot');
        if (mode) stage.classList.add(mode);
    }
    function stopCam() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
    }
    function blobToFile(blob, name) {
        try {
            return new File([blob], name, { type: blob.type || 'image/png' });
        } catch (e) {
            return blob;
        }
    }

    function cropFace(blob) {
        return new Promise(function (resolve) {
            var url = URL.createObjectURL(blob);
            var img = new Image();
            img.onload = function () {
                var w = img.naturalWidth;
                var h = img.naturalHeight;
                var c = document.createElement('canvas');
                c.width = w;
                c.height = h;
                var ctx = c.getContext('2d');
                ctx.drawImage(img, 0, 0);
                URL.revokeObjectURL(url);
                var data = ctx.getImageData(0, 0, w, h).data;
                var minX = w, minY = h, maxX = 0, maxY = 0, found = 0;
                var step = w * h > 400000 ? 2 : 1;
                for (var y = 0; y < h; y += step) {
                    for (var x = 0; x < w; x += step) {
                        var a = data[((y * w) + x) * 4 + 3];
                        if (a < 40) continue;
                        found++;
                        if (x < minX) minX = x;
                        if (y < minY) minY = y;
                        if (x > maxX) maxX = x;
                        if (y > maxY) maxY = y;
                    }
                }
                var total = Math.ceil(w / step) * Math.ceil(h / step);
                if (!(found > 40 && found < total * 0.92)) {
                    resolve(blob);
                    return;
                }
                var bw = Math.max(1, maxX - minX);
                var bh = Math.max(1, maxY - minY);
                var headH = Math.max(bw * 0.95, bh * 0.58);
                var cx = (minX + maxX) / 2;
                var cy = minY + headH * 0.42;
                var side = Math.round(Math.max(bw, headH) * 1.16);
                var sx = Math.round(cx - side / 2);
                var sy = Math.round(cy - side / 2);
                if (sx < 0) sx = 0;
                if (sy < 0) sy = 0;
                if (sx + side > w) sx = Math.max(0, w - side);
                if (sy + side > h) sy = Math.max(0, h - side);
                side = Math.min(side, w - sx, h - sy);
                var out = document.createElement('canvas');
                out.width = side;
                out.height = side;
                out.getContext('2d').drawImage(img, sx, sy, side, side, 0, 0, side, side);
                out.toBlob(function (cut) { resolve(cut || blob); }, 'image/png');
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(blob); };
            img.src = url;
        });
    }

    async function cutout(blob) {
        hint.textContent = 'Removing the background…';
        try {
            if (!cutoutMod) {
                cutoutMod = await import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/+esm');
            }
            var fn = cutoutMod.removeBackground || cutoutMod.default || cutoutMod;
            var out = await fn(blob, {
                publicPath: 'https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/dist/',
                device: 'cpu',
                model: 'small',
                output: { format: 'image/png', quality: 1 }
            });
            hint.textContent = 'Placing your face in the ring…';
            return cropFace(out);
        } catch (e) {
            hint.textContent = 'We will cut the background on the flyer.';
            return blob;
        }
    }

    async function useImageBlob(blob) {
        camBtn.classList.add('busy');
        var cut = await cutout(blob);
        selfieFile = blobToFile(cut, 'selfie.png');
        var url = URL.createObjectURL(cut);
        shot.src = url;
        setStage('is-shot');
        captureBtn.style.display = 'none';
        camBtn.style.display = 'none';
        camBtn.classList.remove('busy');
        retakeBtn.style.display = 'inline-flex';
        hint.textContent = 'Background removed. Your face is ready for the flyer.';
        stopCam();
    }

    camBtn.addEventListener('click', function () {
        err.textContent = '';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function (s) {
            stream = s;
            video.srcObject = s;
            setStage('is-live');
            captureBtn.style.display = 'inline-flex';
            camBtn.style.display = 'none';
        }).catch(function () {
            err.textContent = 'Camera was blocked. Choose a photo instead.';
        });
    });

    captureBtn.addEventListener('click', function () {
        var c = document.createElement('canvas');
        c.width = video.videoWidth || 720;
        c.height = video.videoHeight || 720;
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
            if (blob) useImageBlob(blob);
        }, 'image/jpeg', 0.9);
    });

    retakeBtn.addEventListener('click', function () {
        selfieFile = null;
        shot.removeAttribute('src');
        setStage('');
        retakeBtn.style.display = 'none';
        camBtn.style.display = 'inline-flex';
        hint.textContent = 'The background is removed automatically and your face is placed in the gold ring.';
    });

    fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) return;
        useImageBlob(f);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.textContent = '';
        if (!document.getElementById('displayName').value.trim()) {
            err.textContent = 'Enter the name that should appear.';
            showStep(1);
            return;
        }
        goBtn.classList.add('busy');
        goBtn.textContent = 'Sending…';
        var body = new FormData(form);
        body.set('mark', currentMark());
        if (currentMark() === 'picture') {
            if (!selfieFile) {
                goBtn.classList.remove('busy');
                goBtn.textContent = 'Submit';
                err.textContent = 'Upload or take a picture, or choose None.';
                return;
            }
            body.set('selfie', selfieFile, 'selfie.png');
            sendForm(body);
            return;
        }
        if (currentMark() === 'sign') {
            if (!signPad || signPad.isEmpty()) {
                goBtn.classList.remove('busy');
                goBtn.textContent = 'Submit';
                err.textContent = 'Please sign first, or choose None.';
                return;
            }
            signPad.canvas.toBlob(function (blob) {
                if (blob) body.set('selfie', blob, 'signature.png');
                sendForm(body);
            }, 'image/png');
            return;
        }
        sendForm(body);
    });

    function sendForm(body) {
        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) {
            return r.text().then(function (t) {
                var j = {};
                try { j = JSON.parse(t); } catch (e2) {}
                return { ok: r.ok, j: j };
            });
        }).then(function (res) {
            if (res.j && res.j.redirect) {
                window.location.href = res.j.redirect;
                return;
            }
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Submit';
            var msg = (res.j && res.j.message) ? res.j.message : 'Could not create the flyer.';
            if (res.j && res.j.errors) {
                var first = Object.keys(res.j.errors)[0];
                if (first && res.j.errors[first][0]) msg = res.j.errors[first][0];
            }
            err.textContent = msg;
        }).catch(function () {
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Submit';
            err.textContent = 'Network error. Please try again.';
        });
    }
})();
</script>
</body>
</html>
