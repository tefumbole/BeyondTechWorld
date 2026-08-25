@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1180px;">
        <h1 class="ip-title">Internships</h1>
        <p class="ip-meta mb-4">Manage programs, supervisors, and accepted interns from one place.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.programs') }}" class="ip-hub-card ip-hub-teal">
                    <div class="ip-hub-icon"><i class="fa fa-book"></i></div>
                    <h3>Programs</h3>
                    <p>View and edit the 180-day curriculum tracks.</p>
                    <strong class="ip-hub-stat">{{ $stats['programs'] }} published</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.supervisors') }}" class="ip-hub-card ip-hub-amber">
                    <div class="ip-hub-icon"><i class="fa fa-user-tie"></i></div>
                    <h3>Supervisors</h3>
                    <p>People assigned to supervise internship placements.</p>
                    <strong class="ip-hub-stat">{{ $stats['supervisors'] }} supervisors</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.interns') }}" class="ip-hub-card ip-hub-violet">
                    <div class="ip-hub-icon"><i class="fa fa-users"></i></div>
                    <h3>Interns</h3>
                    <p>Accepted or hired applicants — assign programs and supervisors.</p>
                    <strong class="ip-hub-stat">{{ (int) ($stats['interns_ready'] ?? 0) }} ready to assign</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.supervisor.index') }}" class="ip-hub-card ip-hub-rose">
                    <div class="ip-hub-icon"><i class="fa fa-check-square"></i></div>
                    <h3>Grade submissions</h3>
                    <p>Open the supervisor queue, review evidence, and accept work so the next task is released.</p>
                    <strong class="ip-hub-stat">{{ (int) ($stats['pending_review'] ?? 0) }} awaiting review</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.supervisor.dashboard') }}" class="ip-hub-card ip-hub-teal">
                    <div class="ip-hub-icon"><i class="fa fa-clipboard"></i></div>
                    <h3>Supervisor home</h3>
                    <p>Interns, open tasks, and recent submissions — the same workspace supervisors use.</p>
                    <strong class="ip-hub-stat">Open supervisor workspace</strong>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="ip-stat-tile ip-stat-blue">
                    <div class="ip-meta">Active placements</div>
                    <strong>{{ $stats['active_enrolments'] }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="ip-stat-tile ip-stat-green">
                    <div class="ip-meta">Completed</div>
                    <strong>{{ $stats['completed'] }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.supervisor.index') }}" class="ip-stat-tile ip-stat-orange" style="display:block;text-decoration:none;color:#fff;">
                    <div class="ip-meta">Awaiting review</div>
                    <strong>{{ $stats['pending_review'] }}</strong>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-3">
                <div class="ip-card mb-0">
                    <h5 class="ip-chart-title">Placement status</h5>
                    <div class="ip-chart-box"><canvas id="ip-placement-chart" height="220"></canvas></div>
                </div>
            </div>
            <div class="col-lg-7 mb-3">
                <div class="ip-card mb-0">
                    <h5 class="ip-chart-title">Placements by program</h5>
                    <div class="ip-chart-box"><canvas id="ip-program-chart" height="220"></canvas></div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <div class="ip-card mb-0">
                    <h5 class="ip-chart-title">Task pipeline</h5>
                    <div class="ip-chart-box" style="max-width:720px;margin:0 auto;"><canvas id="ip-task-chart" height="200"></canvas></div>
                </div>
            </div>
        </div>

        <div class="ip-card mt-1">
            <div class="ip-meta mb-2">More tools</div>
            <div class="ip-nav mb-0">
                <a class="ip-btn" href="{{ route('internship.tasks') }}">Task Manager</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.enrolments') }}">Enrolments</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.import') }}">Import curriculum</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Grade queue</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.reports') }}">Reports</a>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    var placementLabels = @json($placementChart['labels'] ?? []);
    var placementValues = @json($placementChart['values'] ?? []);
    var programLabels = @json($programChart['labels'] ?? []);
    var programValues = @json($programChart['values'] ?? []);
    var taskLabels = @json($taskStatusChart['labels'] ?? []);
    var taskValues = @json($taskStatusChart['values'] ?? []);

    var placementColors = ['#0ea5e9', '#f59e0b', '#22c55e'];
    var programColors = ['#0b3f90', '#14b8a6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#84cc16', '#ec4899', '#6366f1', '#f97316', '#10b981'];
    var taskColors = ['#38bdf8', '#eab308', '#f97316', '#22c55e'];

    var pie = document.getElementById('ip-placement-chart');
    if (pie) {
        new Chart(pie, {
            type: 'doughnut',
            data: {
                labels: placementLabels,
                datasets: [{
                    data: placementValues.some(function (v) { return v > 0; }) ? placementValues : [1],
                    backgroundColor: placementValues.some(function (v) { return v > 0; }) ? placementColors : ['#e2e8f0'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } }
                },
                cutout: '58%'
            }
        });
    }

    var bar = document.getElementById('ip-program-chart');
    if (bar) {
        new Chart(bar, {
            type: 'bar',
            data: {
                labels: programLabels.length ? programLabels : ['No placements yet'],
                datasets: [{
                    data: programValues.length ? programValues : [0],
                    backgroundColor: programLabels.map(function (_, i) { return programColors[i % programColors.length]; }),
                    borderRadius: 8,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 0, font: { size: 11 } } }
                }
            }
        });
    }

    var task = document.getElementById('ip-task-chart');
    if (task) {
        new Chart(task, {
            type: 'bar',
            data: {
                labels: taskLabels,
                datasets: [{
                    data: taskValues,
                    backgroundColor: taskColors,
                    borderRadius: 8,
                    maxBarThickness: 56
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
})();
</script>
@endsection
