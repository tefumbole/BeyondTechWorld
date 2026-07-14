<div id="event-countdown" class="bg-brand-blue text-white py-10" data-target="{{ $targetIso }}" data-timezone="{{ $timezone }}" data-hide-after="{{ $hideAfter ? '1' : '0' }}">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <p class="text-brand-gold font-semibold uppercase tracking-wider text-sm mb-2">Countdown</p>
        <div id="countdown-units" class="flex justify-center gap-4 md:gap-8 flex-wrap">
            <div class="countdown-unit"><span id="cd-days" class="text-4xl md:text-5xl font-bold tabular-nums">--</span><span class="block text-sm text-gray-300 mt-1">Days</span></div>
            <div class="countdown-unit"><span id="cd-hours" class="text-4xl md:text-5xl font-bold tabular-nums">--</span><span class="block text-sm text-gray-300 mt-1">Hours</span></div>
            <div class="countdown-unit"><span id="cd-mins" class="text-4xl md:text-5xl font-bold tabular-nums">--</span><span class="block text-sm text-gray-300 mt-1">Minutes</span></div>
            <div class="countdown-unit"><span id="cd-secs" class="text-4xl md:text-5xl font-bold tabular-nums">--</span><span class="block text-sm text-gray-300 mt-1">Seconds</span></div>
        </div>
        <p id="countdown-done" class="hidden text-2xl font-bold mt-4">{{ $completionMessage }}</p>
        <p class="text-xs text-gray-400 mt-4">Times shown in {{ str_replace('_', ' ', $timezone) }}</p>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var el = document.getElementById('event-countdown');
    if (!el) return;
    var targetIso = el.getAttribute('data-target');
    var hideAfter = el.getAttribute('data-hide-after') === '1';
    var doneMsg = document.getElementById('countdown-done');
    var units = document.getElementById('countdown-units');
    var target = new Date(targetIso).getTime();

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function tick() {
        var now = Date.now();
        var diff = target - now;
        if (diff <= 0) {
            if (units) units.classList.add('hidden');
            if (doneMsg) doneMsg.classList.remove('hidden');
            if (hideAfter) {
                setTimeout(function () { el.style.display = 'none'; }, 8000);
            }
            return;
        }
        var secs = Math.floor(diff / 1000);
        var days = Math.floor(secs / 86400); secs %= 86400;
        var hours = Math.floor(secs / 3600); secs %= 3600;
        var mins = Math.floor(secs / 60); secs %= 60;
        document.getElementById('cd-days').textContent = days;
        document.getElementById('cd-hours').textContent = pad(hours);
        document.getElementById('cd-mins').textContent = pad(mins);
        document.getElementById('cd-secs').textContent = pad(secs);
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
