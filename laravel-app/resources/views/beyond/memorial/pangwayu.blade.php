<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pa Ngwayu Francis · Funeral programme</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-2: #f0d57a;
            --ink: #1c160e;
            --paper: #f7f1e4;
            --muted: #5c5348;
            --bg: #1a1410;
            --card: #fffdf8;
            --line: #e4d3a4;
            --soft: #2a2118;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif;
            color: var(--ink);
            background: var(--bg);
        }
        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(260px, 38%) 1fr;
        }
        .portrait {
            position: sticky;
            top: 0;
            height: 100vh;
            background: #0d0a08;
            overflow: hidden;
        }
        .portrait img {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; object-position: center 15%;
            opacity: 0; transition: opacity 1.4s ease;
        }
        .portrait img.on { opacity: 1; }
        .portrait .wash {
            position: absolute; inset: 0;
            background:
                linear-gradient(180deg, rgba(232,150,60,.18) 0%, transparent 40%, rgba(10,8,14,.55) 100%),
                linear-gradient(90deg, transparent 70%, rgba(26,20,16,.35) 100%);
        }
        .portrait .caption {
            position: absolute; left: 18px; right: 18px; bottom: 20px;
            color: #f7f1e4;
            text-shadow: 0 2px 12px rgba(0,0,0,.55);
        }
        .portrait .caption strong {
            display: block;
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
        }
        .main {
            background: var(--paper);
            padding: 28px 32px 40px;
            overflow: auto;
        }
        .memorial-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 4px;
        }
        .memorial-head > div { min-width: 0; flex: 1; }
        .eulogy-top {
            flex-shrink: 0;
            margin-top: 0;
            padding: 10px 16px;
            white-space: nowrap;
            font-size: 14px;
        }
        .kicker { letter-spacing: .24em; text-transform: uppercase; color: #6b5410; font-size: 12px; margin: 0 0 8px; font-weight: 700; }
        h1 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(34px, 5vw, 52px);
            line-height: 1.05; margin: 0 0 8px; font-weight: 700; color: #14100a;
        }
        .meta { color: #3c3428; margin: 0 0 20px; font-size: 17px; line-height: 1.45; }
        .rings { display: grid; grid-template-columns: repeat(4, 86px); gap: 12px; margin-bottom: 22px; }
        .ring {
            width: 86px; height: 86px; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: radial-gradient(circle at center, #fffdf8 56%, transparent 57%),
                        conic-gradient(#b8860b var(--p, 0%), #efe4c6 0);
            border: 1px solid #d4bf86;
        }
        .ring b { font-family: "Cormorant Garamond", serif; font-size: 28px; color: #14100a; line-height: 1; }
        .ring span { font-size: 10px; letter-spacing: .12em; color: #4a4238; text-transform: uppercase; font-weight: 700; }
        .progress { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; }
        .progress-top { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; }
        .raised { color: #b23b32; font-weight: 700; }
        .target { color: var(--ink); font-weight: 600; }
        .bar { height: 9px; border-radius: 999px; background: #efe6d2; margin: 8px 0 6px; overflow: hidden; }
        .bar > i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #e85d4c, #d4af37); }
        .pct { font-size: 12px; color: #b23b32; }
        .filters { display: flex; gap: 8px; margin: 0 0 12px; }
        .filters button {
            border: 1px solid #c9b36a; background: #fff; color: var(--ink);
            border-radius: 999px; padding: 6px 13px; font-weight: 700; cursor: pointer;
        }
        .filters button.on { background: var(--gold); color: #1a1408; border-color: var(--gold); }
        .group {
            margin: 18px 0 10px;
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 10px;
            color: #fff;
        }
        .group h2 {
            margin: 0;
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(22px, 3.2vw, 30px);
            line-height: 1.1;
            font-weight: 700;
        }
        .group .tot { text-align: right; font-size: 13px; font-weight: 700; line-height: 1.35; }
        .group .tot b { display: block; font-size: 18px; }
        .group-food { background: linear-gradient(135deg, #c45c26, #e39b2d); }
        .group-takeaway { background: linear-gradient(135deg, #1f7a4d, #3cb371); }
        .group-logistics { background: linear-gradient(135deg, #1e4d8c, #3d7ad6); }
        .group-other { background: linear-gradient(135deg, #6b2d7a, #b455c4); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .card {
            text-align: left; width: 100%;
            background: #fff; border: 1px solid var(--line); border-radius: 12px;
            padding: 10px 12px; color: inherit; cursor: pointer;
        }
        .card[disabled] { opacity: .7; cursor: default; }
        .card h3 { margin: 0 0 3px; font-size: 18px; font-weight: 800; color: #1a1408; }
        .card .amt { color: #6d5410; font-size: 12px; font-weight: 600; }
        .mini { height: 8px; background: #efe6d2; border-radius: 99px; margin: 8px 0 6px; overflow: hidden; }
        .mini > i {
            display: block; height: 100%; width: 0;
            background: linear-gradient(90deg, #4ade80, #16a34a);
            border-radius: inherit;
            transition: width .25s ease;
        }
        .mini.is-full > i { background: #16a34a; }
        .who { font-size: 12px; color: var(--muted); }
        .taken { color: #1d7a45; font-size: 12px; font-weight: 700; }
        .flash { background: #e8f6ea; border: 1px solid #8dca98; color: #1d5c2c; padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; }
        .flash.bad { background: #fdecea; border-color: #e7a399; color: #8a2a20; }
        .eulogies {
            margin-top: 28px;
            padding-top: 8px;
        }
        .eulogies-head { margin-bottom: 16px; }
        .eulogies-title {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(30px, 4vw, 42px);
            line-height: 1.1;
            margin: 0 0 8px;
            color: #14100a;
            font-weight: 700;
        }
        .eulogies-lead {
            margin: 0 0 16px;
            color: #3c3428;
            font-size: 18px;
            line-height: 1.6;
            max-width: 34em;
        }
        .eulogies-count {
            display: inline-block;
            margin: 0 0 16px;
            color: #6b5410;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .eulogy-box {
            background: #fffdf8;
            border: 1px solid #d8c89a;
            border-radius: 20px;
            padding: 24px 22px 18px;
            margin-bottom: 18px;
            box-shadow: 0 10px 28px rgba(40, 28, 8, .06);
        }
        .eulogy-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #efe4c6;
        }
        .eulogy-head cite {
            display: block;
            font-family: "Cormorant Garamond", serif;
            font-style: normal;
            font-size: clamp(22px, 4vw, 28px);
            font-weight: 700;
            color: #14100a;
            line-height: 1.15;
        }
        .eulogy-date {
            display: block;
            margin-top: 3px;
            color: #6b5410;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .eulogy-body { max-width: 40em; }
        .eulogy-body p {
            margin: 0 0 1.05em;
            color: #1c160e;
            font-size: clamp(17px, 2.4vw, 19px);
            line-height: 1.8;
        }
        .eulogy-body p:last-child { margin-bottom: 0; }
        .eulogy-body p:first-child::first-letter {
            font-family: "Cormorant Garamond", serif;
            font-size: 2.55em;
            float: left;
            line-height: .82;
            padding: 8px 10px 0 0;
            color: #8a6d1a;
            font-weight: 700;
        }
        .eulogy-who {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #efe4c6;
            margin-top: 18px;
            padding-top: 14px;
        }
        .eulogy-who cite {
            font-family: "Cormorant Garamond", serif;
            font-style: italic;
            font-size: clamp(18px, 3vw, 22px);
            font-weight: 600;
            color: #14100a;
        }
        .eulogy-box .sig { height: 58px; max-width: 200px; object-fit: contain; background: transparent; }
        .eulogy-box .selfie { height: 56px; width: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #d4af37; }
        .name-choices { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
        .name-choice {
            text-align: left; border: 1px solid #d8c89a; background: #fff; color: #14100a;
            border-radius: 12px; padding: 10px 12px; cursor: pointer; font-family: inherit;
        }
        .name-choice span { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #6b5410; font-weight: 700; }
        .name-choice strong { display: block; margin-top: 4px; font-size: 14px; }
        .name-choice.on { border-color: #d4af37; box-shadow: 0 0 0 2px rgba(212,175,55,.25); background: #fff8e0; }
        .eulogies-empty {
            background: #fffdf8;
            border: 1px dashed #c9b36a;
            border-radius: 16px;
            padding: 22px 18px;
            color: #3c3428;
            font-size: 17px;
            line-height: 1.55;
        }
        .hint { color: #6b5410; font-size: 12px; margin: 6px 0 0; line-height: 1.45; }
        .sig-pad { width: 100%; height: 150px; border: 1px dashed #c9b36a; border-radius: 10px; background: #fff; touch-action: none; }
        #eulogyModal .sheet { max-width: 560px; }
        #eulogyModal textarea {
            width: 100%;
            min-height: 180px;
            border: 1px solid #d8c89a;
            border-radius: 10px;
            padding: 14px 14px;
            font-size: 18px;
            line-height: 1.6;
            font-family: inherit;
            color: #1c160e;
            background: #fff;
            resize: vertical;
        }
        .actions3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 16px; }
        @media (max-width: 520px) { .actions3 { grid-template-columns: 1fr; } }
        .foot { margin-top: 22px; text-align: center; color: var(--muted); font-size: 13px; }
        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .portrait { position: relative; min-height: 220px; height: 38vw; max-height: 280px; }
            .portrait .caption { display: none; }
            .main { padding: 16px 14px 28px; }
            .rings { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
            .ring { width: 100%; height: auto; aspect-ratio: 1; }
            .ring b { font-size: 24px; }
            .eulogy-box { padding: 18px 14px 14px; }
            .eulogy-body p { font-size: 17px; }
            .name-choices { grid-template-columns: 1fr; }
            .eulogy-top { padding: 8px 12px; font-size: 13px; }
            .grid { grid-template-columns: 1fr; }
            .group { flex-direction: column; align-items: flex-start; }
            .group .tot { text-align: left; }
        }
        .modal {
            display: none; position: fixed; inset: 0; z-index: 20;
            background: rgba(8, 6, 10, .72); padding: 18px 12px; overflow: auto;
        }
        .modal.on { display: block; }
        .sheet {
            max-width: 440px; margin: 24px auto; background: #fffdf8; border: 1px solid var(--line);
            border-radius: 18px; padding: 18px 16px 16px; color: var(--ink);
        }
        .sheet h2 { font-family: "Cormorant Garamond", serif; margin: 0 0 12px; }
        label { display: block; font-size: 12px; color: var(--muted); margin: 10px 0 4px; font-weight: 700; }
        input, select {
            width: 100%; border: 1px solid #d8c89a; background: #fff; color: var(--ink);
            border-radius: 10px; padding: 11px 12px; font-size: 16px;
        }
        .phone-row { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .btn {
            border: 0; border-radius: 12px; padding: 12px 10px; font-weight: 700; cursor: pointer; font-size: 15px;
        }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #d4af37); color: #1a1408; }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        .err { color: #b23b32; font-size: 13px; min-height: 18px; margin-top: 8px; }
        .close { float: right; background: none; border: 0; color: var(--muted); font-size: 22px; cursor: pointer; }
        @media (max-width: 420px) {
            .ring b { font-size: 22px; }
            .phone-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="shell">
<aside class="portrait" id="bg">
    @foreach($photos as $i => $src)
        <img src="{{ $src }}" alt="Pa Ngwayu Francis" class="{{ $i === 0 ? 'on' : '' }}">
    @endforeach
    <div class="wash"></div>
    <div class="caption">
        <strong>Pa Ngwayu Francis</strong>
        <span>73 years</span>
    </div>
</aside>

<main class="main">
    @if($flashPay === 'ok')
        <div class="flash">Payment received. Thank you.</div>
    @elseif($flashPay === 'failed')
        <div class="flash bad">Payment did not complete. You can pledge or try again.</div>
    @elseif($flashPay === 'pending')
        <div class="flash">Payment is pending. It will show once confirmed.</div>
    @endif
    @if($flashEulogy === 'ok')
        <div class="flash">Your eulogy was received. Thank you.</div>
    @endif

    <header class="memorial-head">
        <div>
            <p class="kicker">In loving memory</p>
            <h1>Pa Ngwayu Francis</h1>
            <p class="meta">73 years · Funeral 26 September 2026</p>
        </div>
        <button type="button" class="btn btn-gold eulogy-top" id="openEulogy">Leave a eulogy</button>
    </header>

    <div class="rings" id="rings">
        <div class="ring" style="--p:0%"><b id="d">0</b><span id="dLabel">Days</span></div>
        <div class="ring" style="--p:0%"><b id="h">0</b><span id="hLabel">Hours</span></div>
        <div class="ring" style="--p:0%"><b id="m">0</b><span id="mLabel">Mins</span></div>
        <div class="ring" style="--p:0%"><b id="s">0</b><span id="sLabel">Secs</span></div>
    </div>

    <div class="progress">
        <div class="progress-top">
            <span class="raised">RAISED {{ number_format($raised) }} F CFA</span>
            <span class="target">TARGET {{ number_format($target) }} F CFA</span>
        </div>
        <div class="bar"><i id="barFill" style="width: {{ min(100, $percent) }}%"></i></div>
        <div class="pct">{{ $percent }}% · {{ number_format(max(0, $target - $raised)) }} still needed</div>
    </div>

    <div class="filters">
        <button type="button" class="on" data-filter="all">All items</button>
        <button type="button" data-filter="left">Remaining</button>
        <button type="button" data-filter="taken">Taken items</button>
    </div>

    <div id="list"></div>

    <section class="eulogies" id="eulogies">
        <div class="eulogies-head">
            <p class="kicker">Words of remembrance</p>
            <h2 class="eulogies-title">Eulogies</h2>
            <p class="eulogies-lead">Share a few words for Pa Ngwayu Francis. Enter your phone and your name will appear, or type it yourself. You may add a signature before you submit.</p>
            <span class="eulogies-count">{{ count($eulogies) }} {{ count($eulogies) === 1 ? 'eulogy' : 'eulogies' }} written</span>
        </div>
        @forelse($eulogies as $eu)
            <article class="eulogy-box">
                <header class="eulogy-head">
                    @if(!empty($eu['has_selfie']))
                        <img class="selfie" src="{{ $eu['selfie'] }}" alt="{{ $eu['name'] }}">
                    @endif
                    <div>
                        <cite>{{ $eu['name'] }}</cite>
                        @if(!empty($eu['when']))
                            <span class="eulogy-date">{{ $eu['when'] }}</span>
                        @endif
                    </div>
                </header>
                <div class="eulogy-body">
                    @foreach(($eu['paragraphs'] ?? [$eu['body']]) as $para)
                        <p>{{ $para }}</p>
                    @endforeach
                </div>
                <footer class="eulogy-who">
                    <cite>{{ $eu['name'] }}</cite>
                    @if($eu['has_signature'])
                        <img class="sig" src="{{ $eu['signature'] }}" alt="Signature of {{ $eu['name'] }}">
                    @endif
                </footer>
            </article>
        @empty
            <p class="eulogies-empty">Be the first to leave a eulogy for Pa Ngwayu Francis.</p>
        @endforelse
    </section>

    <p class="foot">Developed By: Sr. Engr. Tefu R. Mbole |
        <a href="https://wa.me/237675321739" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">+237 675-321-739</a>
    </p>
</main>
</div>

<div class="modal" id="modal">
    <div class="sheet">
        <button type="button" class="close" id="closeModal">&times;</button>
        <h2 id="modalTitle">Pledge</h2>
        <form id="pledgeForm">
            <input type="hidden" name="item_id" id="itemId">
            <label>Phone number *</label>
            <div class="phone-row">
                <select name="country_code" id="countryCode">
                    @foreach($countries as $c)
                        <option value="{{ $c['code'] }}" {{ $c['code'] === '+237' ? 'selected' : '' }}>{{ $c['code'] }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phone" id="phone" placeholder="677318405" autocomplete="tel" required>
            </div>
            <label>Name *</label>
            <input type="text" name="name" id="name" required>
            <label>Amount (XAF) *</label>
            <input type="number" name="amount" id="amount" min="100" step="1" required>
            <div class="err" id="formErr"></div>
            <div class="actions3">
                <button type="submit" class="btn btn-ghost" data-action="pledge">Pledge</button>
                <button type="submit" class="btn btn-gold" data-action="pay" data-pay="momo">Pay MoMo / OM</button>
                <button type="submit" class="btn btn-gold" data-action="pay" data-pay="visa">Pay VISA</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="eulogyModal">
    <div class="sheet">
        <button type="button" class="close" id="closeEulogy">&times;</button>
        <p class="kicker">In loving memory</p>
        <h2>Leave a eulogy</h2>
        <form id="eulogyForm">
            <label>Phone number *</label>
            <div class="phone-row">
                <select name="country_code" id="euCountry">
                    @foreach($countries as $c)
                        <option value="{{ $c['code'] }}" {{ $c['code'] === '+237' ? 'selected' : '' }}>{{ $c['code'] }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phone" id="euPhone" placeholder="677318405" required>
            </div>
            <label>Name *</label>
            <div id="euNameChoices" class="name-choices" hidden></div>
            <input type="text" name="name" id="euName" required>
            <p class="hint">We use the Mobile Money name when we find it. You can tap the other name or edit this field.</p>
            <label>Eulogy *</label>
            <textarea name="body" id="euBody" rows="7" required placeholder="A few words for Pa Ngwayu Francis…"></textarea>
            <label>Signature (optional)</label>
            <canvas id="sigPad" class="sig-pad"></canvas>
            <button type="button" class="btn btn-ghost" id="sigClear" style="margin-top:8px;">Clear signature</button>
            <input type="hidden" name="signature" id="euSig">
            <div class="err" id="euErr"></div>
            <div class="actions" style="margin-top:12px;">
                <button type="button" class="btn btn-ghost" id="euCancel">Cancel</button>
                <button type="submit" class="btn btn-gold">Submit eulogy</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var ITEMS = @json($items);
    var GROUPS = @json($groups);
    var LOOKUP = @json($lookupUrl);
    var POST = @json($pledgeUrl);
    var EULOGY = @json($eulogyUrl);
    var END = new Date(@json($funeralAt)).getTime();
    var filter = 'all';
    var action = 'pledge';
    var payMethod = 'momo';
    var current = null;
    var list = document.getElementById('list');
    var modal = document.getElementById('modal');
    var imgs = document.querySelectorAll('#bg img');
    var idx = 0;

    setInterval(function () {
        if (!imgs.length) return;
        imgs[idx].classList.remove('on');
        idx = (idx + 1) % imgs.length;
        imgs[idx].classList.add('on');
    }, 8000);

    function tick() {
        var left = Math.max(0, END - Date.now());
        var d = Math.floor(left / 86400000);
        var h = Math.floor((left % 86400000) / 3600000);
        var m = Math.floor((left % 3600000) / 60000);
        var s = Math.floor((left % 60000) / 1000);
        document.getElementById('d').textContent = d;
        document.getElementById('h').textContent = h;
        document.getElementById('m').textContent = m;
        document.getElementById('s').textContent = s;
        document.getElementById('dLabel').textContent = d === 1 ? 'Day' : 'Days';
        document.getElementById('hLabel').textContent = h === 1 ? 'Hour' : 'Hours';
        document.getElementById('mLabel').textContent = m === 1 ? 'Min' : 'Mins';
        document.getElementById('sLabel').textContent = s === 1 ? 'Sec' : 'Secs';
        var rings = document.querySelectorAll('.ring');
        var parts = [d / 30, h / 24, m / 60, s / 60];
        for (var i = 0; i < rings.length; i++) {
            rings[i].style.setProperty('--p', Math.min(100, Math.round(parts[i] * 100)) + '%');
        }
    }
    tick();
    setInterval(tick, 1000);

    function money(n) { return Number(n || 0).toLocaleString('en-US'); }

    function render() {
        var html = '';
        Object.keys(GROUPS).forEach(function (key) {
            var rows = ITEMS.filter(function (it) {
                if (it.category !== key) return false;
                if (filter === 'left' && it.covered) return false;
                if (filter === 'taken' && !it.covered && !(Number(it.committed) > 0)) return false;
                return true;
            });
            if (!rows.length) return;
            var allInCat = ITEMS.filter(function (it) { return it.category === key; });
            var catTarget = 0;
            var catRaised = 0;
            allInCat.forEach(function (it) {
                if (it.target) catTarget += Number(it.target);
                catRaised += Number(it.committed || 0);
            });
            var totLine = catTarget
                ? (money(catRaised) + ' / ' + money(catTarget) + ' XAF')
                : (catRaised ? (money(catRaised) + ' XAF pledged') : 'Open');
            html += '<div class="group group-' + key + '"><h2>' + escapeHtml(GROUPS[key]) + '</h2>'
                + '<div class="tot">Section total<b>' + totLine + '</b></div></div><div class="grid">';
            rows.forEach(function (it) {
                var committed = Number(it.committed || 0);
                var fill = 0;
                if (it.covered) {
                    fill = 100;
                } else if (it.target) {
                    fill = Math.min(100, Math.round((committed / Number(it.target)) * 100));
                } else if (committed > 0) {
                    fill = 100;
                }
                var amt = it.is_open
                    ? (committed ? ('Pledged ' + money(committed) + ' XAF') : 'Open amount')
                    : (money(it.remaining) + ' of ' + money(it.target) + ' XAF left'
                        + (fill > 0 && fill < 100 ? ' · ' + fill + '% taken' : ''));
                var who = it.names && it.names.length ? it.names.join(', ') : '';
                html += '<button type="button" class="card" data-id="' + it.id + '"' + (it.covered ? ' disabled' : '') + '>'
                    + '<h3>' + escapeHtml(it.name) + '</h3>'
                    + '<div class="amt">' + amt + '</div>'
                    + '<div class="mini' + (fill >= 100 ? ' is-full' : '') + '"><i style="width:' + fill + '%"></i></div>'
                    + (it.covered ? '<div class="taken">Covered' + (who ? ' · ' + escapeHtml(who) : '') + '</div>'
                        : (who ? '<div class="who">Taken by ' + escapeHtml(who) + '</div>' : ''))
                    + '</button>';
            });
            html += '</div>';
        });
        var empty = 'No items to show.';
        if (filter === 'left') empty = 'All items are covered.';
        if (filter === 'taken') empty = 'No items have been taken yet.';
        list.innerHTML = html || '<p class="meta">' + empty + '</p>';
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    document.querySelector('.filters').addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        filter = btn.getAttribute('data-filter');
        Array.prototype.forEach.call(document.querySelectorAll('.filters button'), function (b) {
            b.classList.toggle('on', b === btn);
        });
        render();
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.card');
        if (!btn || btn.disabled) return;
        var id = Number(btn.getAttribute('data-id'));
        current = ITEMS.filter(function (it) { return it.id === id; })[0];
        if (!current) return;
        document.getElementById('modalTitle').textContent = current.name;
        document.getElementById('itemId').value = current.id;
        document.getElementById('amount').value = current.remaining || '';
        document.getElementById('formErr').textContent = '';
        modal.classList.add('on');
        document.getElementById('phone').focus();
    });

    document.getElementById('closeModal').onclick = function () { modal.classList.remove('on'); };
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('on'); });

    var lookupTimer = null;
    function lookup() {
        var phone = document.getElementById('phone').value;
        var code = document.getElementById('countryCode').value;
        if (String(phone).replace(/\D/g, '').length < 8) return;
        fetch(LOOKUP + '?country_code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data) return;
            var n = data.original_name || data.name || data.system_name;
            if (n) document.getElementById('name').value = n;
        }).catch(function () {});
    }
    document.getElementById('phone').addEventListener('input', function () {
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookup, 450);
    });
    document.getElementById('phone').addEventListener('blur', lookup);

    document.getElementById('pledgeForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('formErr');
        err.textContent = '';
        var body = new FormData(e.target);
        body.append('action', action);
        if (action === 'pay') body.append('pay_method', payMethod);
        fetch(POST, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (res.j && res.j.redirect) {
                  window.location = res.j.redirect;
                  return;
              }
              if (!res.ok || !res.j.ok) {
                  err.textContent = (res.j && res.j.message) || 'Could not save.';
                  return;
              }
              window.location.reload();
          }).catch(function () { err.textContent = 'Network error. Try again.'; });
    });
    Array.prototype.forEach.call(document.querySelectorAll('#pledgeForm [data-action]'), function (btn) {
        btn.addEventListener('click', function () {
            action = btn.getAttribute('data-action');
            payMethod = btn.getAttribute('data-pay') || 'momo';
        });
    });

    var euModal = document.getElementById('eulogyModal');
    var canvas = document.getElementById('sigPad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    function sizeCanvas() {
        var r = canvas.getBoundingClientRect();
        canvas.width = Math.floor(r.width);
        canvas.height = Math.floor(r.height);
        ctx.strokeStyle = '#1a1408';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
    }
    function pos(ev) {
        var r = canvas.getBoundingClientRect();
        var t = ev.touches ? ev.touches[0] : ev;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    canvas.addEventListener('mousedown', function (ev) { drawing = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('mousemove', function (ev) { if (!drawing) return; var p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup', function () { drawing = false; });
    canvas.addEventListener('mouseleave', function () { drawing = false; });
    canvas.addEventListener('touchstart', function (ev) { ev.preventDefault(); drawing = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
    canvas.addEventListener('touchmove', function (ev) { ev.preventDefault(); if (!drawing) return; var p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); }, { passive: false });
    canvas.addEventListener('touchend', function () { drawing = false; });
    document.getElementById('sigClear').onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); };
    document.getElementById('openEulogy').onclick = function () { euModal.classList.add('on'); sizeCanvas(); document.getElementById('euPhone').focus(); };
    document.getElementById('closeEulogy').onclick = function () { euModal.classList.remove('on'); };
    document.getElementById('euCancel').onclick = function () { euModal.classList.remove('on'); };
    euModal.addEventListener('click', function (e) { if (e.target === euModal) euModal.classList.remove('on'); });

    var euTimer = null;
    function applyNameLookup(data, nameEl, choicesEl) {
        if (!data || !nameEl) return;
        var momo = (data.original_name || '').trim();
        var system = (data.system_name || '').trim();
        var chosen = momo || data.name || system;
        if (chosen) nameEl.value = chosen;
        if (!choicesEl) return;
        choicesEl.innerHTML = '';
        var showMomo = !!momo;
        var showSystem = !!system && system.toLowerCase() !== momo.toLowerCase();
        if (!showMomo && !showSystem) {
            choicesEl.hidden = true;
            return;
        }
        function addChoice(label, value, on) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'name-choice' + (on ? ' on' : '');
            btn.innerHTML = '<span>' + label + '</span><strong></strong>';
            btn.querySelector('strong').textContent = value;
            btn.addEventListener('click', function () {
                nameEl.value = value;
                Array.prototype.forEach.call(choicesEl.querySelectorAll('.name-choice'), function (el) {
                    el.classList.toggle('on', el === btn);
                });
            });
            choicesEl.appendChild(btn);
        }
        if (showMomo) addChoice('Mobile Money', momo, true);
        if (showSystem) addChoice('Our records', system, !showMomo);
        choicesEl.hidden = false;
    }
    function lookupEu() {
        var phone = document.getElementById('euPhone').value;
        var code = document.getElementById('euCountry').value;
        if (String(phone).replace(/\D/g, '').length < 8) return;
        fetch(LOOKUP + '?country_code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            applyNameLookup(data, document.getElementById('euName'), document.getElementById('euNameChoices'));
        }).catch(function () {});
    }
    document.getElementById('euPhone').addEventListener('input', function () {
        clearTimeout(euTimer);
        euTimer = setTimeout(lookupEu, 450);
    });
    document.getElementById('euPhone').addEventListener('blur', lookupEu);

    document.getElementById('eulogyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('euErr');
        err.textContent = '';
        var blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        var signed = canvas.toDataURL() !== blank.toDataURL();
        document.getElementById('euSig').value = signed ? canvas.toDataURL('image/png') : '';
        var body = new FormData(e.target);
        fetch(EULOGY, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (!res.ok || !res.j.ok) {
                  err.textContent = (res.j && res.j.message) || 'Could not save.';
                  return;
              }
              window.location = window.location.pathname + '?eulogy=ok#eulogies';
          }).catch(function () { err.textContent = 'Network error. Try again.'; });
    });

    render();
})();
</script>
</body>
</html>
