@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot reports</h3>
    @include('hotspot.admin.nav')
    <form method="GET" class="form-inline mb-3">
        <select class="form-control mr-2" name="period">
            <option value="day" @if($period==='day') selected @endif>Today</option>
            <option value="week" @if($period==='week') selected @endif>This week</option>
            <option value="month" @if($period==='month') selected @endif>This month</option>
        </select>
        <button class="btn btn-primary" type="submit">Show</button>
    </form>
    <p><strong>{{ number_format($total) }} XAF</strong> from {{ $count }} confirmed payments. Paid rows are also in Sales → Payments.</p>
    <div class="row">
        <div class="col-md-4"><h5>By package</h5>@forelse($byPackage as $name => $amount)<div>{{ $name }}: {{ number_format($amount) }} XAF</div>@empty<div>No sales in this period.</div>@endforelse</div>
        <div class="col-md-4"><h5>By location</h5>@forelse($byLocation as $name => $amount)<div>{{ $name }}: {{ number_format($amount) }} XAF</div>@empty<div>No sales in this period.</div>@endforelse</div>
        <div class="col-md-4"><h5>MoMo / Visa / voucher</h5>@forelse($byMethod as $name => $amount)<div>{{ $name }}: {{ number_format($amount) }} XAF</div>@empty<div>No sales in this period.</div>@endforelse</div>
    </div>
</div></section>
@endsection
