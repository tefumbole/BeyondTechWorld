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
            <p>Scan this QR code with WhatsApp on your phone.</p>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            @if(!empty($session['error']))<p class="bad" id="link-error">{{ $session['error'] }}</p>@endif
            <div class="qr-card">
                <img id="link-qr" alt="WhatsApp QR code" @if(!empty($session['qr'])) src="{{ $session['qr'] }}" @endif class="{{ empty($session['qr']) ? 'is-off' : '' }}">
                <div id="qr-wait" class="qr-wait {{ empty($session['qr']) ? '' : 'is-off' }}">Preparing the QR code</div>
                <div>
                    <strong>Scan with WhatsApp</strong>
                    <p>Open WhatsApp → Linked devices → Link a device, then scan this code.</p>
                    <p id="qr-timer"></p>
                </div>
            </div>
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
    var shownAt = {{ !empty($session['qr']) ? 'Date.now() - ' . (int) ($session['qr_age_ms'] ?? 0) : '0' }};
    var pollTimer = null;
    var tickTimer = null;
    var refreshing = false;
    var csrf = @json(csrf_token());

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

    function paint(data) {
        var ready = document.getElementById('link-ready');
        var open = document.getElementById('open-mai');
        var err = document.getElementById('link-error');
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
        if (data && data.qr) {
            showQr(data.qr, data.qr_age_ms || 0);
            if (err) err.classList.add('is-off');
            return;
        }
        if (data && data.error) {
            showWait(data.error);
            var refresh = document.getElementById('qr-refresh');
            if (refresh) refresh.classList.remove('is-off');
        } else if (!document.getElementById('link-qr') || document.getElementById('link-qr').classList.contains('is-off')) {
            showWait(data && data.status === 'CONNECTING' ? 'Connecting your WhatsApp…' : 'Preparing the QR code');
        }
    }

    function refreshQr() {
        if (refreshing) return;
        refreshing = true;
        var refresh = document.getElementById('qr-refresh');
        if (refresh) {
            refresh.disabled = true;
            refresh.textContent = 'Refreshing…';
        }
        showWait('Preparing a new QR code…');
        shownAt = 0;
        fetch(@json(route('whatsapp.mai.link.qr')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _token: csrf })
        }).then(function (response) { return response.json(); })
            .then(function (data) {
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

    function tickStatus() {
        fetch(@json(route('whatsapp.mai.link.status')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                paint(data);
                if (shownAt && Date.now() - shownAt >= QR_LIFE) {
                    var refresh = document.getElementById('qr-refresh');
                    if (refresh) refresh.classList.remove('is-off');
                }
            })
            .catch(function () {});
    }

    var refreshBtn = document.getElementById('qr-refresh');
    if (refreshBtn) refreshBtn.onclick = refreshQr;
    if (@json(empty($session['qr']))) {
        refreshQr();
    } else {
        paintTimer();
    }
    pollTimer = setInterval(tickStatus, 2000);
    tickTimer = setInterval(paintTimer, 1000);
    tickStatus();
})();
</script>
@endpush
