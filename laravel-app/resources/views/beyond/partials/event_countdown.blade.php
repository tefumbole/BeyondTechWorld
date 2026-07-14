@php
    $uid = $uid ?? ('cd-' . uniqid());
    $compact = !empty($compact);
@endphp
<div class="event-countdown {{ $compact ? 'event-countdown--compact bg-brand-blue/95 text-white rounded-lg py-3 px-3 mt-3' : 'bg-brand-blue text-white py-10' }}"
     data-countdown
     data-target="{{ $targetIso }}"
     data-hide-after="{{ !empty($hideAfter) ? '1' : '0' }}">
    <div class="{{ $compact ? '' : 'max-w-3xl mx-auto px-4' }} text-center">
        @unless($compact)
            <p class="text-brand-gold font-semibold uppercase tracking-wider text-sm mb-2">Countdown</p>
        @else
            <p class="text-brand-gold font-semibold uppercase tracking-wider text-[10px] mb-1">Starts in</p>
        @endunless
        <div class="countdown-units flex justify-center {{ $compact ? 'gap-2' : 'gap-4 md:gap-8' }} flex-wrap" data-units>
            <div><span class="cd-days {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300 {{ $compact ? '' : 'mt-1' }}">Days</span></div>
            <div><span class="cd-hours {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300 {{ $compact ? '' : 'mt-1' }}">Hrs</span></div>
            <div><span class="cd-mins {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300 {{ $compact ? '' : 'mt-1' }}">Min</span></div>
            <div><span class="cd-secs {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300 {{ $compact ? '' : 'mt-1' }}">Sec</span></div>
        </div>
        <p class="countdown-done hidden {{ $compact ? 'text-sm font-bold mt-1' : 'text-2xl font-bold mt-4' }}" data-done>{{ $completionMessage ?? 'The event is here!' }}</p>
        @unless($compact)
            <p class="text-xs text-gray-400 mt-4">Times shown in {{ str_replace('_', ' ', $timezone ?? 'Africa/Kigali') }}</p>
        @endunless
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function bindCountdown(el) {
        if (el.__countdownBound) return;
        el.__countdownBound = true;
        var targetIso = el.getAttribute('data-target');
        if (!targetIso) return;
        var hideAfter = el.getAttribute('data-hide-after') === '1';
        var doneMsg = el.querySelector('[data-done]');
        var units = el.querySelector('[data-units]');
        var target = new Date(targetIso).getTime();
        if (isNaN(target)) return;

        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) {
                if (units) units.classList.add('hidden');
                if (doneMsg) doneMsg.classList.remove('hidden');
                if (hideAfter) setTimeout(function () { el.style.display = 'none'; }, 8000);
                return false;
            }
            var secs = Math.floor(diff / 1000);
            var days = Math.floor(secs / 86400); secs %= 86400;
            var hours = Math.floor(secs / 3600); secs %= 3600;
            var mins = Math.floor(secs / 60); secs %= 60;
            var d = el.querySelector('.cd-days');
            var h = el.querySelector('.cd-hours');
            var m = el.querySelector('.cd-mins');
            var s = el.querySelector('.cd-secs');
            if (d) d.textContent = days;
            if (h) h.textContent = pad(hours);
            if (m) m.textContent = pad(mins);
            if (s) s.textContent = pad(secs);
            return true;
        }
        if (tick()) {
            setInterval(function () { tick(); }, 1000);
        }
    }
    document.querySelectorAll('[data-countdown]').forEach(bindCountdown);
})();
</script>
@endpush
@endonce
