@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Allocations</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <p class="text-muted">Basis: {{ strtoupper($snapshot['basis']) }} · Qualifying income {{ number_format($snapshot['income'], 2) }}. These are ledger targets, not split journal entries.</p>
        <div class="row">
            @foreach($snapshot['rows'] as $row)
                <div class="col-md-4">
                    <div class="wm-card">
                        @include('wealth.partials.badge', ['bucket' => $row['bucket']])
                        <div class="small text-muted">{{ $row['percent'] }}%</div>
                        <p class="mb-1">Expected {{ number_format($row['expected'], 2) }}</p>
                        <p class="mb-1">Used {{ number_format($row['used'], 2) }}</p>
                        <p class="mb-0">Remaining {{ number_format($row['remaining'], 2) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
