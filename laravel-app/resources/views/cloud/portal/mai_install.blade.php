<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Whats MAI</title>
    @include('cloud.portal.mai_install_head')
    <style>
        body { margin: 0; background: #041833; font-family: system-ui, sans-serif; }
        .mai-install { max-width: 460px; margin: 0 auto; color: #fff; padding: 1.4rem 1.2rem 1.6rem; }
        h1 { margin: 0.8rem 0 0.35rem; font-size: 1.8rem; }
        p { color: rgba(255,255,255,.92); }
        .gold { display: block; width: 100%; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.9rem 1.2rem; font-size: 1.05rem; }
        .next { display: block; margin-top: 0.9rem; color: #F5D76E; font-weight: 700; text-align: center; }
        .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; margin-top: 0.8rem; }
    </style>
</head>
<body>
    <div class="mai-install">
        <button type="button" id="download-android" class="gold" data-mai-install data-note="install-note">Download for Android</button>
        <p id="install-note" class="note" hidden>Open this page in Chrome, tap the menu, then Install app.</p>
        <h1>Whats MAI</h1>
        <p>Install Whats MAI on this phone, then open it.</p>
        <a class="next" href="{{ $next }}">Continue to Whats MAI</a>
    </div>
    <script>
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            window.location = @json($next);
        }
    </script>
</body>
</html>
