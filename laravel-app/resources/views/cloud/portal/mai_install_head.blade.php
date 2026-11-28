<link rel="manifest" href="{{ route('whatsapp.mai.manifest') }}">
<meta name="theme-color" content="#072f6b">
<link rel="icon" type="image/png" href="/public/branding/mai-icon-192.png">
<script>
if (!window.__maiInstallEarly) {
    window.__maiInstallEarly = true;
    window.__maiInstall = window.__maiInstall || { event: null, installed: false };
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.__maiInstall.event = event;
    });
    window.addEventListener('appinstalled', function () {
        window.__maiInstall.installed = true;
        window.__maiInstall.event = null;
    });
}
</script>
<script src="/public/js/mai-install.js?v={{ \App\Support\AppVersion::erp() }}" defer></script>
