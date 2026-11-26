@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Link WhatsApp to Whats MAI with a QR code or a code sent to the phone.')

@push('head')
<style>
    .mai-link { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.2rem 1.2rem 1.6rem; }
    .mai-link h1 { margin: 0.2rem 0 0.35rem; font-size: 2rem; color: #fff; }
    .mai-link p, .mai-link label { color: rgba(255,255,255,.92); }
    .mai-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .mai-brand { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; color: #fff; text-decoration: none; }
    .mai-signin { border: 1px solid rgba(255,255,255,.45); color: #fff; border-radius: 999px; padding: 0.35rem 0.85rem; font-weight: 700; text-decoration: none; }
    .link-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 1rem 0; background: rgba(255,255,255,.06); border-radius: 999px; padding: 4px; }
    .link-tabs button { border: 0; background: transparent; color: #fff; font-weight: 800; border-radius: 999px; padding: 0.7rem 0.4rem; cursor: pointer; }
    .link-tabs button.is-on { background: #D4AF37; color: #072f6b; }
    .qr-card { display: flex; gap: 14px; align-items: center; background: rgba(255,255,255,.06); border: 1px solid rgba(212,175,55,.35); border-radius: 18px; padding: 0.8rem; }
    .qr-card img, .qr-wait { width: 148px; height: 148px; background: #fff; border-radius: 12px; object-fit: contain; flex: 0 0 148px; }
    .qr-wait { color: #072f6b; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.85rem; font-weight: 700; padding: 8px; }
    .qr-card strong { display: block; color: #fff; margin-bottom: 0.25rem; }
    .gold { display: block; width: 100%; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.8rem 1.2rem; margin-top: 1rem; cursor: pointer; text-align: center; text-decoration: none; }
    .field { width: 100%; box-sizing: border-box; margin-top: 0.7rem; border: 0; border-radius: 999px; min-height: 48px; padding: 0.7rem 1rem; color: #10233f; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; }
    .bad { background: #fdecec; color: #7f1d1d; border-radius: 12px; padding: 0.65rem 0.75rem; }
    .is-off { display: none !important; }
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
            <p>Add Whats MAI to your phone so you can open it without the website.</p>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            @if(!empty($session['error']))<p class="bad">{{ $session['error'] }}</p>@endif
            <div class="link-tabs" id="link-tabs">
                <button type="button" data-tab="qr" class="{{ $linkTab === 'qr' ? 'is-on' : '' }}">QR code</button>
                <button type="button" data-tab="code" class="{{ $linkTab === 'code' ? 'is-on' : '' }}">Link code</button>
            </div>
            <div id="pane-qr" class="{{ $linkTab === 'qr' ? '' : 'is-off' }}">
                <div class="qr-card">
                    <img id="link-qr" alt="WhatsApp QR code" @if(!empty($session['qr'])) src="{{ $session['qr'] }}" @endif class="{{ empty($session['qr']) ? 'is-off' : '' }}">
                    <div id="qr-wait" class="qr-wait {{ empty($session['qr']) ? '' : 'is-off' }}">Preparing the QR code</div>
                    <div>
                        <strong>Scan with WhatsApp</strong>
                        <p>On your phone, open WhatsApp, tap Link a device, then scan this QR code.</p>
                    </div>
                </div>
            </div>
            <div id="pane-code" class="{{ $linkTab === 'code' ? '' : 'is-off' }}">
                <p>A code is sent to <strong>{{ $phone }}</strong>. Enter it here to open Whats MAI for this phone. The same link is then sent here so you can open it on this computer.</p>
                @if($otpSent)
                    <form method="POST" action="{{ route('whatsapp.mai.link.otp.verify') }}">
                        @csrf
                        <input class="field" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code" required>
                        <button class="gold" type="submit">Open Whats MAI</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('whatsapp.mai.link.otp') }}">
                        @csrf
                        <button class="gold" type="submit">Link code</button>
                    </form>
                @endif
            </div>
            <p id="link-ready" class="note {{ ($session['status'] ?? '') === 'CONNECTED' ? '' : 'is-off' }}">WhatsApp is linked for this phone.</p>
            <a class="gold" href="{{ route('whatsapp.mai.home') }}">Open Whats MAI on this computer</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var tabs = document.getElementById('link-tabs');
    var qrPane = document.getElementById('pane-qr');
    var codePane = document.getElementById('pane-code');
    function showTab(name) {
        if (!tabs) return;
        tabs.querySelectorAll('button').forEach(function (button) {
            button.classList.toggle('is-on', button.getAttribute('data-tab') === name);
        });
        if (qrPane) qrPane.classList.toggle('is-off', name !== 'qr');
        if (codePane) codePane.classList.toggle('is-off', name !== 'code');
    }
    if (tabs) {
        tabs.addEventListener('click', function (event) {
            var button = event.target.closest('button');
            if (!button) return;
            showTab(button.getAttribute('data-tab'));
        });
    }
    function paint(data) {
        var qr = document.getElementById('link-qr');
        var wait = document.getElementById('qr-wait');
        var ready = document.getElementById('link-ready');
        if (data.status === 'CONNECTED') {
            if (ready) ready.classList.remove('is-off');
            window.location = @json(route('whatsapp.mai.home'));
            return;
        }
        if (qr && data.qr) {
            qr.src = data.qr;
            qr.classList.remove('is-off');
            if (wait) wait.classList.add('is-off');
        }
    }
    setInterval(function () {
        fetch(@json(route('whatsapp.mai.link.status')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(paint)
            .catch(function () {});
    }, 4000);
})();
</script>
@endpush
