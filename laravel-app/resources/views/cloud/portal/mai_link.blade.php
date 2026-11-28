@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Link WhatsApp to Whats MAI by scanning a QR code.')

@push('head')
<style>
    .mai-link { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.2rem 1.2rem 1.6rem; }
    .mai-link h1 { margin: 0.2rem 0 0.35rem; font-size: 2rem; color: #fff; }
    .mai-link p, .mai-link label { color: rgba(255,255,255,.92); }
    .mai-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .mai-brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; color: #fff; text-decoration: none; }
    .mai-signin { border: 1px solid rgba(255,255,255,.45); color: #fff; border-radius: 999px; padding: 0.35rem 0.85rem; font-weight: 700; text-decoration: none; }
    .qr-card { display: flex; flex-direction: column; align-items: center; gap: 12px; background: rgba(255,255,255,.06); border: 1px solid rgba(212,175,55,.35); border-radius: 18px; padding: 1rem; text-align: center; }
    .qr-card img, .qr-wait { width: 220px; height: 220px; background: #fff; border-radius: 12px; object-fit: contain; }
    .qr-wait { color: #072f6b; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.9rem; font-weight: 700; padding: 12px; }
    .qr-card strong { display: block; color: #fff; margin-bottom: 0.25rem; }
    .gold { display: block; width: 100%; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.8rem 1.2rem; margin-top: 1rem; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; }
    .pair-form, .pair-help { width: 100%; text-align: left; }
    .pair-form label { display: block; font-weight: 700; margin: 0.7rem 0 0.25rem; }
    .pair-form input, .pair-form select { width: 100%; box-sizing: border-box; border-radius: 12px; border: 0; padding: 0.75rem 0.8rem; font-size: 16px; }
    .pair-code { font-size: 2rem; letter-spacing: 0.12em; font-weight: 800; color: #072f6b; background: #fff; border-radius: 12px; padding: 0.7rem 0.4rem; margin: 0.4rem 0; }
    .pair-help li { margin: 0.35rem 0; }
    @media (max-width: 480px) {
        .mai-link { padding: 1rem 0.8rem 1.2rem; border-radius: 18px; }
        .mai-link h1 { font-size: 1.6rem; }
        .pair-code { font-size: 1.5rem; letter-spacing: 0.08em; }
        .qr-card img, .qr-wait { width: min(220px, 70vw); height: min(220px, 70vw); }
    }
    .ghost { display: block; width: 100%; background: transparent; color: #F5D76E; border: 1px solid rgba(212,175,55,.75); border-radius: 999px; font-weight: 800; padding: 0.75rem 1.2rem; margin-top: 0.7rem; cursor: pointer; text-align: center; }
    .ghost[disabled] { opacity: .45; cursor: default; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; }
    .bad { background: #fdecec; color: #7f1d1d; border-radius: 12px; padding: 0.65rem 0.75rem; }
    .is-off { display: none !important; }
    #qr-timer { color: #F5D76E; font-weight: 700; min-height: 1.2rem; }
</style>
@endpush

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-link">
            <div class="mai-top">
                <a class="mai-brand" href="{{ route('whatsapp.mai.home') }}">
                    <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="36" height="40" style="width:36px;height:40px;object-fit:contain">
                    Whats MAI
                </a>
                <a class="mai-signin" href="{{ route('whatsapp.mai.home') }}">Close</a>
            </div>
            <h1>Link WhatsApp</h1>
            <p>Use this phone. Enter the WhatsApp number, then type the code in WhatsApp. A second screen can scan a QR code instead.</p>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            <p class="bad is-off" id="link-error">@if(!empty($session['error'])){{ $session['error'] }}@endif</p>
            <div class="pair-form" id="pair-form">
                <label for="link-dial">Country code</label>
                <select id="link-dial">
                    @foreach(\App\Support\CountryDialCodes::all() as $label)
                        @php $dialCode = preg_replace('/\D+/', '', $label); @endphp
                        <option value="{{ $dialCode }}" {{ $dialCode === '237' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="link-phone">WhatsApp number</label>
                <input id="link-phone" inputmode="tel" autocomplete="tel" value="{{ $phone }}" placeholder="670000000">
                <button type="button" class="gold" id="pair-go">Link with phone number</button>
            </div>
            <div class="qr-card is-off" id="pair-card">
                <div id="pair-code" class="pair-code"></div>
                <button type="button" class="ghost" id="pair-copy">Copy code</button>
                <p id="pair-status">Waiting for approval in WhatsApp.</p>
                <div class="pair-help">
                    <strong>iPhone</strong>
                    <ol>
                        <li>Open WhatsApp.</li>
                        <li>Go to Settings, then Linked Devices.</li>
                        <li>Tap Link a Device, then Link with phone number instead.</li>
                        <li>Enter this code, then come back to this page.</li>
                    </ol>
                    <strong>Android</strong>
                    <ol>
                        <li>Open WhatsApp.</li>
                        <li>Open the menu, then Linked devices.</li>
                        <li>Tap Link a device, then Link with phone number instead.</li>
                        <li>Enter this code, then come back to this page.</li>
                    </ol>
                </div>
                <p id="qr-timer"></p>
                <button type="button" class="ghost is-off" id="pair-fresh">Create a new code</button>
            </div>
            <div class="qr-card is-off" id="qr-card">
                <img id="link-qr" alt="WhatsApp QR code" class="is-off">
                <div id="qr-wait" class="qr-wait">Preparing the QR code</div>
                <div>
                    <strong>Scan with WhatsApp</strong>
                    <p>On the other screen, open WhatsApp, then Linked devices, then Link a device, and scan this code.</p>
                </div>
            </div>
            <button type="button" class="ghost" id="qr-show">Scan QR code</button>
            <button type="button" class="ghost is-off" id="qr-refresh">Refresh QR code</button>
            <p id="link-ready" class="note {{ ($session['status'] ?? '') === 'CONNECTED' ? '' : 'is-off' }}">WhatsApp is linked for this phone.</p>
            <a class="gold" id="open-mai" href="{{ route('whatsapp.mai.home') }}">Open Whats MAI on this computer</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var QR_LIFE = 60000;
    var shownAt = 0;
    var mode = 'phone';
    var pollTimer = null;
    var tickTimer = null;
    var refreshing = false;
    var csrf = @json(csrf_token());
    var pairUrl = @json(route('whatsapp.mai.link.pair'));
    var statusUrl = @json(route('whatsapp.mai.link.status'));
    var qrUrl = @json(route('whatsapp.mai.link.qr'));

    function showQr(src, ageMs) {
        var qr = document.getElementById('link-qr');
        var wait = document.getElementById('qr-wait');
        var refresh = document.getElementById('qr-refresh');
        if (!src) return;
        if (qr) {
            qr.src = src;
            qr.classList.remove('is-off');
        }
        if (wait) wait.classList.add('is-off');
        shownAt = Date.now() - (ageMs || 0);
        if (refresh) {
            refresh.classList.add('is-off');
            refresh.disabled = false;
            refresh.textContent = 'Refresh QR code';
        }
        paintTimer();
    }

    function showWait(text) {
        var qr = document.getElementById('link-qr');
        var wait = document.getElementById('qr-wait');
        if (qr) qr.classList.add('is-off');
        if (wait) {
            wait.classList.remove('is-off');
            wait.textContent = text || 'Preparing the QR code';
        }
    }

    function paintTimer() {
        var node = document.getElementById('qr-timer');
        var refresh = document.getElementById('qr-refresh');
        if (!shownAt) {
            if (node) node.textContent = '';
            return;
        }
        var left = Math.max(0, QR_LIFE - (Date.now() - shownAt));
        if (node) {
            node.textContent = left > 0
                ? ('This code is valid for ' + Math.ceil(left / 1000) + 's')
                : 'This code expired. Refresh for a new one.';
        }
        if (refresh && left <= 0) {
            refresh.classList.remove('is-off');
        }
    }

    function fields() {
        var phone = document.getElementById('link-phone');
        var dial = document.getElementById('link-dial');
        var raw = phone ? String(phone.value || '').replace(/\D+/g, '') : '';
        return {
            phone: phone ? phone.value : '',
            dial: raw.length >= 11 ? '' : (dial ? dial.value : '')
        };
    }

    function showError(text) {
        var err = document.getElementById('link-error');
        if (!err) return;
        if (!text) {
            err.classList.add('is-off');
            err.textContent = '';
            return;
        }
        err.classList.remove('is-off');
        err.textContent = text;
    }

    function paintCode(data) {
        var card = document.getElementById('pair-card');
        var code = document.getElementById('pair-code');
        var status = document.getElementById('pair-status');
        var fresh = document.getElementById('pair-fresh');
        if (card) card.classList.remove('is-off');
        if (code && data && data.pairing_code) {
            code.textContent = data.pairing_code;
            shownAt = Date.now() - (data.pairing_age_ms || 0);
        }
        if (status) {
            if (data && data.status === 'CONNECTING') status.textContent = 'Waiting for approval in WhatsApp.';
            else if (data && data.status === 'ERROR') status.textContent = data.error || 'The link failed.';
            else if (data && data.pairing_code) status.textContent = 'Waiting for approval in WhatsApp.';
            else status.textContent = 'Creating the link code.';
        }
        var left = shownAt ? Math.max(0, QR_LIFE - (Date.now() - shownAt)) : 0;
        var timer = document.getElementById('qr-timer');
        if (timer) {
            timer.textContent = left > 0
                ? ('This code is valid for ' + Math.ceil(left / 1000) + 's')
                : (shownAt ? 'This code expired. Create a new one.' : '');
        }
        if (fresh) fresh.classList.toggle('is-off', left > 0);
        if (data && data.error && !(data.pairing_code)) showError(data.error);
        else showError('');
    }

    function paint(data) {
        var ready = document.getElementById('link-ready');
        var open = document.getElementById('open-mai');
        if (data && data.redirect) {
            window.location = data.redirect;
            return;
        }
        if (data && data.status === 'CONNECTED') {
            if (ready) ready.classList.remove('is-off');
            if (open) open.href = @json(route('whatsapp.mai.inbox'));
            window.location = @json(route('whatsapp.mai.inbox'));
            return;
        }
        if (mode === 'phone') {
            if (data && (data.pairing_code || data.error || data.status === 'ERROR' || data.status === 'CONNECTING')) paintCode(data);
            return;
        }
        if (data && data.status === 'CONNECTING' && !data.qr) {
            showWait('Opening Whats MAI…');
            return;
        }
        if (data && data.qr) {
            var current = document.getElementById('link-qr');
            var stillGood = shownAt && (Date.now() - shownAt) < QR_LIFE && current && current.src && current.src.indexOf('data:image') === 0 && !current.classList.contains('is-off');
            if (stillGood) return;
            showQr(data.qr, data.qr_age_ms || 0);
            showError('');
            return;
        }
        if (data && data.error) {
            showWait(data.error);
            showError(data.error);
            var refresh = document.getElementById('qr-refresh');
            if (refresh) refresh.classList.remove('is-off');
        } else if (!document.getElementById('link-qr') || document.getElementById('link-qr').classList.contains('is-off')) {
            showWait(data && data.status === 'CONNECTING' ? 'Connecting your WhatsApp…' : 'Preparing the QR code');
        }
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body || {})
        }).then(function (response) { return response.json(); });
    }

    function refreshQr() {
        if (refreshing) return;
        refreshing = true;
        mode = 'qr';
        var refresh = document.getElementById('qr-refresh');
        var card = document.getElementById('qr-card');
        var pairCard = document.getElementById('pair-card');
        if (card) card.classList.remove('is-off');
        if (pairCard) pairCard.classList.add('is-off');
        if (refresh) {
            refresh.disabled = true;
            refresh.textContent = 'Refreshing…';
        }
        showWait('Preparing a new QR code…');
        shownAt = 0;
        var entered = fields();
        postJson(qrUrl, { phone: entered.phone, dial: entered.dial }).then(function (data) {
            refreshing = false;
            paint(data);
            if (refresh) {
                refresh.disabled = false;
                refresh.textContent = 'Refresh QR code';
                if (!(data && data.qr)) refresh.classList.remove('is-off');
            }
        }).catch(function () {
            refreshing = false;
            showWait('The QR code could not be created. Try again.');
            if (refresh) {
                refresh.disabled = false;
                refresh.classList.remove('is-off');
                refresh.textContent = 'Refresh QR code';
            }
        });
    }

    function requestCode(fresh) {
        if (refreshing) return;
        refreshing = true;
        mode = 'phone';
        var qrCard = document.getElementById('qr-card');
        if (qrCard) qrCard.classList.add('is-off');
        paintCode({ status: 'CONNECTING' });
        var entered = fields();
        postJson(pairUrl, { phone: entered.phone, dial: entered.dial, fresh: fresh ? 1 : 0 }).then(function (data) {
            refreshing = false;
            paint(data);
        }).catch(function () {
            refreshing = false;
            showError('The link code could not be created.');
        });
    }

    function tickStatus() {
        var url = statusUrl + (mode === 'qr' ? '?qr=1' : '');
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                paint(data);
                if (mode === 'qr' && shownAt && Date.now() - shownAt >= QR_LIFE && !(data && (data.status === 'CONNECTED' || data.redirect))) {
                    refreshQr();
                }
            })
            .catch(function () {});
    }

    var refreshBtn = document.getElementById('qr-refresh');
    if (refreshBtn) refreshBtn.onclick = refreshQr;
    var showQrBtn = document.getElementById('qr-show');
    if (showQrBtn) showQrBtn.onclick = refreshQr;
    var pairBtn = document.getElementById('pair-go');
    if (pairBtn) pairBtn.onclick = function () { requestCode(false); };
    var freshBtn = document.getElementById('pair-fresh');
    if (freshBtn) freshBtn.onclick = function () { requestCode(true); };
    var copyBtn = document.getElementById('pair-copy');
    if (copyBtn) copyBtn.onclick = function () {
        var code = (document.getElementById('pair-code').textContent || '').replace(/\s+/g, '');
        if (!code) return;
        var done = function () { copyBtn.textContent = 'Copied'; };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(code).then(done).catch(done);
        else done();
    };
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) tickStatus();
    });
    window.addEventListener('pageshow', function () { tickStatus(); });
    pollTimer = setInterval(tickStatus, 2000);
    tickTimer = setInterval(paintTimer, 1000);
})();
</script>
@endpush
