(function () {
    if (window.__maiInstallBound) return;
    window.__maiInstallBound = true;
    window.__maiInstall = window.__maiInstall || { event: null, installed: false };
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone;
    if (standalone) {
        document.querySelectorAll('[data-mai-install]').forEach(function (button) { button.hidden = true; });
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/whatsapp-mai/sw.js', { scope: '/whatsapp-mai/' }).catch(function () {});
    }
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.__maiInstall.event = event;
        window.__maiInstall.installed = false;
    });
    window.addEventListener('appinstalled', function () {
        window.__maiInstall.installed = true;
        window.__maiInstall.event = null;
        document.querySelectorAll('[data-mai-install]').forEach(function (button) {
            button.textContent = 'Installed';
            button.disabled = true;
        });
        var sheet = document.getElementById('mai-install-sheet');
        if (sheet) sheet.hidden = true;
    });
    function android() {
        return /android/i.test(navigator.userAgent || '');
    }
    function inAppBrowser() {
        return /WhatsApp|FBAN|FBAV|Instagram|Line\//.test(navigator.userAgent || '');
    }
    function chrome() {
        return /chrome|crios/i.test(navigator.userAgent || '') && !/edg\//i.test(navigator.userAgent || '');
    }
    function sheetText(kind) {
        var page = String(location.href || '');
        if (kind === 'dismissed') {
            return 'Install was dismissed. Tap Install again, or open the Chrome menu and tap Install app or Add to Home screen.';
        }
        if (kind === 'inapp') {
            return 'This page is inside another app, so Android cannot install Whats MAI here. Copy this address and open it in Chrome, then tap Install: ' + page;
        }
        if (kind === 'iphone') {
            return 'On iPhone, tap Share, then Add to Home Screen.';
        }
        if (android() && chrome()) {
            return 'Chrome did not open the install prompt. Open the Chrome menu (the three dots) and tap Install app or Add to Home screen.';
        }
        return 'Open this page in Chrome, then use the menu and tap Install app or Add to Home screen. ' + page;
    }
    function showSheet(kind) {
        var sheet = document.getElementById('mai-install-sheet');
        if (!sheet) {
            if (!document.getElementById('mai-install-style')) {
                var style = document.createElement('style');
                style.id = 'mai-install-style';
                style.textContent = '#mai-install-sheet{position:fixed;z-index:90;left:12px;right:12px;bottom:12px;background:#fff;color:#10233f;border-radius:16px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);font:16px/1.45 system-ui,sans-serif}#mai-install-sheet p{margin:0}#mai-install-sheet button{display:inline-block;margin:10px 8px 0 0;background:#072f6b;color:#fff;border:0;border-radius:999px;padding:10px 14px;font-weight:700}';
                (document.head || document.documentElement).appendChild(style);
            }
            sheet = document.createElement('div');
            sheet.id = 'mai-install-sheet';
            sheet.setAttribute('role', 'dialog');
            sheet.setAttribute('aria-label', 'Install Whats MAI');
            document.body.appendChild(sheet);
        }
        sheet.hidden = false;
        sheet.textContent = '';
        var copy = document.createElement('p');
        copy.textContent = sheetText(kind);
        sheet.appendChild(copy);
        if (kind === 'inapp') {
            var open = document.createElement('button');
            open.type = 'button';
            open.textContent = 'Open in Chrome';
            open.addEventListener('click', function () {
                var bare = String(location.href || '').replace(/^https?:\/\//, '');
                location.href = 'intent://' + bare + '#Intent;scheme=https;package=com.android.chrome;end';
            });
            sheet.appendChild(open);
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'Close';
        close.addEventListener('click', function () { sheet.hidden = true; });
        sheet.appendChild(close);
    }
    function afterPrompt(waiting, button) {
        var choice = waiting.userChoice;
        if (!choice || !choice.then) return;
        choice.then(function (result) {
            if (window.__maiInstall.installed) return;
            if (!result || result.outcome !== 'accepted') showSheet('dismissed');
            if (button) button.disabled = false;
        }).catch(function () {
            showSheet(inAppBrowser() ? 'inapp' : 'help');
        });
    }
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-mai-install]') : null;
        if (!button || button.disabled) return;
        event.preventDefault();
        if (window.__maiInstall.installed || standalone) return;
        var waiting = window.__maiInstall.event;
        window.__maiInstall.event = null;
        if (waiting && typeof waiting.prompt === 'function') {
            try {
                var pending = waiting.prompt();
                if (pending && pending.catch) pending.catch(function () { showSheet(inAppBrowser() ? 'inapp' : 'help'); });
                afterPrompt(waiting, button);
            } catch (error) {
                showSheet(inAppBrowser() ? 'inapp' : 'help');
            }
            return;
        }
        if (inAppBrowser()) {
            showSheet('inapp');
            return;
        }
        if (/iphone|ipad|ipod/i.test(navigator.userAgent || '')) {
            showSheet('iphone');
            return;
        }
        showSheet('help');
    });
})();
