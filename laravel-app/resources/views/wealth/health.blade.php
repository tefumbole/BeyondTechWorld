@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Financial Health</h1>
        <p class="text-muted">Internal discipline score for {{ $filter->periodLabel() }}. This is not a bank credit score.</p>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')

        <div class="wm-card wm-health">
            <div>
                <div class="wm-score">{{ $health['overall_score'] }} / 100</div>
                <div class="wm-status-{{ $health['status'] }}" style="font-weight:800;font-size:1.1rem;">{{ $health['status_label'] }}</div>
                <div>{{ $health['trend'] === 'IMPROVING' ? '↑ Improving' : ($health['trend'] === 'DECLINING' ? '↓ Declining' : '→ Stable') }}
                    vs {{ $filter->previousPeriod()->periodLabel() }} · previous {{ $health['previous_score'] }} ({{ $health['score_change'] >= 0 ? '+' : '' }}{{ $health['score_change'] }})</div>
            </div>
        </div>

        <div class="wm-kpis mb-3">
            <div class="wm-kpi">
                <div class="lbl">Income this month</div>
                <div class="val">{{ number_format($health['income']['total'], 2) }}</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">You can spend</div>
                <div class="val">{{ number_format(max(0, (float) $health['operations']['remaining']), 2) }}</div>
                <div class="small text-muted">70% operations left of {{ number_format($health['operations']['expected'], 2) }}</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">Due for investment</div>
                <div class="val">{{ number_format(max(0, (float) $health['investment']['remaining']), 2) }}</div>
                <div class="small text-muted">20% · invested {{ number_format($health['investment']['used'], 2) }} of {{ number_format($health['investment']['expected'], 2) }}</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">Due for giving</div>
                <div class="val">{{ number_format(max(0, (float) $health['charity']['remaining']), 2) }}</div>
                <div class="small text-muted">10% · given {{ number_format($health['charity']['used'], 2) }} of {{ number_format($health['charity']['expected'], 2) }}</div>
            </div>
        </div>

        <div class="row">
            @foreach([
                ['Income vs expenses', $health['expenses']['ratio'].'% of income spent', 'Income '.$health['income']['total'], 'Expenses '.$health['expenses']['total']],
                ['Cash balance', $health['cash']['balance'], $health['cash']['ratio'].'% of income', ''],
            ] as $card)
                <div class="col-md-6">
                    <div class="wm-card">
                        <strong>{{ $card[0] }}</strong>
                        <div>{{ $card[1] }}</div>
                        <div class="small text-muted">{{ $card[2] }} {{ $card[3] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="wm-card">
                    <h5 class="wm-chart-title">Score this month</h5>
                    <div class="wm-gauge">
                        <canvas id="wm-health-score"></canvas>
                        <div class="wm-gauge-center">
                            <strong>{{ (int) $health['overall_score'] }}</strong>
                            <span>{{ $health['status_label'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="wm-card">
                    <h5 class="wm-chart-title">Health over the last 12 months</h5>
                    <canvas id="wm-health-hist" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="wm-card">
                    <h5 class="wm-chart-title">70 / 20 / 10 this month</h5>
                    <canvas id="wm-health-alloc" height="160"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="wm-card">
                    <h5 class="wm-chart-title">Income vs expenses</h5>
                    <canvas id="wm-health-ie" height="160"></canvas>
                </div>
            </div>
        </div>

        <div class="wm-card">
            <h5 class="wm-chart-title">Where this month’s income should go</h5>
            <canvas id="wm-health-envelope" height="110"></canvas>
        </div>

        <div class="wm-card">
            <h5>Warnings</h5>
            <ul class="mb-0">
                @forelse($health['warnings'] as $w)
                    <li class="wm-warn-{{ $w['severity'] }}"><strong>{{ $w['severity'] }}</strong> — {{ $w['message'] }}</li>
                @empty
                    <li class="text-muted">No warnings for this period.</li>
                @endforelse
            </ul>
        </div>
        <div class="wm-card">
            <h5>Recommendations</h5>
            <ul class="mb-0">
                @forelse($health['recommendations'] as $r)
                    <li>{{ $r }}</li>
                @empty
                    <li class="text-muted">No recommendations right now.</li>
                @endforelse
            </ul>
        </div>
    </div>
</section>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.global.defaultFontFamily = 'inherit';
    Chart.defaults.global.defaultFontColor = '#475569';
    var score = {{ (int) $health['overall_score'] }};
    var income = {{ (float) $health['income']['total'] }};
    var expenses = {{ (float) $health['expenses']['total'] }};
    var opsE = {{ (float) $health['operations']['expected'] }};
    var opsU = {{ (float) $health['operations']['used'] }};
    var opsR = Math.max(0, {{ (float) $health['operations']['remaining'] }});
    var invE = {{ (float) $health['investment']['expected'] }};
    var invU = {{ (float) $health['investment']['used'] }};
    var invR = Math.max(0, {{ (float) $health['investment']['remaining'] }});
    var chaE = {{ (float) $health['charity']['expected'] }};
    var chaU = {{ (float) $health['charity']['used'] }};
    var chaR = Math.max(0, {{ (float) $health['charity']['remaining'] }});
    var hist = @json($history);

    new Chart(document.getElementById('wm-health-score'), {
        type: 'doughnut',
        data: {
            labels: ['Score', 'To 100'],
            datasets: [{
                data: [score, Math.max(0, 100 - score)],
                backgroundColor: ['#0b3f90', '#e8eef8'],
                borderWidth: 0
            }]
        },
        options: {
            cutoutPercentage: 72,
            legend: { display: false },
            tooltips: { enabled: false }
        }
    });

    new Chart(document.getElementById('wm-health-hist'), {
        type: 'line',
        data: {
            labels: hist.map(function (h) { return h.label; }),
            datasets: [{
                label: 'Financial Health',
                borderColor: '#0b3f90',
                backgroundColor: 'rgba(11,63,144,.12)',
                pointBackgroundColor: '#0b3f90',
                lineTension: 0.25,
                data: hist.map(function (h) { return h.score; })
            }]
        },
        options: {
            legend: { display: false },
            scales: {
                yAxes: [{ ticks: { min: 0, max: 100, stepSize: 20 } }]
            }
        }
    });

    new Chart(document.getElementById('wm-health-alloc'), {
        type: 'bar',
        data: {
            labels: ['You can spend (70%)', 'Investment (20%)', 'Giving (10%)'],
            datasets: [
                { label: 'Expected', backgroundColor: '#cbd5e1', data: [opsE, invE, chaE] },
                { label: 'Used', backgroundColor: '#0b3f90', data: [opsU, invU, chaU] },
                { label: 'Still due / available', backgroundColor: '#10b981', data: [opsR, invR, chaR] }
            ]
        },
        options: {
            legend: { position: 'bottom' },
            scales: {
                yAxes: [{ ticks: { beginAtZero: true } }]
            }
        }
    });

    new Chart(document.getElementById('wm-health-ie'), {
        type: 'doughnut',
        data: {
            labels: ['Income', 'Expenses'],
            datasets: [{
                data: [Math.max(income, 0), Math.max(expenses, 0)],
                backgroundColor: ['#0b3f90', '#e91e8c'],
                borderWidth: 0
            }]
        },
        options: { legend: { position: 'bottom' } }
    });

    new Chart(document.getElementById('wm-health-envelope'), {
        type: 'horizontalBar',
        data: {
            labels: ['This month'],
            datasets: [
                { label: 'You can spend', backgroundColor: '#0b3f90', data: [opsR] },
                { label: 'Due for investment', backgroundColor: '#c6ab47', data: [invR] },
                { label: 'Due for giving', backgroundColor: '#10b981', data: [chaR] },
                { label: 'Already spent', backgroundColor: '#e91e8c', data: [opsU + invU + chaU] }
            ]
        },
        options: {
            legend: { position: 'bottom' },
            scales: {
                xAxes: [{ stacked: true, ticks: { beginAtZero: true } }],
                yAxes: [{ stacked: true }]
            }
        }
    });
})();
</script>
@endsection

