@extends('beyond.layout')
@section('title', 'Wi-Fi')
@section('content')
<style>
    .hs { max-width: 32rem; margin: 0 auto; padding: 1.2rem 1rem 3rem; }
    .hs h1 { font-size: 1.6rem; margin-bottom: .3rem; }
    .hs .pack { border: 1px solid rgba(0,0,0,.12); border-radius: 14px; padding: 1rem; margin: .8rem 0; }
    .hs button, .hs .btn { width: 100%; border: 0; border-radius: 999px; padding: .8rem 1rem; background: #0b3f90; color: #fff; font-weight: 700; }
    .hs input, .hs select { width: 100%; padding: .7rem .8rem; border-radius: 10px; border: 1px solid rgba(0,0,0,.18); margin-bottom: .6rem; }
    .hs .err { background: #fff4f4; color: #9b1c1c; padding: .7rem .8rem; border-radius: 10px; }
</style>
<div class="hs">
    <h1>Wi-Fi</h1>
    @if(session('hotspot_error'))<p class="err">{{ session('hotspot_error') }}</p>@endif
    @if(!empty($errorText))<p class="err">{{ $errorText }}</p>@endif
    @if(!$location)
        <p>Wi-Fi packages are not on sale yet.</p>
    @else
        <p>{{ $location->name }}</p>
        @forelse($packages as $package)
            <form class="pack" method="POST" action="{{ route('hotspot.pay') }}">
                @csrf
                <input type="hidden" name="package_id" value="{{ $package->id }}">
                <strong>{{ $package->name }}</strong>
                <div>{{ number_format($package->price) }} XAF · {{ \App\Hotspot\HotspotLimits::humanDuration($package->validity_seconds) }} · {{ $package->rateLabel() }}</div>
                @if($package->data_bytes)<div>{{ \App\Hotspot\HotspotLimits::humanBytes($package->data_bytes) }} included</div>@endif
                <input name="name" placeholder="Your name" required>
                <input name="phone" placeholder="Phone / WhatsApp" inputmode="tel" required>
                <select name="method">
                    <option value="momo">MTN or Orange MoMo</option>
                    <option value="visa">Visa</option>
                </select>
                <button type="submit">Pay</button>
            </form>
        @empty
            <p>No packages are on sale at this location.</p>
        @endforelse
        <form class="pack" method="POST" action="{{ route('hotspot.voucher') }}">
            @csrf
            <strong>Have a code?</strong>
            <input name="code" placeholder="Voucher code" required>
            <input name="name" placeholder="Your name" required>
            <input name="phone" placeholder="Phone / WhatsApp" inputmode="tel" required>
            <button type="submit">Redeem</button>
        </form>
        <p><a href="https://wa.me/{{ $support }}">WhatsApp support</a></p>
    @endif
</div>
@endsection
