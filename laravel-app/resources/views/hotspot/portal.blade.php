@extends('beyond.layout')
@section('title', 'Wi-Fi')
@section('content')
<style>
    .hs-wrap { background: linear-gradient(180deg, #e8eef8 0%, #f6f8fb 42%, #fff 100%); padding: 1.5rem 1rem 4rem; }
    .hs { max-width: 28rem; margin: 0 auto; }
    .hs-kicker { margin: 0; color: #0b3f90; font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
    .hs h1 { margin: .25rem 0 .35rem; font-size: 2rem; line-height: 1.1; color: #10233f; }
    .hs-lede { margin: 0 0 1.1rem; color: #526074; }
    .hs-card { background: #fff; border: 1px solid rgba(16,35,63,.08); border-radius: 22px; box-shadow: 0 16px 40px rgba(16,35,63,.08); padding: 1.15rem; margin-bottom: 1rem; }
    .hs-top { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
    .hs-top h2 { margin: 0; font-size: 1.25rem; color: #10233f; }
    .hs-top p { margin: .15rem 0 0; color: #526074; }
    .hs-price { text-align: right; font-size: 1.7rem; font-weight: 800; color: #0b3f90; line-height: 1; }
    .hs-price small { display: block; margin-top: .2rem; font-size: .75rem; letter-spacing: .08em; color: #526074; }
    .hs-facts { display: flex; flex-wrap: wrap; gap: .45rem; margin: .9rem 0 1rem; padding: 0; list-style: none; }
    .hs-facts li { background: #f3f6fb; color: #24364d; border-radius: 999px; padding: .35rem .7rem; font-size: .85rem; }
    .hs label { display: block; margin: .15rem 0 .3rem; font-size: .82rem; font-weight: 650; color: #24364d; }
    .hs input, .hs select { width: 100%; box-sizing: border-box; min-height: 48px; margin-bottom: .75rem; padding: .7rem .85rem; border-radius: 12px; border: 1px solid #d5deea; background: #fff; font-size: 16px; color: #10233f; }
    .hs input:focus, .hs select:focus { outline: 2px solid rgba(11,63,144,.25); border-color: #0b3f90; }
    .hs button { width: 100%; border: 0; border-radius: 999px; min-height: 48px; background: #0b3f90; color: #fff; font-weight: 700; font-size: 1rem; }
    .hs-note { margin: .7rem 0 0; text-align: center; color: #6b7788; font-size: .85rem; }
    .hs-split { display: flex; align-items: center; gap: .7rem; color: #8a96a6; font-size: .8rem; margin: .2rem 0 .9rem; }
    .hs-split:before, .hs-split:after { content: ""; flex: 1; height: 1px; background: #e1e7ef; }
    .hs-err { background: #fff1f1; color: #9b1c1c; padding: .75rem .9rem; border-radius: 12px; margin-bottom: .8rem; }
    .hs-support { display: block; text-align: center; color: #0b3f90; font-weight: 700; text-decoration: none; }
</style>
<div class="hs-wrap">
    <div class="hs">
        <p class="hs-kicker">Wi-Fi</p>
        @if(!$location)
            <h1>Not on sale yet</h1>
            <p class="hs-lede">Packages will appear here after a location is added.</p>
        @else
            <h1>{{ $location->name }}</h1>
            <p class="hs-lede">Choose a package, then pay with MoMo or Visa.</p>
            @if(session('hotspot_error'))<p class="hs-err">{{ session('hotspot_error') }}</p>@endif
            @if(!empty($errorText))<p class="hs-err">{{ $errorText }}</p>@endif
            @forelse($packages as $package)
                <form class="hs-card" method="POST" action="{{ route('hotspot.pay') }}">
                    @csrf
                    <input type="hidden" name="package_id" value="{{ $package->id }}">
                    <div class="hs-top">
                        <div>
                            <h2>{{ $package->name }}</h2>
                            <p>{{ \App\Hotspot\HotspotLimits::humanDuration($package->validity_seconds) }} of access</p>
                        </div>
                        <div class="hs-price">{{ number_format($package->price) }} <small>XAF</small></div>
                    </div>
                    <ul class="hs-facts">
                        <li>{{ \App\Hotspot\HotspotLimits::humanDuration($package->validity_seconds) }}</li>
                        <li>{{ $package->rateLabel() }} up / down</li>
                        @if($package->data_bytes)<li>{{ \App\Hotspot\HotspotLimits::humanBytes($package->data_bytes) }} included</li>@endif
                    </ul>
                    <label for="hs-name-{{ $package->id }}">Name</label>
                    <input id="hs-name-{{ $package->id }}" name="name" placeholder="Your name" autocomplete="name" required>
                    <label for="hs-phone-{{ $package->id }}">Phone / WhatsApp</label>
                    <input id="hs-phone-{{ $package->id }}" name="phone" placeholder="6XX XXX XXX" inputmode="tel" autocomplete="tel" required>
                    <label for="hs-method-{{ $package->id }}">Payment</label>
                    <select id="hs-method-{{ $package->id }}" name="method">
                        <option value="momo">MTN or Orange MoMo</option>
                        <option value="visa">Visa</option>
                    </select>
                    <button type="submit">Pay {{ number_format($package->price) }} XAF</button>
                    <p class="hs-note">Access starts after the payment is confirmed.</p>
                </form>
            @empty
                <p class="hs-lede">No packages are on sale at this location.</p>
            @endforelse
            <div class="hs-split">or use a code</div>
            <form class="hs-card" method="POST" action="{{ route('hotspot.voucher') }}">
                @csrf
                <h2 style="margin:0 0 .8rem;font-size:1.1rem;color:#10233f;">Have a code?</h2>
                <label for="hs-code">Voucher code</label>
                <input id="hs-code" name="code" placeholder="10-character code" autocomplete="off" required>
                <label for="hs-vname">Name</label>
                <input id="hs-vname" name="name" placeholder="Your name" autocomplete="name" required>
                <label for="hs-vphone">Phone / WhatsApp</label>
                <input id="hs-vphone" name="phone" placeholder="6XX XXX XXX" inputmode="tel" autocomplete="tel" required>
                <button type="submit">Redeem code</button>
            </form>
            <a class="hs-support" href="https://wa.me/{{ $support }}">WhatsApp support</a>
        @endif
    </div>
</div>
@endsection
