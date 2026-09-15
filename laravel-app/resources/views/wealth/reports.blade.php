@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Reports</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <div class="wm-card d-flex flex-wrap" style="gap:8px;">
            <a class="wm-btn" href="{{ route('wealth.reports.pdf', request()->query()) }}">PDF</a>
            <a class="wm-btn wm-btn-out" href="{{ route('wealth.reports.csv', array_merge(request()->query(), ['kind' => 'income'])) }}">CSV Income</a>
            <a class="wm-btn wm-btn-out" href="{{ route('wealth.reports.csv', array_merge(request()->query(), ['kind' => 'expenses'])) }}">CSV Expenses</a>
            <button class="wm-btn wm-btn-out" type="button" onclick="window.print()">Print</button>
        </div>
        <div class="wm-kpis mb-3">
            <div class="wm-kpi"><div class="lbl">Income</div><div class="val">{{ number_format($data['income'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Expenses</div><div class="val">{{ number_format($data['expenses'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Balance</div><div class="val">{{ number_format($data['balance'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Health</div><div class="val">{{ $data['health']['overall_score'] }} / 100</div></div>
        </div>
        <div class="row">
            @foreach($data['allocation']['rows'] as $row)
                <div class="col-md-4">
                    <div class="wm-card">
                        @include('wealth.partials.badge', ['bucket' => $row['bucket']])
                        <p class="mb-0">Expected {{ number_format($row['expected'], 2) }} · Used {{ number_format($row['used'], 2) }} · Left {{ number_format($row['remaining'], 2) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        @if(!empty($data['health']['warnings']))
            <div class="wm-card">
                <h5>Warnings</h5>
                <ul class="mb-0">@foreach($data['health']['warnings'] as $w)<li>{{ $w['message'] }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="wm-card">
            <h5>Income</h5>
            <table class="table wm-table">
                <thead><tr><th>Date</th><th>Source</th><th>Reference</th><th>Customer</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @foreach($data['income_rows'] as $row)
                    <tr>
                        <td>{{ $row->occurred_at }}</td>
                        <td>{{ strtoupper($row->source_type) }}</td>
                        <td>{{ $row->reference }}</td>
                        <td>{{ optional($row->customer)->name }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="wm-card">
            <h5>Expenses</h5>
            <table class="table wm-table">
                <thead><tr><th>Date</th><th>Ref</th><th>Category</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @foreach($data['expense_rows'] as $row)
                    <tr>
                        <td>{{ $row->created_at }}</td>
                        <td>{{ $row->reference_no }}</td>
                        <td>{{ optional($row->expenseCategory)->name }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
