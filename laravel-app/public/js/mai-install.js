(function () {
    var promptEvent = null;
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone;
    if (standalone) {
        document.querySelectorAll('[data-mai-install]').forEach(function (button) { button.hidden = true; });
        return;
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/whatsapp-mai/sw.js', { scope: '/whatsapp-mai/' }).catch(function () {});
    }
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        promptEvent = event;
    });
    function inAppBrowser() {
        return /WhatsApp|FBAN|FBAV|Instagram|Line\//.test(navigator.userAgent || '');
    }
    function openInChrome() {
        var bare = location.href.replace(/^https?:\/\//, '');
        location.href = 'intent://' + bare + '#Intent;scheme=https;package=com.android.chrome;end';
    }
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-mai-install]') : null;
        if (!button) return;
        event.preventDefault();
        if (promptEvent) {
            var waiting = promptEvent;
            promptEvent = null;
            waiting.prompt();
            if (waiting.userChoice) {
                waiting.userChoice.then(function () {});
            }
            return;
        }
        if (inAppBrowser()) {
            openInChrome();
            return;
        }
        var note = document.getElementById(button.getAttribute('data-note') || 'install-note');
        if (note) {
            note.hidden = false;
            if (!note.textContent) {
                note.textContent = 'Open this page in Chrome, tap the menu, then Install app.';
            }
        }
    });
})();
