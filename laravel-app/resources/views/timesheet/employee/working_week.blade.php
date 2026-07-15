@extends('layout.main')

@section('content')
@php
    $tsTab = 'timesheet.working-week';
    $days = \App\WorkingWeek::days();
    $labels = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
    ];
@endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.employee_tabs')

        <form method="POST" action="{{ route('timesheet.working-week.save') }}" id="ww-form">
            @csrf
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
                <div>
                    <h1 class="ts-title">Working Week Configuration</h1>
                    <p class="ts-subtitle">Set your weekly schedule and working hours.</p>
                </div>
                <button type="submit" class="ts-btn ts-btn-sm">
                    <i class="dripicons-document-edit"></i> Save Changes
                </button>
            </div>

            @if(session('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="ts-card">
                        <div class="d-flex align-items-center mb-1" style="gap:8px;">
                            <i class="dripicons-calendar" style="color:#0b3f90;"></i>
                            <h5 class="mb-0" style="color:#0b3f90;font-weight:700;">Weekly Schedule</h5>
                        </div>
                        <p class="text-muted small mb-3">Define working hours for each day of the week.</p>

                        <div class="ts-lunch-box">
                            <label class="ts-label mb-1">Lunch Break (Minutes)</label>
                            <input type="number" name="lunch_break_minutes" id="lunch_break" class="ts-field"
                                   style="max-width:140px;" min="0" max="180"
                                   value="{{ old('lunch_break_minutes', $ww->lunch_break_minutes ?? 60) }}">
                            <div class="text-muted small mt-1">Deducted from daily total.</div>
                        </div>

                        @foreach($days as $day)
                            @php
                                $active = (bool) old($day, $ww->{$day});
                                $start = old($day.'_start', $ww->{$day.'_start'} ?: '08:00');
                                $end = old($day.'_end', $ww->{$day.'_end'} ?: '17:00');
                                $hrs = $summary['day_hours'][$day] ?? 0;
                            @endphp
                            <div class="ts-day-row {{ $active ? '' : 'is-off' }}" data-day="{{ $day }}">
                                <label class="mb-0 d-flex align-items-center" style="gap:10px;font-weight:600;">
                                    <input type="checkbox" name="{{ $day }}" value="1" class="day-toggle" @if($active) checked @endif>
                                    <span class="day-label">{{ $labels[$day] }}</span>
                                </label>
                                <span class="day-off text-muted" style="font-size:14px;@if($active) display:none; @endif">Day Off</span>
                                <div class="ts-day-times day-times" style="@if(!$active) display:none; @endif">
                                    <span class="text-muted small">From</span>
                                    <input type="time" name="{{ $day }}_start" class="ts-field day-start" value="{{ substr($start, 0, 5) }}">
                                    <span class="text-muted small">To</span>
                                    <input type="time" name="{{ $day }}_end" class="ts-field day-end" value="{{ substr($end, 0, 5) }}">
                                </div>
                                <span class="ts-day-hours day-hours-badge" style="@if(!$active) display:none; @endif">{{ number_format($hrs, 2) }}h</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="ts-summary">
                        <div class="d-flex align-items-center mb-3" style="gap:8px;">
                            <i class="dripicons-information"></i>
                            <strong style="font-size:1.05rem;">Summary</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4" style="font-size:15px;">
                            <span>Working Days</span>
                            <strong id="sum-days" style="font-size:1.15rem;">{{ $summary['working_days'] }}</strong>
                        </div>
                        <div style="opacity:.9;font-size:11px;letter-spacing:.06em;font-weight:700;margin-bottom:6px;">TOTAL EXPECTED HOURS</div>
                        <div class="gold" id="sum-hours">{{ number_format($summary['expected'], 2) }}h</div>
                        <div class="small mt-2" style="opacity:.8;">Per week based on current configuration.</div>
                        <div class="mt-4 p-3" style="background:rgba(0,0,0,.18);border-radius:10px;font-size:13px;line-height:1.4;">
                            Lunch break of <strong id="sum-lunch">{{ $ww->lunch_break_minutes ?? 60 }}</strong> min is deducted daily.
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
(function () {
    function parseHm(v) {
        if (!v) return null;
        var p = v.split(':');
        return (parseInt(p[0], 10) * 60) + parseInt(p[1] || 0, 10);
    }
    function dayHours(row, lunch) {
        if (!row.querySelector('.day-toggle').checked) return 0;
        var s = parseHm(row.querySelector('.day-start').value);
        var e = parseHm(row.querySelector('.day-end').value);
        if (s === null || e === null) return 0;
        var mins = e - s;
        if (mins < 0) mins += 24 * 60;
        mins -= lunch;
        if (mins < 0) mins = 0;
        return Math.round((mins / 60) * 100) / 100;
    }
    function refresh() {
        var lunch = parseInt(document.getElementById('lunch_break').value || '0', 10) || 0;
        var days = 0, total = 0;
        document.querySelectorAll('.ts-day-row').forEach(function (row) {
            var on = row.querySelector('.day-toggle').checked;
            row.classList.toggle('is-off', !on);
            row.querySelector('.day-times').style.display = on ? 'flex' : 'none';
            row.querySelector('.day-off').style.display = on ? 'none' : '';
            var badge = row.querySelector('.day-hours-badge');
            var h = dayHours(row, lunch);
            badge.style.display = on ? '' : 'none';
            badge.textContent = h.toFixed(2) + 'h';
            if (on) { days++; total += h; }
        });
        document.getElementById('sum-days').textContent = days;
        document.getElementById('sum-hours').textContent = total.toFixed(2) + 'h';
        document.getElementById('sum-lunch').textContent = lunch;
    }
    document.getElementById('ww-form').addEventListener('change', refresh);
    document.getElementById('ww-form').addEventListener('input', refresh);
    refresh();
})();
</script>
@endsection
