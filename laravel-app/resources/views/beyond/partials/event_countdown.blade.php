@php
    $compact = !empty($compact);
    $countdownLabel = $countdownLabel ?? ($compact ? 'Starts in' : 'Countdown');
@endphp
<div class="event-countdown {{ $compact ? 'event-countdown--compact bg-brand-blue text-white rounded-lg py-3 px-3' : 'bg-brand-blue text-white py-8' }}"
     data-countdown
     data-target="{{ $targetIso }}"
     data-hide-after="{{ !empty($hideAfter) ? '1' : '0' }}">
    <div class="{{ $compact ? '' : 'max-w-3xl mx-auto px-4' }} text-center">
        @unless($compact)
            <p class="text-brand-gold font-semibold uppercase tracking-wider text-sm mb-2">{{ $countdownLabel }}</p>
        @else
            <p class="text-brand-gold font-semibold uppercase tracking-wider text-[10px] mb-1">{{ $countdownLabel }}</p>
        @endunless
        <div class="countdown-units flex justify-center {{ $compact ? 'gap-2' : 'gap-4 md:gap-8' }} flex-wrap" data-units>
            <div><span class="cd-days {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300">Days</span></div>
            <div><span class="cd-hours {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300">Hrs</span></div>
            <div><span class="cd-mins {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300">Min</span></div>
            <div><span class="cd-secs {{ $compact ? 'text-lg font-bold' : 'text-4xl md:text-5xl font-bold' }} tabular-nums">--</span><span class="block {{ $compact ? 'text-[9px]' : 'text-sm' }} text-gray-300">Sec</span></div>
        </div>
        <p class="countdown-done hidden {{ $compact ? 'text-sm font-bold mt-1' : 'text-2xl font-bold mt-4' }}" data-done>{{ $completionMessage ?? 'The event is here!' }}</p>
        @unless($compact)
            <p class="text-xs text-gray-400 mt-3">Times shown in {{ str_replace('_', ' ', $timezone ?? 'Africa/Kigali') }}</p>
        @endunless
    </div>
</div>
