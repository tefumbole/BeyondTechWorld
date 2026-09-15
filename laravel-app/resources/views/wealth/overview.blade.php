@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Wealth Manager</h1>
        <p class="text-muted">{{ $filter->periodLabel() }}</p>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        @if(($unclassified ?? 0) > 0)
            <div class="alert alert-warning">Unclassified Expenses: {{ $unclassified }}.
                <a href="{{ route('wealth.expenses', ['unclassified' => 1]) }}">Assign them to 70% Operations, 20% Investment or 10% Charity</a>
            </div>
        @endif

        <div class="wm-card wm-health">
            <div>
                <div class="lbl" style="color:#64748b;font-size:12px;font-weight:700;">FINANCIAL HEALTH · {{ strtoupper($filter->periodLabel()) }}</div>
                <div class="wm-score">{{ $health['overall_score'] }} <small style="font-size:1rem;">/ 100</small></div>
                <div class="wm-status-{{ $health['status'] }}" style="font-weight:800;">{{ $health['status_label'] }}</div>
                <div class="text-muted small">{{ $health['trend'] }} vs {{ $filter->previousPeriod()->periodLabel() }} · prev {{ $health['previous_score'] }} ({{ $health['score_change'] >= 0 ? '+' : '' }}{{ $health['score_change'] }})</div>
            </div>
            <div class="small">
                <div>You can spend {{ number_format(max(0, (float) $ops['remaining']), 2) }}</div>
                <div>Investment due {{ number_format(max(0, (float) $inv['remaining']), 2) }}</div>
                <div>Giving due {{ number_format(max(0, (float) $cha['remaining']), 2) }}</div>
            </div>
            <div>
                <a class="wm-btn wm-btn-out" href="{{ route('wealth.health', request()->query()) }}">View Full Financial Health</a>
            </div>
        </div>

        <div class="wm-kpis mb-3">
            <div class="wm-kpi">
                <div class="lbl">Income this month</div>
                <div class="val">{{ number_format($incomeTotal, 2) }}</div>
                <div class="small text-muted">All posted income in {{ $filter->periodLabel() }}</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">You can spend</div>
                <div class="val">{{ number_format(max(0, (float) $ops['remaining']), 2) }}</div>
                <div class="small text-muted">70% operations · used {{ number_format($ops['used'], 2) }} of {{ number_format($ops['expected'], 2) }}@if((float)$ops['remaining'] < 0) · over by {{ number_format(abs($ops['remaining']), 2) }}@endif</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">Due for investment</div>
                <div class="val">{{ number_format(max(0, (float) $inv['remaining']), 2) }}</div>
                <div class="small text-muted">20% of income · invested {{ number_format($inv['used'], 2) }} of {{ number_format($inv['expected'], 2) }}@if((float)$inv['remaining'] < 0) · over by {{ number_format(abs($inv['remaining']), 2) }}@endif</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">Due for giving</div>
                <div class="val">{{ number_format(max(0, (float) $cha['remaining']), 2) }}</div>
                <div class="small text-muted">10% of income · given {{ number_format($cha['used'], 2) }} of {{ number_format($cha['expected'], 2) }}@if((float)$cha['remaining'] < 0) · over by {{ number_format(abs($cha['remaining']), 2) }}@endif</div>
            </div>
            <div class="wm-kpi">
                <div class="lbl">Expenses this month</div>
                <div class="val">{{ number_format($expenseTotal, 2) }}</div>
                <div class="small text-muted">Net {{ number_format($balance, 2) }}</div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="wm-card"><canvas id="wm-ie-chart" height="160"></canvas></div>
            </div>
            <div class="col-lg-6">
                <div class="wm-card"><canvas id="wm-alloc-chart" height="160"></canvas></div>
            </div>
        </div>
        <div class="wm-card"><canvas id="wm-cash-chart" height="120"></canvas></div>

        <div class="row">
            <div class="col-lg-6">
                <div class="wm-card">
                    <h5>Programs</h5>
                    @forelse($programCards as $card)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $card['program']->name }}</strong>
                                <div class="small text-muted">Spent {{ $card['finance']['spent_pct'] }}%</div>
                            </div>
                            <div class="text-right small">
                                In {{ number_format($card['finance']['income'], 2) }}<br>
                                Out {{ number_format($card['finance']['expenses'], 2) }}
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No programs yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-6">
                <div class="wm-card">
                    <h5>Top expense categories</h5>
                    @forelse($topCategories as $cat)
                        <div class="d-flex justify-content-between py-1">
                            <span>{{ optional(\App\ExpenseCategory::find($cat->expense_category_id))->name ?: '—' }}</span>
                            <strong>{{ number_format($cat->total, 2) }}</strong>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No expenses in this period.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="wm-card">
            <h5>Recent transactions</h5>
            <table class="wm-table table">
                <thead><tr><th>When</th><th>Type</th><th>Detail</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @foreach($recentIncome as $row)
                    <tr>
                        <td>{{ $row->occurred_at }}</td>
                        <td>Income · {{ strtoupper($row->source_type) }}</td>
                        <td>{{ $row->title }} {{ optional($row->customer)->name }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @endforeach
                @foreach($recentExpenses as $row)
                    <tr>
                        <td>{{ $row->created_at }}</td>
                        <td>Expense</td>
                        <td>{{ $row->title ?: $row->reference_no }}</td>
                        <td class="text-right">-{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var months = @json(array_values(array_unique(array_merge(array_keys($monthlyIncome), array_keys($monthlyExpense)))));
    months.sort();
    var inc = @json($monthlyIncome);
    var exp = @json($monthlyExpense);
    new Chart(document.getElementById('wm-ie-chart'), {
        type: 'bar',
        data: { labels: months, datasets: [
            { label: 'Income', backgroundColor: '#0b3f90', data: months.map(function (m) { return parseFloat(inc[m] || 0); }) },
            { label: 'Expenses', backgroundColor: '#e91e8c', data: months.map(function (m) { return parseFloat(exp[m] || 0); }) }
        ]},
        options: { responsive: true, legend: { display: true } }
    });
    new Chart(document.getElementById('wm-cash-chart'), {
        type: 'line',
        data: { labels: months, datasets: [{
            label: 'Monthly cash flow',
            borderColor: '#0ea5a4',
            data: months.map(function (m) { return parseFloat(inc[m] || 0) - parseFloat(exp[m] || 0); })
        }]},
        options: { responsive: true }
    });
    var alloc = @json(collect($snapshot['rows'])->map(function ($r) { return ['label' => optional($r['bucket'])->name, 'used' => (float)$r['used']]; }));
    new Chart(document.getElementById('wm-alloc-chart'), {
        type: 'doughnut',
        data: {
            labels: alloc.map(function (a) { return a.label; }),
            datasets: [{ data: alloc.map(function (a) { return a.used; }), backgroundColor: ['#0b3f90','#c6ab47','#10b981'] }]
        }
    });
})();
</script>
@endsection
