@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot</h3>
    @include('hotspot.admin.nav')
    @if($locations === 0)
        <div class="alert alert-info">No locations yet. Add the shop or site where this router lives. You can do that before the router is online.</div>
    @endif
    @if($packages === 0)
        <div class="alert alert-info">No packages yet. You set the price, speed, and duration. Prices are not created for you.</div>
    @endif
    @if($routers->isEmpty())
        <div class="alert alert-info">No router yet. Save the tunnel address and API user now. The router stays offline until the tunnel answers.</div>
    @endif
    <div class="row">
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Active access</div><strong>{{ $activeSubscriptions }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Online sessions</div><strong>{{ $onlineSessions }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Expired</div><strong>{{ $expiredSubscriptions }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Today / week / month</div><strong>{{ number_format($revenueToday) }} / {{ number_format($revenueWeek) }} / {{ number_format($revenueMonth) }} XAF</strong></div></div>
    </div>
    <div class="card mt-3 p-3">
        <h5>Routers</h5>
        @forelse($routers as $router)
            <div>{{ $router->name }} — {{ $router->status }} @if($router->last_seen_at) · seen {{ $router->last_seen_at }} @endif @if($router->last_error) · {{ $router->last_error }} @endif</div>
        @empty
            <p class="mb-0">No routers saved.</p>
        @endforelse
    </div>
    <div class="card mt-3 p-3">
        <h5>Recent payments</h5>
        @forelse($recent as $payment)
            <div>{{ $payment->created_at }} · {{ number_format($payment->amount) }} XAF · {{ $payment->method }} · {{ $payment->status }} @if($payment->sale_id) · sale {{ $payment->sale_id }} @endif</div>
        @empty
            <p class="mb-0">No hotspot payments yet.</p>
        @endforelse
    </div>
</div></section>
@endsection
