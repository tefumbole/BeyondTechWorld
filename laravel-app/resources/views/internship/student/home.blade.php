@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1180px;">
        <h1 class="ip-title"><i class="dripicons-meter"></i> My Dashboard</h1>
        <p class="ip-meta mb-3">Your internship program, this week’s hours, and what you can submit next.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @include('internship.student.partials.timesheet-reminder')

        @include('internship.student.partials.student-nav')

        @if($enrolment)
            <div class="ip-card">
                <div class="ip-meta">Internship program</div>
                <strong style="font-size:1.25rem;color:#0b3f90;">{{ optional($enrolment->program)->displayName() ?? '—' }}</strong>
                <div class="ip-meta mt-1">
                    Days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}
                    · {{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }} tasks accepted
                    · <span class="ip-badge {{ $enrolment->status === 'active' ? 'active' : 'warn' }}">{{ ucfirst($enrolment->status) }}</span>
                </div>
            </div>
        @else
            <div class="ip-card">
                <h5 style="color:#0b3f90;font-weight:700;">No active placement</h5>
                <p class="mb-0 text-muted">Ask an Internship Administrator to enrol you after your application is accepted.</p>
            </div>
        @endif

        <div class="row">
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="ip-stat-tile ip-stat-blue">
                    <div class="ip-meta">Current tasks</div>
                    <strong>{{ (int) $currentTaskCount }}</strong>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <a href="{{ route('internship.student.upload') }}" class="ip-stat-tile ip-stat-orange" style="display:block;text-decoration:none;color:#fff;">
                    <div class="ip-meta">Awaiting grading</div>
                    <strong>{{ (int) ($awaitingGradingCount ?? 0) }}</strong>
                    <div class="ip-meta">{{ ($awaitingGradingCount ?? 0) === 1 ? 'submission with supervisor' : 'submissions with supervisor' }}</div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="ip-stat-tile ip-stat-green">
                    <div class="ip-meta">Hours this week</div>
                    <strong>{{ number_format($weekScore['logged'], 1) }}</strong>
                    <div class="ip-meta">of {{ number_format($weekScore['expected'], 1) }}h</div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="ip-stat-tile ip-stat-blue">
                    <div class="ip-meta">Total hours</div>
                    <strong>{{ number_format($totalHours, 1) }}</strong>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="ip-stat-tile ip-stat-orange">
                    <div class="ip-meta">Undertime (week)</div>
                    <strong>{{ number_format($weekScore['remaining'], 1) }}h</strong>
                    <div class="ip-meta">Today: {{ number_format($dayBalance['remaining'], 1) }}h remaining</div>
                </div>
            </div>
            <div class="col-md-4 col-lg-2 mb-3">
                <div class="ip-stat-tile {{ $weekScore['overtime'] > 0 || $dayBalance['overtime'] > 0 ? 'ip-stat-orange' : 'ip-stat-green' }}">
                    <div class="ip-meta">Overtime</div>
                    <strong>{{ number_format($weekScore['overtime'], 1) }}h</strong>
                    <div class="ip-meta">
                        @if($weekScore['overtime'] > 0 || $dayBalance['overtime'] > 0)
                            Supervisor approval needed
                        @else
                            None this week
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($awaiting) && $awaiting->count())
            <div class="ip-card ip-pending">
                <h5 style="font-weight:700;color:#c2410c;">Awaiting grading</h5>
                @foreach($awaiting as $row)
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;padding:.5rem 0;">
                        <div>
                            <strong>Task #{{ $row->progression_day }} — {{ optional($row->task)->title ?: 'Task' }}</strong>
                            <div class="ip-meta">Submitted — your supervisor has not graded this yet</div>
                        </div>
                        <a class="ip-btn ip-btn-outline" href="{{ route('internship.student.task', $row->id) }}">View submission</a>
                    </div>
                @endforeach
            </div>
        @endif

        @if(!empty($gradeSummary) && (!empty($gradeSummary['score']) || !empty($gradeSummary['rubric']['rows'])))
            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Latest results</h5>
                @if(!empty($assignment) || !empty($lastPassed))
                    <p class="ip-meta mb-2">
                        Task #{{ optional($assignment ?: $lastPassed)->progression_day }}
                        — {{ optional(optional($assignment ?: $lastPassed)->task)->title }}
                    </p>
                @endif
                @include('internship.student.partials.grade-status', ['gradeSummary' => $gradeSummary])
            </div>
        @endif

        <div class="row">
            <div class="col-lg-7 mb-3">
                <div class="ip-card mb-0">
                    <div class="ip-chart-title">This week — logged vs expected</div>
                    <div class="ip-chart-box"><canvas id="ip-week-chart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5 mb-3">
                <div class="ip-card mb-0">
                    <div class="ip-chart-title">Hours by activity</div>
                    @if(count($byActivity))
                        <div class="ip-chart-box"><canvas id="ip-activity-chart"></canvas></div>
                    @else
                        <p class="text-muted mb-0">No hours logged yet. <a href="{{ route('timesheet.fill', ['date' => \App\Support\InternCompliance::timesheetFillDate(Auth::user()) ?: date('Y-m-d'), 'intern' => 1]) }}">Fill your timesheet</a> on a working day.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-7 mb-3">
                <div class="ip-card mb-0">
                    <div class="ip-chart-title">Hours by category</div>
                    @if(count($byCategory))
                        <div class="ip-chart-box"><canvas id="ip-category-chart"></canvas></div>
                    @else
                        <p class="text-muted mb-0">Create activities under a category, then log hours to see this chart.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-5 mb-3">
                <div class="ip-card mb-0">
                    <div class="ip-chart-title">Submit work</div>
                    @if(!empty($assignment))
                        <p class="mb-2"><strong>Task #{{ $assignment->progression_day }} — {{ $assignment->task->title ?? 'Task' }}</strong></p>
                        <p class="ip-meta mb-3">Status: {{ str_replace('_', ' ', $assignment->status) }}</p>
                        <a class="ip-btn mb-2" href="{{ route('internship.student.task', $assignment->id) }}">
                            <i class="dripicons-upload"></i>
                            @if($assignment->status === 'submitted') View submission
                            @elseif($assignment->status === 'revision_required') Re-submit
                            @else Submit evidence
                            @endif
                        </a>
                        <a class="ip-btn ip-btn-outline" href="{{ route('timesheet.fill', ['date' => optional($assignment->scheduled_work_date)->toDateString() ?: (\App\Support\InternCompliance::timesheetFillDate(Auth::user()) ?: date('Y-m-d')), 'intern' => 1, 'assignment' => $assignment->id]) }}">
                            <i class="dripicons-clock"></i> Log hours for this task
                        </a>
                    @elseif(!empty($requestState['can_request']))
                        <p>No task in your inbox. Request the next one in your program.</p>
                        <form method="POST" action="{{ route('internship.student.request') }}">
                            @csrf
                            <button class="ip-btn" type="submit"><i class="dripicons-download"></i> Request my task</button>
                        </form>
                    @else
                        <p class="text-muted mb-0">{{ $requestState['message'] ?? 'No submission is waiting right now.' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
@section('scripts')
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var week = @json($weekChart);
    var byActivity = @json($byActivity);
    var byCategory = @json($byCategory);

    try {
        var weekEl = document.getElementById('ip-week-chart');
        if (weekEl) {
            new Chart(weekEl, {
                type: 'bar',
                data: {
                    labels: (week && week.labels) ? week.labels : [],
                    datasets: [
                        { label: 'Logged', data: (week && week.logged) ? week.logged : [], backgroundColor: '#0b3f90' },
                        { label: 'Expected', data: (week && week.expected) ? week.expected : [], backgroundColor: '#c6ab47' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom' },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return v + 'h'; } } }],
                        xAxes: [{ gridLines: { display: false } }]
                    }
                }
            });
        }
    } catch (e) {}

    function doughnut(id, map, colors) {
        var el = document.getElementById(id);
        if (!el || !map || !Object.keys(map).length) return;
        try {
            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(map),
                    datasets: [{ data: Object.values(map), backgroundColor: colors, borderWidth: 0 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { position: 'bottom' }
                }
            });
        } catch (e) {}
    }

    doughnut('ip-activity-chart', byActivity, ['#0b3f90', '#2563eb', '#c6ab47', '#14b8a6', '#f59e0b', '#8b5cf6', '#e11d48']);
    doughnut('ip-category-chart', byCategory, ['#14b8a6', '#0b3f90', '#c6ab47', '#8b5cf6', '#f59e0b', '#64748b']);
})();
</script>
@endsection
