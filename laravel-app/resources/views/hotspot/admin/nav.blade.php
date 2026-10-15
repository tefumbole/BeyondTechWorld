<div class="mb-3">
    <a href="{{ route('hotspot.dashboard') }}">Dashboard</a> ·
    <a href="{{ route('hotspot.locations') }}">Locations</a> ·
    <a href="{{ route('hotspot.packages') }}">Packages</a> ·
    <a href="{{ route('hotspot.routers') }}">Routers</a> ·
    <a href="{{ route('hotspot.sessions') }}">Sessions</a> ·
    <a href="{{ route('hotspot.subscriptions') }}">Access</a> ·
    <a href="{{ route('hotspot.vouchers') }}">Vouchers</a> ·
    <a href="{{ route('hotspot.reports') }}">Reports</a> ·
    <a href="{{ route('hotspot.templates') }}">Messages</a> ·
    <a href="{{ route('hotspot.guide') }}">Setup guide</a>
</div>
@if(session('hotspot_status'))
    <div class="alert alert-success">{{ session('hotspot_status') }}</div>
@endif
@if(session('hotspot_error'))
    <div class="alert alert-danger">{{ session('hotspot_error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
