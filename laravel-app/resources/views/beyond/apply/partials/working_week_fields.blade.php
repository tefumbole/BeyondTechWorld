{{--
  Reusable Working Week fields (apply + offer portal).
  Params: $wwData (array), $prefix (optional form name prefix), $required (bool)
--}}
@php
    use App\Support\WorkingWeekForm;
    use App\WorkingWeek;
    $wwData = WorkingWeekForm::fromArray($wwData ?? []);
    $summary = WorkingWeekForm::summary($wwData);
    $days = WorkingWeek::days();
    $labels = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ];
    $prefix = $prefix ?? '';
    $name = function ($key) use ($prefix) {
        return $prefix !== '' ? $prefix.'['.$key.']' : $key;
    };
    $formId = $formId ?? 'ww-embed-form';
@endphp
<style>
    .ww-embed { --ww-blue:#0b3f90; --ww-gold:#c6ab47; }
    .ww-embed .ww-lunch { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin-bottom:14px; }
    .ww-embed .ww-day {
        display:flex; flex-wrap:wrap; align-items:center; gap:10px 14px;
        padding:12px 0; border-bottom:1px solid #eef2f7;
    }
    .ww-embed .ww-day:last-child { border-bottom:0; }
    .ww-embed .ww-day.is-off { opacity:.72; }
    .ww-embed .ww-times { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ww-embed .ww-times input[type=time] {
        border:1px solid #d7deea; border-radius:8px; padding:7px 10px; font-size:14px; background:#fff;
    }
    .ww-embed .ww-hours {
        margin-left:auto; background:#eef4ff; color:#0b3f90; font-weight:700; font-size:12px;
        border-radius:999px; padding:4px 10px; white-space:nowrap;
    }
    .ww-embed .ww-summary {
        background:linear-gradient(160deg,#0b3f90,#0a2f6d); color:#fff; border-radius:16px; padding:18px;
    }
    .ww-embed .ww-summary .gold { color:var(--ww-gold); font-size:1.75rem; font-weight:800; line-height:1.1; }
    .ww-embed input[type=number] {
        border:1px solid #d7deea; border-radius:8px; padding:8px 10px; font-size:14px; width:100%; max-width:140px;
    }
</style>
<div class="ww-embed" id="{{ $formId }}-wrap">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <h4 class="text-brand-blue font-extrabold text-base m-0 mb-1">Working Week *</h4>
            <p class="text-slate-500 text-sm m-0 mb-3">Choose the days and hours you will work. Daily tasks release on these days.</p>

            <div class="ww-lunch">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Lunch Break (Minutes)</label>
                <input type="number" name="{{ $name('lunch_break_minutes') }}" id="{{ $formId }}-lunch"
                       min="0" max="180" value="{{ (int) ($wwData['lunch_break_minutes'] ?? 60) }}">
                <div class="text-slate-500 text-xs mt-1">Deducted from daily total.</div>
            </div>

            @foreach($days as $day)
                @php
                    $active = ! empty($wwData[$day]);
                    $start = $wwData[$day.'_start'] ?? '08:00';
                    $end = $wwData[$day.'_end'] ?? '17:00';
                    $hrs = $summary['day_hours'][$day] ?? 0;
                @endphp
                <div class="ww-day {{ $active ? '' : 'is-off' }}" data-day="{{ $day }}">
                    <label class="mb-0 flex items-center gap-2 font-semibold text-slate-800">
                        <input type="checkbox" name="{{ $name($day) }}" value="1" class="ww-day-toggle" @if($active) checked @endif>
                        <span>{{ $labels[$day] }}</span>
                    </label>
                    <span class="ww-day-off text-slate-400 text-sm" style="@if($active) display:none; @endif">Day Off</span>
                    <div class="ww-times ww-day-times" style="@if(!$active) display:none; @endif">
                        <span class="text-slate-500 text-xs">From</span>
                        <input type="time" name="{{ $name($day.'_start') }}" class="ww-day-start" value="{{ substr($start, 0, 5) }}">
                        <span class="text-slate-500 text-xs">To</span>
                        <input type="time" name="{{ $name($day.'_end') }}" class="ww-day-end" value="{{ substr($end, 0, 5) }}">
                    </div>
                    <span class="ww-hours ww-day-hours" style="@if(!$active) display:none; @endif">{{ number_format($hrs, 2) }}h</span>
                </div>
            @endforeach
        </div>

        <div>
            <div class="ww-summary">
                <div class="font-bold text-base mb-3">Summary</div>
                <div class="flex justify-between items-center mb-4 text-sm">
                    <span>Working Days</span>
                    <strong id="{{ $formId }}-sum-days" class="text-lg">{{ $summary['working_days'] }}</strong>
                </div>
                <div class="text-[11px] tracking-wide font-bold opacity-80 mb-1">TOTAL EXPECTED HOURS</div>
                <div class="gold" id="{{ $formId }}-sum-hours">{{ number_format($summary['expected'], 2) }}h</div>
                <div class="text-xs mt-2 opacity-80">Per week based on current configuration.</div>
                <div class="mt-4 p-3 rounded-xl text-xs leading-relaxed" style="background:rgba(0,0,0,.18);">
                    Lunch break of <strong id="{{ $formId }}-sum-lunch">{{ (int) ($wwData['lunch_break_minutes'] ?? 60) }}</strong> min is deducted daily.
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var wrap = document.getElementById(@json($formId.'-wrap'));
    if (!wrap) return;
    var lunchEl = document.getElementById(@json($formId.'-lunch'));
    function parseHm(v) {
        if (!v) return null;
        var p = v.split(':');
        return (parseInt(p[0], 10) * 60) + parseInt(p[1] || 0, 10);
    }
    function dayHours(row, lunch) {
        var toggle = row.querySelector('.ww-day-toggle');
        if (!toggle || !toggle.checked) return 0;
        var s = parseHm(row.querySelector('.ww-day-start').value);
        var e = parseHm(row.querySelector('.ww-day-end').value);
        if (s === null || e === null) return 0;
        var mins = e - s;
        if (mins < 0) mins += 24 * 60;
        mins -= lunch;
        if (mins < 0) mins = 0;
        return Math.round((mins / 60) * 100) / 100;
    }
    function refresh() {
        var lunch = parseInt((lunchEl && lunchEl.value) || '0', 10) || 0;
        var days = 0, total = 0;
        wrap.querySelectorAll('.ww-day').forEach(function (row) {
            var on = row.querySelector('.ww-day-toggle').checked;
            row.classList.toggle('is-off', !on);
            var times = row.querySelector('.ww-day-times');
            var off = row.querySelector('.ww-day-off');
            var badge = row.querySelector('.ww-day-hours');
            if (times) times.style.display = on ? 'flex' : 'none';
            if (off) off.style.display = on ? 'none' : '';
            var h = dayHours(row, lunch);
            if (badge) {
                badge.style.display = on ? '' : 'none';
                badge.textContent = h.toFixed(2) + 'h';
            }
            if (on) { days++; total += h; }
        });
        var sd = document.getElementById(@json($formId.'-sum-days'));
        var sh = document.getElementById(@json($formId.'-sum-hours'));
        var sl = document.getElementById(@json($formId.'-sum-lunch'));
        if (sd) sd.textContent = days;
        if (sh) sh.textContent = total.toFixed(2) + 'h';
        if (sl) sl.textContent = lunch;
    }
    wrap.addEventListener('change', refresh);
    wrap.addEventListener('input', refresh);
    refresh();
})();
</script>
