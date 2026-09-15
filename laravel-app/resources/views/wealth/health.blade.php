@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Financial Health</h1>
        <p class="text-muted">Internal discipline score based on Wealth Manager rules. This is not a bank credit score.</p>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')

        <div class="wm-card wm-health">
            <div>
                <div class="wm-score">{{ $health['overall_score'] }} / 100</div>
                <div class="wm-status-{{ $health['status'] }}" style="font-weight:800;font-size:1.1rem;">{{ $health['status_label'] }}</div>
                <div>{{ $health['trend'] === 'IMPROVING' ? '↑ Improving' : ($health['trend'] === 'DECLINING' ? '↓ Declining' : '→ Stable') }}
                    · previous {{ $health['previous_score'] }} ({{ $health['score_change'] >= 0 ? '+' : '' }}{{ $health['score_change'] }})</div>
            </div>
        </div>

        <div class="row">
            @foreach([
                ['Income vs expenses', $health['expenses']['ratio'].'% of income spent', $health['income']['total'], $health['expenses']['total']],
                ['Cash balance', $health['cash']['balance'], $health['cash']['ratio'].'% of income', ''],
                ['70% Operations', 'Used '.$health['operations']['used'], 'Expected '.$health['operations']['expected'], 'Left '.$health['operations']['remaining']],
                ['20% Investment', 'Invested '.$health['investment']['used'], 'Expected '.$health['investment']['expected'], $health['investment']['fulfillment_percentage'].'%'],
                ['10% Charity', 'Given '.$health['charity']['used'], 'Expected '.$health['charity']['expected'], $health['charity']['fulfillment_percentage'].'%'],
                ['Available cash', $health['savings']['balance'], $health['savings']['ratio'].'%', ''],
            ] as $card)
                <div class="col-md-4">
                    <div class="wm-card">
                        <strong>{{ $card[0] }}</strong>
                        <div>{{ $card[1] }}</div>
                        <div class="small text-muted">{{ $card[2] }} {{ $card[3] }}</div>
                    </div>
                </div>
            @endforeach
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
        <div class="wm-card"><canvas id="wm-health-hist" height="120"></canvas></div>
    </div>
</section>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var hist = @json($history);
    new Chart(document.getElementById('wm-health-hist'), {
        type: 'line',
        data: {
            labels: hist.map(function (h) { return h.label; }),
            datasets: [{ label: 'Financial Health', borderColor: '#0b3f90', data: hist.map(function (h) { return h.score; }) }]
        }
    });
})();
</script>
@endsection
