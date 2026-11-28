@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Download Whats MAI for Android.')

@push('head')
<link rel="manifest" href="{{ asset('whats-mai.webmanifest') }}">
<meta name="theme-color" content="#072f6b">
<meta name="mobile-web-app-capable" content="yes">
<style>
    .mai-install { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.4rem 1.2rem 1.6rem; }
    .mai-install h1 { margin: 0.8rem 0 0.35rem; font-size: 1.8rem; color: #fff; }
    .mai-install p { color: rgba(255,255,255,.92); }
    .gold { display: block; width: 100%; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.9rem 1.2rem; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; font-size: 1.05rem; }
    .next { display: block; margin-top: 0.9rem; color: #F5D76E; font-weight: 700; text-align: center; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; margin-top: 0.8rem; }
</style>
@endpush

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-install">
            <button type="button" id="download-android" class="gold">Download for Android</button>
            <p id="install-note" class="note" hidden>On Android, open this page in Chrome, tap the menu, then Install app or Add to Home screen.</p>
            <h1>Whats MAI</h1>
            <p>Install Whats MAI on this phone, then open it.</p>
            <a class="next" href="{{ $next }}">Continue to Whats MAI</a>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var promptEvent = null;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ url('/whatsapp-mai/sw.js') }}', { scope: '/whatsapp-mai/' }).catch(function () {});
    }
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
        window.location = @json($next);
        return;
    }
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        promptEvent = event;
    });
    var button = document.getElementById('download-android');
    if (!button) return;
    button.onclick = function () {
        if (promptEvent) {
            promptEvent.prompt();
            return;
        }
        var note = document.getElementById('install-note');
        if (note) note.hidden = false;
    };
})();
</script>
@endpush
