@extends('layout.main')
@section('content')
@php
    $companyName = $companyName ?? 'Company';
    $currency = $currency ?? 'XAF';
    $months = $months ?? [];
    $salesSeries = $salesSeries ?? [];
    $paymentSeries = $paymentSeries ?? [];
    $recentSales = $recentSales ?? [];
@endphp
<div class="beyond-dashboard">
    <div class="beyond-dashboard-hero">
        <div>
            <h1>{{ $companyName }}</h1>
            <p class="welcome-line">Your sales, payments, and catalogue. These figures are for this company only.</p>
        </div>
    </div>

    <div class="beyond-stat-grid">
        <a href="{{ route('sales.index') }}" class="beyond-stat-card">
            <div>
                <div class="label">Sales this month</div>
                <div class="value">{{ number_format((float) $monthSales, 0, '.', ',') }}</div>
                <div class="hint">{{ $currency }} · {{ number_format((int) $saleCount) }} sales in total</div>
            </div>
            <div class="beyond-stat-icon blue"><i class="dripicons-graph-bar"></i></div>
        </a>
        <a href="{{ route('customer.index') }}" class="beyond-stat-card">
            <div>
                <div class="label">Customers</div>
                <div class="value">{{ number_format((int) $customerCount) }}</div>
                <div class="hint">Includes the Walk-in customer</div>
            </div>
            <div class="beyond-stat-icon green"><i class="dripicons-user"></i></div>
        </a>
        <a href="{{ route('products.index') }}" class="beyond-stat-card">
            <div>
                <div class="label">Products</div>
                <div class="value">{{ number_format((int) $productCount) }}</div>
            </div>
            <div class="beyond-stat-icon gold"><i class="dripicons-list"></i></div>
        </a>
        <a href="{{ route('quotations.index') }}" class="beyond-stat-card">
            <div>
                <div class="label">Quotations</div>
                <div class="value">{{ number_format((int) $quotationCount) }}</div>
                <div class="hint">{{ number_format((int) $bookingCount) }} rental bookings</div>
            </div>
            <div class="beyond-stat-icon rose"><i class="dripicons-document"></i></div>
        </a>
    </div>

    <div class="beyond-chart-grid">
        <div class="beyond-chart-panel">
            <h5><i class="dripicons-graph-bar"></i> Sales and payments</h5>
            <div class="beyond-chart-canvas-wrap">
                <canvas id="company-sales-chart"
                    data-months='@json($months)'
                    data-sales='@json($salesSeries)'
                    data-payments='@json($paymentSeries)'></canvas>
            </div>
            <p class="text-muted small mb-0 mt-2">Last six months, in {{ $currency }}.</p>
        </div>
        <div class="beyond-chart-panel">
            <h5><i class="dripicons-graph-pie"></i> What you have on file</h5>
            <div class="beyond-chart-canvas-wrap">
                <canvas id="company-mix-chart"
                    data-customers="{{ (int) $customerCount }}"
                    data-products="{{ (int) $productCount }}"
                    data-quotations="{{ (int) $quotationCount }}"
                    data-bookings="{{ (int) $bookingCount }}"></canvas>
            </div>
            <div class="beyond-chart-legend">
                <span><i style="background:#0b3f90;"></i> Customers ({{ (int) $customerCount }})</span>
                <span><i style="background:#00a86b;"></i> Products ({{ (int) $productCount }})</span>
                <span><i style="background:#c6ab47;"></i> Quotations ({{ (int) $quotationCount }})</span>
                <span><i style="background:#7b61ff;"></i> Bookings ({{ (int) $bookingCount }})</span>
            </div>
        </div>
    </div>

    <div class="beyond-chart-panel" style="margin-top:18px;">
        <h5><i class="dripicons-card"></i> Recent sales</h5>
        @if(count($recentSales) === 0)
            <p class="text-muted mb-0">No sales yet. A new sale starts with the Walk-in customer.</p>
        @else
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>Reference</th><th>Date</th><th class="text-right">Total ({{ $currency }})</th></tr>
                </thead>
                <tbody>
                    @foreach($recentSales as $sale)
                        <tr>
                            <td>{{ $sale['reference'] }}</td>
                            <td>{{ $sale['when'] }}</td>
                            <td class="text-right">{{ number_format((float) $sale['total'], 0, '.', ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var bar = document.getElementById('company-sales-chart');
    if (bar) {
        new Chart(bar, {
            type: 'bar',
            data: {
                labels: JSON.parse(bar.getAttribute('data-months') || '[]'),
                datasets: [
                    { label: 'Sales', data: JSON.parse(bar.getAttribute('data-sales') || '[]'), backgroundColor: '#0b3f90', borderWidth: 0 },
                    { label: 'Payments', data: JSON.parse(bar.getAttribute('data-payments') || '[]'), backgroundColor: '#00a86b', borderWidth: 0 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    yAxes: [{ ticks: { beginAtZero: true }, gridLines: { color: '#f1f5f9' } }],
                    xAxes: [{ gridLines: { display: false }, barPercentage: 0.7 }]
                }
            }
        });
    }
    var mix = document.getElementById('company-mix-chart');
    if (mix) {
        new Chart(mix, {
            type: 'doughnut',
            data: {
                labels: ['Customers', 'Products', 'Quotations', 'Bookings'],
                datasets: [{
                    data: [
                        parseInt(mix.getAttribute('data-customers') || '0', 10),
                        parseInt(mix.getAttribute('data-products') || '0', 10),
                        parseInt(mix.getAttribute('data-quotations') || '0', 10),
                        parseInt(mix.getAttribute('data-bookings') || '0', 10)
                    ],
                    backgroundColor: ['#0b3f90', '#00a86b', '#c6ab47', '#7b61ff'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
        });
    }
})();
</script>
@endsection
