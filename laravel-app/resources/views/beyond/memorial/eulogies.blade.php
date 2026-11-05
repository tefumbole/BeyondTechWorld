@extends('beyond.memorial.remember-layout')

@section('title', 'Eulogies · Late Pa Ngwayu Nchinda Francis')

@section('styles')
        body.is-eulogies .main {
            background: #f3f2eb;
            color: #29352d;
            padding: 0 0 12px;
        }
        body.is-eulogies .foot {
            background: #3a2e24;
            color: #f6efe4;
            margin: 0;
            padding: 35px 20px;
        }
        body.is-eulogies .foot a { color: #e5c385; }
        .eu-hero {
            position: relative;
            overflow: hidden;
            background: #f3f2eb;
            color: #29352d;
        }
        .eu-hero:after { content: none; }
        .eu-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1280px;
            margin: 0 auto;
            padding: 36px 36px 8px;
        }
        .eu-eyebrow {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .26em;
            text-transform: uppercase;
            color: #d8b777;
        }
        .eu-hero h1 {
            margin: 17px 0 20px;
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: clamp(57px, 7vw, 100px);
            letter-spacing: -.05em;
            line-height: 1.08;
            color: #29352d;
        }
        .eu-hero p {
            max-width: 600px;
            margin: 0;
            color: #49544b;
            font-size: 18px;
            line-height: 1.75;
        }
        .eu-stats {
            display: flex;
            align-items: center;
            gap: 22px;
            margin-top: 38px;
        }
        .eu-stats strong {
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: 38px;
            line-height: 1;
            color: #b88f4b;
        }
        .eu-stats span {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #8e7b59;
            line-height: 1.45;
        }
        .eu-note {
            max-width: 1280px;
            margin: 0 auto;
            padding: 45px 36px 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .eu-note span {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #8e7b59;
        }
        .eu-note .line { height: 1px; flex: 1; background: #d7d4c8; }
        .eu-collection {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 36px 80px;
        }
        .eu-featured {
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            gap: 60px;
            margin-bottom: 20px;
            padding: 42px 54px 53px;
        }
        .eu-aside {
            border-right: 1px solid #e6e0d1;
            padding: 0 40px 0 0;
        }
        .eu-aside span {
            display: block;
            color: #b88f4b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .eu-aside p {
            margin: 24px 0 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            line-height: 1.35;
            color: #3c5141;
        }
        .eu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .eu-card {
            background: #fffcf7;
            border: 1px solid #e9e5d9;
            box-shadow: 0 9px 29px rgba(49, 58, 46, .03);
            margin: 0;
            padding: 36px 40px 40px;
        }
        .eu-featured .eu-card {
            background: #fffcf7;
        }
        .eu-grid .eu-card { height: 100%; }
        .eu-top {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e8e3d8;
        }
        .eu-avatar {
            width: 49px;
            height: 49px;
            flex: none;
            border: 1px solid #c8ad76;
            border-radius: 50%;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #eee9db;
            color: #6d613f;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
        }
        .eu-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .eu-byline { flex: 1; min-width: 0; }
        .eu-byline h2 {
            margin: 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.3;
            color: #29352d;
        }
        .eu-byline time {
            display: block;
            margin-top: 2px;
            color: #8c8e83;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .eu-num {
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            color: #cabda0;
        }
        .eu-body { max-width: 820px; margin: 27px auto 0; }
        .eu-grid .eu-body { margin-left: 0; }
        .eu-body p {
            margin: 0 0 20px;
            font-size: 16px;
            line-height: 1.95;
            color: #49544b;
        }
        .eu-grid .eu-body p { font-size: 15px; }
        .eu-body p:last-child { margin-bottom: 0; }
        .eu-sign {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #e7e3d8;
        }
        .eu-sign cite {
            font-family: "Cormorant Garamond", serif;
            font-style: italic;
            font-size: 18px;
            color: #405444;
        }
        .eu-sign img {
            height: 52px;
            max-width: 180px;
            object-fit: contain;
        }
        .eu-more {
            margin: 28px 0 0;
            text-align: center;
        }
        .eu-more a {
            color: #405444;
            font-weight: 700;
            text-underline-offset: 4px;
        }
        .eu-empty {
            background: #fbfaf6;
            border: 1px dashed #d7d4c8;
            padding: 28px 22px;
            color: #667066;
            font-size: 17px;
        }
        .eu-leave {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 22px 0 0;
            padding: 14px 26px;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(180deg, #f0d57a, #d4af37);
            color: #1a1408;
            font-family: inherit;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(184, 143, 75, .28);
        }
        .name-choices[hidden] { display: none !important; }
        .eu-flash {
            max-width: 1280px;
            margin: 0 auto;
            padding: 16px 36px 0;
            color: #3c5141;
            font-weight: 700;
        }
        @media (max-width: 900px) {
            .eu-featured { grid-template-columns: 1fr; gap: 10px; padding: 30px; }
            .eu-aside { border-right: 0; border-bottom: 1px solid #e6e0d1; padding: 0 0 12px; }
            .eu-aside p { margin: 10px 0 0; font-size: 24px; }
            .eu-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .eu-hero-inner { padding: 28px 24px 4px; }
            .eu-hero p { font-size: 16px; }
            .eu-note { padding: 35px 24px 23px; }
            .eu-collection { padding: 0 20px 70px; }
            .eu-card, .eu-featured { padding: 25px 22px; }
            .eu-body p { font-size: 15px; }
            .eu-num { font-size: 22px; }
        }
@endsection

@section('content')
    @php
        $euCount = count($eulogies);
        $pull = '';
        if ($euCount) {
            $flat = trim(preg_replace('/\s+/', ' ', implode(' ', $eulogies[0]['paragraphs'] ?? [])));
            if ($flat !== '' && preg_match('/^(.+?[.!?])(?:\s|$)/u', $flat, $m)) {
                $len = function_exists('mb_strlen') ? mb_strlen($m[1]) : strlen($m[1]);
                if ($len >= 18 && $len <= 180) {
                    $pull = $m[1];
                }
            }
        }
    @endphp

    @if(($flashEulogy ?? '') === 'ok')
        <p class="eu-flash">Your eulogy was received. Thank you.</p>
    @endif

    <section class="eu-hero">
        <div class="eu-hero-inner">
            <p class="eu-eyebrow">Words that remain with us</p>
            <button type="button" class="eu-leave" id="openEulogy">Leave a eulogy</button>
        </div>
    </section>

    <nav class="page-toc cols-3" aria-label="Eulogy contents">
        <p>Contents</p>
        <ol>
            @foreach($eulogies as $index => $eu)
                <li><a href="#tribute-{{ $index + 1 }}">{{ $eu['name'] }}</a></li>
            @endforeach
        </ol>
    </nav>

    <div class="eu-note">
        <span>Tributes written for Pa Ngwayu Francis</span>
        <div class="line"></div>
    </div>

    <div class="eu-collection">
        @if($euCount)
            <div class="eu-featured">
                <aside class="eu-aside">
                    <span>Featured tribute
                        @if(!empty($eulogies[0]['when']))
                            · {{ $eulogies[0]['when'] }}
                        @endif
                    </span>
                    @if($pull !== '')
                        <p>“{{ $pull }}”</p>
                    @endif
                </aside>
                @include('beyond.memorial.eulogy-card', ['eu' => $eulogies[0], 'num' => 1])
            </div>
            @if($euCount > 1)
                <div class="eu-grid">
                    @foreach(array_slice($eulogies, 1) as $offset => $eu)
                        @include('beyond.memorial.eulogy-card', ['eu' => $eu, 'num' => $offset + 2])
                    @endforeach
                </div>
            @endif
        @else
            <p class="eu-empty">The eulogies written for Pa Ngwayu Francis are kept here.</p>
        @endif
        <p class="eu-more">
            <button type="button" class="eu-leave" id="openEulogyBottom">Leave a eulogy</button>
        </p>
        <p class="eu-more"><a href="{{ route('funeral.pangwayu.biography') }}">Read his biography</a></p>
    </div>
@endsection

@section('modals')
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
            <textarea name="body" id="euBody" rows="7" required maxlength="20000" placeholder="A few words for Pa Ngwayu Francis…"></textarea>
            <p class="hint">A few sentences or a full tribute — up to 20,000 characters.</p>
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
@endsection

@section('scripts')
<script>
(function () {
    var LOOKUP = @json($lookupUrl);
    var EULOGY = @json($eulogyUrl);
    var euModal = document.getElementById('eulogyModal');
    var canvas = document.getElementById('sigPad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    function stylePen() {
        ctx.strokeStyle = '#1a1408';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    function sizeCanvas() {
        var cssW = Math.max(280, Math.floor(canvas.clientWidth || canvas.offsetWidth || 320));
        var cssH = Math.max(140, Math.floor(canvas.clientHeight || canvas.offsetHeight || 150));
        if (canvas.width === cssW && canvas.height === cssH) { stylePen(); return; }
        canvas.width = cssW;
        canvas.height = cssH;
        stylePen();
    }
    function pos(ev) {
        var r = canvas.getBoundingClientRect();
        var t = ev.touches && ev.touches[0] ? ev.touches[0]
            : (ev.changedTouches && ev.changedTouches[0] ? ev.changedTouches[0] : ev);
        return {
            x: (t.clientX - r.left) * (canvas.width / Math.max(r.width, 1)),
            y: (t.clientY - r.top) * (canvas.height / Math.max(r.height, 1))
        };
    }
    function onPadDown(ev) {
        if (ev.pointerType === 'mouse' && ev.button !== 0) return;
        drawing = true;
        if (canvas.setPointerCapture && ev.pointerId != null) {
            try { canvas.setPointerCapture(ev.pointerId); } catch (err) {}
        }
        if (ev.cancelable) ev.preventDefault();
        var p = pos(ev);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }
    function onPadMove(ev) {
        if (!drawing) return;
        if (ev.cancelable) ev.preventDefault();
        var p = pos(ev);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }
    function onPadUp() { drawing = false; }
    if (window.PointerEvent) {
        canvas.addEventListener('pointerdown', onPadDown);
        canvas.addEventListener('pointermove', onPadMove);
        canvas.addEventListener('pointerup', onPadUp);
        canvas.addEventListener('pointercancel', onPadUp);
    }
    document.getElementById('sigClear').onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); stylePen(); };
    function openModal() {
        euModal.classList.add('on');
        requestAnimationFrame(function () { requestAnimationFrame(sizeCanvas); });
        document.getElementById('euPhone').focus();
    }
    document.getElementById('openEulogy').onclick = openModal;
    document.getElementById('openEulogyBottom').onclick = openModal;
    document.getElementById('closeEulogy').onclick = function () { euModal.classList.remove('on'); };
    document.getElementById('euCancel').onclick = function () { euModal.classList.remove('on'); };
    euModal.addEventListener('click', function (e) { if (e.target === euModal) euModal.classList.remove('on'); });

    function applyNameLookup(data) {
        var nameEl = document.getElementById('euName');
        var choicesEl = document.getElementById('euNameChoices');
        if (!data || !nameEl) return;
        var momo = (data.original_name || '').trim();
        var system = (data.system_name || '').trim();
        var chosen = momo || data.name || system;
        if (chosen) nameEl.value = chosen;
        choicesEl.innerHTML = '';
        if (!momo && !system) { choicesEl.hidden = true; return; }
        function addChoice(label, value, on) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'name-choice' + (on ? ' on' : '');
            btn.innerHTML = '<span></span><strong></strong>';
            btn.querySelector('span').textContent = label;
            btn.querySelector('strong').textContent = value;
            btn.addEventListener('click', function () { nameEl.value = value; });
            choicesEl.appendChild(btn);
        }
        if (momo) addChoice('Mobile Money', momo, true);
        if (system && system.toLowerCase() !== momo.toLowerCase()) addChoice('Our records', system, !momo);
        choicesEl.hidden = false;
    }
    var euTimer = null;
    function lookupEu() {
        var phone = document.getElementById('euPhone').value;
        var code = document.getElementById('euCountry').value;
        if (String(phone).replace(/\D/g, '').length < 8) return;
        fetch(LOOKUP + '?country_code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(applyNameLookup).catch(function () {});
    }
    document.getElementById('euPhone').addEventListener('input', function () {
        clearTimeout(euTimer);
        euTimer = setTimeout(lookupEu, 450);
    });
    document.getElementById('euPhone').addEventListener('blur', lookupEu);

    function firstApiMessage(j, fallback) {
        if (!j) return fallback;
        if (j.errors) {
            var keys = Object.keys(j.errors);
            for (var i = 0; i < keys.length; i++) {
                if (j.errors[keys[i]] && j.errors[keys[i]][0]) return j.errors[keys[i]][0];
            }
        }
        return j.message || fallback;
    }
    document.getElementById('eulogyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('euErr');
        var btn = e.target.querySelector('button[type="submit"]');
        err.textContent = '';
        var blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        var signed = canvas.toDataURL() !== blank.toDataURL();
        var out = document.createElement('canvas');
        out.width = Math.max(1, canvas.width);
        out.height = Math.max(1, canvas.height);
        var octx = out.getContext('2d');
        octx.fillStyle = '#ffffff';
        octx.fillRect(0, 0, out.width, out.height);
        octx.drawImage(canvas, 0, 0);
        document.getElementById('euSig').value = signed ? out.toDataURL('image/jpeg', 0.82) : '';
        var body = new FormData(e.target);
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf && csrf.content) body.append('_token', csrf.content);
        if (btn) btn.disabled = true;
        fetch(EULOGY, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf ? csrf.content : ''
            },
            body: body
        }).then(function (r) { return r.text().then(function (t) {
            var j = {};
            try { j = t ? JSON.parse(t) : {}; } catch (ex) { j = {}; }
            return { ok: r.ok, j: j };
        }); }).then(function (res) {
            if (!res.ok || !res.j.ok) {
                err.textContent = firstApiMessage(res.j, 'Could not save the eulogy. Try again.');
                if (btn) btn.disabled = false;
                return;
            }
            window.location = window.location.pathname + '?eulogy=ok';
        }).catch(function () {
            err.textContent = 'Network error. Check your connection and try again.';
            if (btn) btn.disabled = false;
        });
    });
})();
</script>
@endsection
