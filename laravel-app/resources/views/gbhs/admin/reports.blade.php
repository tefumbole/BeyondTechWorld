@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Reports & Analytics</h3>
    @include('gbhs.admin.nav')
    <p>Verified {{ number_format($summary['verified']) }} XAF · unverified {{ number_format($summary['unverified']) }} · cancelled {{ number_format($summary['cancelled']) }} · difference {{ number_format($summary['difference']) }}</p>
    <p><a href="{{ route('gbhs.export') }}">Download CSV</a> · <a href="{{ route('gbhs.reconcile') }}">Printable reconciliation</a></p>
    @foreach($byBatch as $row)
        <div>{{ $batches[$row->batch_id] ?? 'Unassigned' }} · {{ number_format($row->total) }} XAF</div>
    @endforeach
</div></section>
@endsection
