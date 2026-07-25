{{-- Batches UI clicks (links/buttons) and POSTs them for Activity Logs. --}}
@auth
<script>
(function () {
    if (window.__activityClickInit) return;
    window.__activityClickInit = true;

    var ENDPOINT = @json(route('activity-logs.clicks'));
    var CSRF = @json(csrf_token());
    var queue = [];
    var flushing = false;

    function labelFor(el) {
        if (!el) return '';
        var t = (el.getAttribute('aria-label') || el.getAttribute('title') || el.innerText || el.value || el.name || '').replace(/\s+/g, ' ').trim();
        return t.slice(0, 120);
    }

    function enqueue(ev) {
        var el = ev.target && ev.target.closest
            ? ev.target.closest('a, button, input[type="submit"], input[type="button"], .btn, [role="button"], .nav-link, .side-menu a')
            : null;
        if (!el) return;
        if (el.closest && el.closest('[data-no-activity-log]')) return;

        var href = '';
        if (el.tagName === 'A') href = el.getAttribute('href') || '';
        if (href && href.charAt(0) === '#') href = location.pathname + href;

        queue.push({
            tag: (el.tagName || 'EL').toLowerCase(),
            label: labelFor(el),
            href: href ? href.slice(0, 400) : '',
            page: location.pathname + location.search,
            x: ev.clientX || null,
            y: ev.clientY || null,
            t: Date.now()
        });
        if (queue.length >= 12) flush();
    }

    function flush() {
        if (flushing || !queue.length) return;
        flushing = true;
        var batch = queue.splice(0, 40);
        try {
            var body = JSON.stringify({ clicks: batch });
            if (navigator.sendBeacon) {
                var blob = new Blob([body], { type: 'application/json' });
                // sendBeacon cannot set CSRF easily for Laravel — use fetch keepalive
            }
            fetch(ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {}).finally(function () { flushing = false; });
        } catch (e) {
            flushing = false;
        }
    }

    document.addEventListener('click', enqueue, true);
    setInterval(flush, 4000);
    window.addEventListener('beforeunload', flush);
})();
</script>
@endauth
