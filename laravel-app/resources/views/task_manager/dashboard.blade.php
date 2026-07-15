@extends('layout.main')

@section('content')
@php
    $tmTab = 'tasks.dashboard';
    $pending = (int) ($stats['pending'] ?? 0);
    $inProgress = (int) ($stats['in_progress'] ?? 0);
    $completed = (int) ($stats['completed'] ?? 0);
    $overdue = (int) ($stats['overdue'] ?? 0);
    $chartMax = max(1, $pending, $inProgress, $completed, $overdue);
@endphp
<section class="forms">
    <div class="container-fluid tm-shell">
        @include('task_manager.partials.tabs')

        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap" style="gap:12px;">
            <div>
                <h1 class="tm-title">Task Dashboard</h1>
                <p class="tm-subtitle">Overview of team assignments — click a stat to open that list.</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ route('tasks.dashboard') }}" class="tm-btn-outline"><i class="dripicons-clockwise"></i> Refresh</a>
                @if(in_array('tasks.create', $all_permission ?? []))
                    <a href="{{ route('tasks.create') }}" class="tm-btn-primary"><i class="dripicons-plus"></i> New Task</a>
                @endif
            </div>
        </div>

        <div class="row">
            @foreach([
                ['Total Tasks', $stats['total'], 'dripicons-checklist', 'blue', route('tasks.index')],
                ['Pending', $pending, 'dripicons-clock', 'gray', route('tasks.index', ['status' => 'Pending'])],
                ['In Progress', $inProgress, 'dripicons-clockwise', 'yellow', route('tasks.index', ['status' => 'In Progress'])],
                ['Completed', $completed, 'dripicons-checkmark', 'green', route('tasks.index', ['status' => 'Completed'])],
                ['Overdue', $overdue, 'dripicons-warning', 'red', route('tasks.index', ['status' => 'Overdue'])],
            ] as $card)
                <div class="col-6 col-md-4 col-xl mb-3">
                    <a href="{{ $card[4] }}" class="tm-stat">
                        <div class="tm-stat-row">
                            <div>
                                <p class="tm-stat-label">{{ $card[0] }}</p>
                                <p class="tm-stat-value">{{ $card[1] }}</p>
                            </div>
                            <div class="tm-stat-icon {{ $card[3] }}"><i class="{{ $card[2] }}"></i></div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row mt-2">
            <div class="col-lg-6 mb-3">
                <div class="tm-panel">
                    <h5><i class="dripicons-graph-bar"></i> Tasks by Status</h5>
                    <div style="height:280px;position:relative;">
                        <canvas id="tm-bar-chart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="tm-panel">
                    <h5><i class="dripicons-graph-pie"></i> Status Distribution</h5>
                    <div style="height:240px;position:relative;">
                        <canvas id="tm-pie-chart"></canvas>
                    </div>
                    <div class="tm-chip-legend">
                        <span><i style="background:#94a3b8;"></i> Pending</span>
                        <span><i style="background:#eab308;"></i> In Progress</span>
                        <span><i style="background:#22c55e;"></i> Completed</span>
                        <span><i style="background:#ef4444;"></i> Overdue</span>
                    </div>
                    <p class="text-muted small mb-0 mt-3">Total active workload: <strong>{{ $stats['open'] }} open tasks</strong>.</p>
                </div>
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
    var labels = ['Pending', 'In Progress', 'Completed', 'Overdue'];
    var values = [{{ $pending }}, {{ $inProgress }}, {{ $completed }}, {{ $overdue }}];
    var colors = ['#94a3b8', '#eab308', '#22c55e', '#ef4444'];

    var barCtx = document.getElementById('tm-bar-chart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 8,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: {{ $chartMax }},
                        ticks: { precision: 0 },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    var pieCtx = document.getElementById('tm-pie-chart');
    if (pieCtx) {
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.label + ': ' + ctx.raw;
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
@endsection
