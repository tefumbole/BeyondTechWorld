@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Reconciliation</h3>
    @include('gbhs.admin.nav')
    <div class="row">
        @foreach(['reported' => 'Reported total', 'monetary' => 'Monetary lines', 'verified' => 'Verified', 'unverified' => 'Unverified', 'cancelled' => 'Cancelled', 'in_kind' => 'In-kind stated value', 'difference' => 'Difference'] as $key => $label)
            <div class="col-md-3"><div class="card p-3 mb-2"><div class="text-muted">{{ $label }}</div><strong>{{ number_format($summary[$key]) }}</strong></div></div>
        @endforeach
    </div>
    <p>The reported total is the figure the association published. The difference is that figure minus the monetary lines. Reversals and in-kind values are not inside the monetary sum. In-kind quantity on file: {{ $summary['in_kind_qty'] }}.</p>
    <div class="d-print-none"><button class="btn btn-sm btn-outline-secondary" onclick="window.print()">Print</button></div>
</div></section>
@endsection
