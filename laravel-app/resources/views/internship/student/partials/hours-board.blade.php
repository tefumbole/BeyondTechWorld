@php
    $weekScore = $weekScore ?? [
        'expected' => 40,
        'logged' => 0,
        'accounted' => 0,
        'remaining' => 40,
        'overtime' => 0,
        'percent' => 0,
        'status' => 'undertime',
        'met' => false,
        'week_start' => null,
        'week_end' => null,
        'days' => [],
    ];
    $expected = (float) ($weekScore['expected'] ?? 40);
    $logged = (float) ($weekScore['logged'] ?? 0);
    $accounted = (float) ($weekScore['accounted'] ?? min($logged, $expected));
    $remaining = (float) ($weekScore['remaining'] ?? max(0, $expected - $accounted));
    $overtime = (float) ($weekScore['overtime'] ?? max(0, $logged - $expected));
    $percent = (float) ($weekScore['percent'] ?? ($expected > 0 ? min(100, ($accounted / $expected) * 100) : 0));
    $status = $weekScore['status'] ?? ($remaining > 0.009 ? 'undertime' : ($overtime > 0.009 ? 'overtime' : 'met'));
    $statusLabel = [
        'undertime' => 'Undertime — below the weekly target',
        'met' => 'Week target met',
        'overtime' => 'Overtime — hours above the weekly target',
    ][$status] ?? 'Hours this week';
    $statusColor = [
        'undertime' => '#c2410c',
        'met' => '#15803d',
        'overtime' => '#b45309',
    ][$status] ?? '#0b3f90';
@endphp
<div class="ip-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px;margin-bottom:1rem;">
        <div>
            <h5 style="font-weight:700;color:#0b3f90;margin:0;">Hours this week</h5>
            <div class="ip-meta mt-1">
                @if (! empty($weekScore['week_start']))
                    {{ \Carbon\Carbon::parse($weekScore['week_start'])->format('D d M') }}
                    –
                    {{ \Carbon\Carbon::parse($weekScore['week_end'])->format('D d M Y') }}
                @endif
                · Target {{ number_format($expected, 1) }}h (max counted). Extra hours are overtime.
            </div>
        </div>
        <span class="ip-badge" style="background:{{ $statusColor }};color:#fff;">{{ $statusLabel }}</span>
    </div>

    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="ip-stat-tile ip-stat-blue">
                <div class="ip-meta">Expected hours</div>
                <strong>{{ number_format($expected, 1) }}h</strong>
                <div class="ip-meta">Weekly target (≈8h × working days, capped at 40h)</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="ip-stat-tile ip-stat-green">
                <div class="ip-meta">Hours accounted for</div>
                <strong>{{ number_format($accounted, 1) }}h</strong>
                <div class="ip-meta">
                    @if ($logged > $accounted + 0.009)
                        {{ number_format($logged, 1) }}h logged · only {{ number_format($accounted, 1) }}h count
                    @else
                        Logged toward the weekly target
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="ip-stat-tile {{ $percent >= 100 ? 'ip-stat-green' : 'ip-stat-orange' }}">
                <div class="ip-meta">% of hours worked</div>
                <strong>{{ number_format($percent, 1) }}%</strong>
                <div class="ip-meta">{{ number_format($accounted, 1) }} / {{ number_format($expected, 1) }}h (never above 100%)</div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            @if ($status === 'undertime')
                <div class="ip-stat-tile ip-stat-orange">
                    <div class="ip-meta">Undertime</div>
                    <strong>{{ number_format($remaining, 1) }}h</strong>
                    <div class="ip-meta">Still short of this week’s {{ number_format($expected, 1) }}h target</div>
                </div>
            @else
                <div class="ip-stat-tile {{ $overtime > 0.009 ? 'ip-stat-orange' : 'ip-stat-green' }}">
                    <div class="ip-meta">Overtime</div>
                    <strong>{{ number_format($overtime, 1) }}h</strong>
                    <div class="ip-meta">
                        @if ($overtime > 0.009)
                            Above target — needs supervisor approval
                        @else
                            No overtime this week
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="ip-progress-wrap" style="max-width:100%;margin-bottom:.75rem;">
        <div class="ip-progress-bar" style="height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
            <span style="display:block;height:100%;width:{{ min(100, $percent) }}%;background:{{ $statusColor }};"></span>
        </div>
        <div class="ip-progress-label mt-1">
            Accounted {{ number_format($accounted, 1) }}h of {{ number_format($expected, 1) }}h
            @if ($overtime > 0.009)
                · +{{ number_format($overtime, 1) }}h overtime (not counted in %)
            @endif
        </div>
    </div>

    @if (! empty($weekScore['days']))
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="min-width:560px;">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Expected</th>
                        <th>Logged</th>
                        <th>Counted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekScore['days'] as $day)
                        @php
                            $dayExpected = (float) ($day['expected'] ?? 0);
                            $dayLogged = (float) ($day['logged'] ?? 0);
                            $dayAccounted = (float) ($day['accounted'] ?? min($dayLogged, $dayExpected));
                            $dayOt = (float) ($day['overtime'] ?? max(0, $dayLogged - $dayExpected));
                            $dayShort = (float) ($day['remaining'] ?? max(0, $dayExpected - $dayAccounted));
                        @endphp
                        @if ($dayExpected > 0 || $dayLogged > 0)
                            <tr>
                                <td>
                                    <strong>{{ ucfirst(substr($day['day'], 0, 3)) }}</strong>
                                    <span class="ip-meta">{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}</span>
                                </td>
                                <td>{{ number_format($dayExpected, 1) }}h</td>
                                <td>{{ number_format($dayLogged, 1) }}h</td>
                                <td>{{ number_format($dayAccounted, 1) }}h</td>
                                <td>
                                    @if ($dayExpected <= 0 && $dayLogged > 0)
                                        <span style="color:#b45309;">Non-working day OT</span>
                                    @elseif ($dayShort > 0.009)
                                        <span style="color:#c2410c;">Undertime {{ number_format($dayShort, 1) }}h</span>
                                    @elseif ($dayOt > 0.009)
                                        <span style="color:#b45309;">OT {{ number_format($dayOt, 1) }}h</span>
                                    @else
                                        <span style="color:#15803d;">Complete</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="ip-meta mb-0 mt-3">
        Log about <strong>8 hours</strong> on each working day.
        Only up to <strong>{{ number_format($expected, 1) }}h</strong> counts toward this week’s completion percentage.
        <a href="{{ route('timesheet.fill', ['date' => \App\Support\InternCompliance::timesheetFillDate(Auth::user()) ?: date('Y-m-d'), 'intern' => 1]) }}">Fill timesheet</a>
    </p>
</div>
