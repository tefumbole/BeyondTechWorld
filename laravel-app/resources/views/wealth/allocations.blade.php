@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Allocations</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <p class="text-muted">{{ $filter->periodLabel() }} · {{ strtoupper($snapshot['basis']) }} income {{ number_format($snapshot['income'], 2) }}. These are monthly 70/20/10 targets from that month’s income, not split journal entries.</p>
        <div class="row">
            @foreach($snapshot['rows'] as $row)
                @php $code = optional($row['bucket'])->code; @endphp
                <div class="col-md-4">
                    <div class="wm-card">
                        @include('wealth.partials.badge', ['bucket' => $row['bucket']])
                        <div class="small text-muted">{{ $row['percent'] }}% of this month’s income</div>
                        <p class="mb-1">Expected {{ number_format($row['expected'], 2) }}</p>
                        <p class="mb-1">Used {{ number_format($row['used'], 2) }}</p>
                        @if($code === 'OPERATIONS')
                            <p class="mb-0"><strong>You can spend {{ number_format(max(0, (float) $row['remaining']), 2) }}</strong></p>
                        @elseif($code === 'INVESTMENT')
                            <p class="mb-0"><strong>Due for investment {{ number_format(max(0, (float) $row['remaining']), 2) }}</strong></p>
                        @elseif($code === 'CHARITY')
                            <p class="mb-0"><strong>Due for giving {{ number_format(max(0, (float) $row['remaining']), 2) }}</strong></p>
                        @else
                            <p class="mb-0">Remaining {{ number_format($row['remaining'], 2) }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
