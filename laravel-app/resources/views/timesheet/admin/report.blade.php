@extends('layout.main')

@section('content')
@php
    $tsaTab = 'timesheet.admin.report';
    $view = $view ?? 'summary';
    $printBase = ['from' => $from, 'to' => $to, 'user_id' => $userId];
@endphp
<section class="forms">
    <div class="container-fluid ts-shell ts-shell-wide">
        @include('timesheet.partials.admin_tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="ts-title">Timesheet Reports</h1>
                <p class="ts-subtitle">Summary, detailed logs, and branded internship letters for a student, an employee, or everyone.</p>
            </div>
            @if($report)
                <div class="ts-actions">
                    <a class="ts-btn-outline" target="_blank" href="{{ route('timesheet.admin.report.print', $printBase + ['kind' => 'summary']) }}">
                        <i class="dripicons-print"></i> Print summary
                    </a>
                    <a class="ts-btn-outline" target="_blank" href="{{ route('timesheet.admin.report.print', $printBase + ['kind' => 'detailed']) }}">
                        <i class="dripicons-document"></i> Print detailed
                    </a>
                    <a class="ts-btn-gold" target="_blank" href="{{ route('timesheet.admin.report.print', $printBase + ['kind' => 'internship']) }}">
                        <i class="dripicons-document-new"></i> Internship letter
                    </a>
                </div>
            @endif
        </div>

        <div class="ts-card ts-card-accent mb-4">
            <form method="GET" action="{{ route('timesheet.admin.report') }}" class="row align-items-end">
                <input type="hidden" name="generate" value="1">
                <input type="hidden" name="view" value="{{ $view }}">
                <div class="col-md-3 mb-2">
                    <label class="ts-label">From</label>
                    <input type="date" name="from" class="ts-field" value="{{ $from }}" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ts-label">To</label>
                    <input type="date" name="to" class="ts-field" value="{{ $to }}" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="ts-label">Student or employee</label>
                    <select name="user_id" class="ts-field">
                        <option value="all" @if(($userId ?? 'all')==='all') selected @endif>Everyone</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @if(($userId ?? '')==(string)$emp->id) selected @endif>{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="ts-btn"><i class="dripicons-experiment"></i> Generate</button>
                </div>
            </form>
        </div>

        @if($report)
            <div class="ts-kpis mb-4">
                <div class="ts-kpi">
                    <div class="ts-kpi-label">Hours logged</div>
                    <div class="ts-kpi-value">{{ number_format($report['total_logged'], 1) }}</div>
                    <div class="ts-kpi-sub">{{ $report['total_entries'] }} {{ \Illuminate\Support\Str::plural('entry', $report['total_entries']) }}</div>
                </div>
                <div class="ts-kpi is-gold">
                    <div class="ts-kpi-label">Hours expected</div>
                    <div class="ts-kpi-value">{{ number_format($report['total_expected'], 1) }}</div>
                    <div class="ts-kpi-sub">Working week for the period</div>
                </div>
                <div class="ts-kpi {{ $report['total_logged'] >= $report['total_expected'] && $report['total_expected'] > 0 ? 'is-green' : 'is-orange' }}">
                    <div class="ts-kpi-label">Variance</div>
                    <div class="ts-kpi-value">
                        {{ $report['total_logged'] - $report['total_expected'] >= 0 ? '+' : '' }}{{ number_format($report['total_logged'] - $report['total_expected'], 1) }}
                    </div>
                    <div class="ts-kpi-sub">Logged minus expected</div>
                </div>
                <div class="ts-kpi">
                    <div class="ts-kpi-label">Activities</div>
                    <div class="ts-kpi-value">{{ $report['total_activities'] }}</div>
                    <div class="ts-kpi-sub">Distinct work items</div>
                </div>
                <div class="ts-kpi">
                    <div class="ts-kpi-label">{{ $report['scope'] === 'one' ? 'Person' : 'People' }}</div>
                    <div class="ts-kpi-value">{{ $report['people_count'] }}</div>
                    <div class="ts-kpi-sub">{{ \Carbon\Carbon::parse($from)->format('j M') }} – {{ \Carbon\Carbon::parse($to)->format('j M Y') }}</div>
                </div>
            </div>

            <div class="ts-view-tabs" role="tablist">
                <a href="#ts-view-summary" class="{{ $view === 'summary' ? 'is-active' : '' }}" data-ts-view="summary">Summary</a>
                <a href="#ts-view-detailed" class="{{ $view === 'detailed' ? 'is-active' : '' }}" data-ts-view="detailed">Detailed</a>
                <a href="#ts-view-internship" class="{{ $view === 'internship' ? 'is-active' : '' }}" data-ts-view="internship">Internship report</a>
            </div>

            <div id="ts-view-summary" class="ts-view-panel" @if($view !== 'summary') style="display:none" @endif>
                <div class="row">
                    <div class="col-lg-7 mb-3">
                        <div class="ts-card mb-0">
                            <h5 style="color:#0b3f90;font-weight:700;">Expected hours vs hours put in</h5>
                            <p class="text-muted small mb-2">Working-week target against hours actually logged.</p>
                            <div class="ts-chart-box"><canvas id="ts-hours-chart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-lg-5 mb-3">
                        <div class="ts-card mb-0">
                            <h5 style="color:#0b3f90;font-weight:700;">Activities &amp; hours</h5>
                            @if(count($report['by_activity']))
                                <div class="ts-chart-box"><canvas id="ts-activity-chart"></canvas></div>
                            @else
                                <p class="text-muted mb-0">No activities logged in this period.</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if($report['scope'] === 'all' && count($report['people']) > 1)
                    <div class="ts-card">
                        <h5 style="color:#0b3f90;font-weight:700;">Everyone — logged vs expected</h5>
                        <div class="ts-chart-box" style="height:320px;"><canvas id="ts-people-chart"></canvas></div>
                    </div>
                @endif

                @foreach($report['people'] as $person)
                    <div class="ts-card">
                        <div class="ts-person-head">
                            <div>
                                <h3 class="ts-person-name">{{ $person['name'] }}</h3>
                                <span class="ts-matricule">Matricule {{ $person['matricule'] }}</span>
                                @if($person['is_intern'] && $person['program'])
                                    <div class="text-muted small mt-1">{{ $person['program'] }}
                                        @if(!empty($person['duration']['label']))
                                            · {{ $person['duration']['label'] }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div style="font-weight:800;color:#0b3f90;">{{ number_format($person['logged'], 1) }}h / {{ number_format($person['expected'], 1) }}h</div>
                                <div class="small text-muted">{{ $person['activity_count'] }} {{ \Illuminate\Support\Str::plural('activity', $person['activity_count']) }}</div>
                            </div>
                        </div>
                        @php $pct = $person['expected'] > 0 ? min(100, round($person['logged'] / $person['expected'] * 100)) : 0; @endphp
                        <div class="ts-progress mb-3"><span style="width:{{ $pct }}%;"></span></div>
                        @if(count($person['by_activity']))
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead><tr><th>Activity</th><th class="text-right">Hours</th></tr></thead>
                                    <tbody>
                                        @foreach($person['by_activity'] as $act => $hrs)
                                            <tr>
                                                <td>{{ $act }}</td>
                                                <td class="text-right">{{ number_format($hrs, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No hours logged for this person in the selected period.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div id="ts-view-detailed" class="ts-view-panel" @if($view !== 'detailed') style="display:none" @endif>
                <div class="ts-card">
                    <h5 style="color:#0b3f90;font-weight:700;">What they did</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Activity</th>
                                    <th>Task</th>
                                    <th class="text-right">Hours</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['rows'] as $row)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($row->entry_date)->format('M j, Y') }}</td>
                                        <td>{{ $row->employee_name ?: '—' }}</td>
                                        <td>{{ $row->activity_name ?: '—' }}</td>
                                        <td class="text-muted small">
                                            @if($row->assignment)
                                                Day {{ $row->assignment->progression_day }}
                                                @if($row->assignment->task)
                                                    — {{ \Illuminate\Support\Str::limit($row->assignment->task->title, 40) }}
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format((float) $row->hours, 2) }}</td>
                                        <td class="text-muted">{{ \Illuminate\Support\Str::limit($row->notes, 60) ?: '—' }}</td>
                                        <td><span class="ts-badge">{{ str_replace('_', ' ', $row->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No entries in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="ts-view-internship" class="ts-view-panel" @if($view !== 'internship') style="display:none" @endif>
                @php
                    $interns = $report['interns'] ?? [];
                    if ($report['scope'] === 'one') {
                        $interns = $report['people'];
                    }
                @endphp
                @forelse($interns as $person)
                    <div class="ts-card ts-card-accent">
                        <div class="ts-person-head">
                            <div>
                                <div class="text-muted small text-uppercase" style="letter-spacing:.08em;font-weight:700;">Beyond Enterprise · Internship report</div>
                                <h3 class="ts-person-name">{{ $person['name'] }}</h3>
                                <span class="ts-matricule">Matricule {{ $person['matricule'] }}</span>
                                @if($person['program'])
                                    <div class="mt-1">{{ $person['program'] }}</div>
                                @endif
                                @if(!empty($person['duration']['label']))
                                    <div class="text-muted small">Duration: {{ $person['duration']['label'] }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="ts-kpi-value" style="font-size:1.4rem;">{{ $person['activity_count'] }}</div>
                                <div class="small text-muted">Activities carried out</div>
                                <a class="ts-btn-gold mt-2" target="_blank" href="{{ route('timesheet.admin.report.print', $printBase + ['kind' => 'internship', 'user_id' => $person['identity']]) }}">
                                    Print letter
                                </a>
                            </div>
                        </div>
                        <p class="mb-2">
                            <strong>{{ number_format($person['logged'], 1) }}h</strong> logged of
                            <strong>{{ number_format($person['expected'], 1) }}h</strong> expected
                            · {{ $person['entry_count'] }} {{ \Illuminate\Support\Str::plural('entry', $person['entry_count']) }}
                        </p>
                        @if(count($person['by_activity']))
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead><tr><th>Activity</th><th class="text-right">Hours</th></tr></thead>
                                    <tbody>
                                        @foreach($person['by_activity'] as $act => $hrs)
                                            <tr>
                                                <td>{{ $act }}</td>
                                                <td class="text-right">{{ number_format($hrs, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="ts-card">
                        <p class="text-muted mb-0">No internship students in this selection. Choose a student, or generate for everyone who logged hours.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</section>

@if($report)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var hours = @json($report['chart_hours']);
    var activities = @json($report['by_activity']);
    var people = @json($report['chart_people']);
    var colors = ['#0b3f90', '#c6ab47', '#14b8a6', '#2563eb', '#f59e0b', '#8b5cf6', '#e11d48', '#64748b'];

    if (document.getElementById('ts-hours-chart')) {
        new Chart(document.getElementById('ts-hours-chart'), {
            type: 'bar',
            data: {
                labels: hours.labels || [],
                datasets: [
                    { label: 'Hours put in', data: hours.logged || [], backgroundColor: '#0b3f90' },
                    { label: 'Expected hours', data: hours.expected || [], backgroundColor: '#c6ab47' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return v + 'h'; } } } }
            }
        });
    }

    if (document.getElementById('ts-activity-chart') && activities && Object.keys(activities).length) {
        new Chart(document.getElementById('ts-activity-chart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(activities),
                datasets: [{ data: Object.values(activities), backgroundColor: colors, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    if (document.getElementById('ts-people-chart') && people && (people.labels || []).length > 1) {
        new Chart(document.getElementById('ts-people-chart'), {
            type: 'bar',
            data: {
                labels: people.labels || [],
                datasets: [
                    { label: 'Hours put in', data: people.logged || [], backgroundColor: '#0b3f90' },
                    { label: 'Expected hours', data: people.expected || [], backgroundColor: '#c6ab47' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { beginAtZero: true, ticks: { callback: function (v) { return v + 'h'; } } } }
            }
        });
    }

    var tabs = document.querySelectorAll('[data-ts-view]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            var view = tab.getAttribute('data-ts-view');
            tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            document.querySelectorAll('.ts-view-panel').forEach(function (p) {
                p.style.display = p.id === 'ts-view-' + view ? '' : 'none';
            });
            var url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.history.replaceState({}, '', url.toString());
        });
    });
})();
</script>
@endif
@endsection
