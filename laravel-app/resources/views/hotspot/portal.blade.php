<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wi-Fi | Beyond Tech World</title>
    <link rel="icon" href="{{ \App\Support\SiteBrand::logoUrl() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #17324d; background: #f4f8ff; }
        a { color: inherit; text-decoration: none; }
        .hero { position: relative; background: #071426; }
        .banner { display: block; width: 100%; height: auto; }
        .hero-bar { position: absolute; top: 0; left: 0; right: 0; z-index: 2; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 2.4rem; }
        .brand img { height: 84px; width: auto; display: block; filter: drop-shadow(0 6px 14px rgba(0,0,0,.35)); }
        .help { display: flex; align-items: center; gap: .45rem; color: #fff; font-weight: 700; font-size: .92rem; background: rgba(8, 22, 48, .35); border-radius: 999px; padding: .45rem .8rem; }
        .help small { display: block; color: rgba(255,255,255,.75); font-weight: 600; font-size: .72rem; }
        .sheet { position: relative; margin: -4.5rem 3vw 1.2rem; background: #fff; border-radius: 28px; box-shadow: 0 24px 60px rgba(18, 52, 110, .12); padding: 1.35rem 1.25rem 1.15rem; }
        .phone-row { display: flex; gap: .7rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
        .field { flex: 1; min-width: 220px; display: flex; align-items: center; gap: .5rem; border: 1px solid #e1e7ee; border-radius: 12px; padding: 0 .8rem; min-height: 46px; background: #f8fbff; }
        .field input { border: 0; width: 100%; font: inherit; font-size: 16px; outline: none; background: transparent; }
        .links { display: flex; gap: .45rem; }
        .links button { border: 0; background: #eef4ff; color: #2458d6; border-radius: 10px; min-height: 40px; padding: 0 .75rem; font-weight: 700; cursor: pointer; }
        .extra { margin: 0 0 1rem; }
        .section-head { display: flex; justify-content: space-between; gap: 1rem; align-items: center; flex-wrap: wrap; }
        h3 { margin: 0; font-size: 1.55rem; }
        .sub { margin: .2rem 0 0; color: #7b8794; font-size: .95rem; }
        .filters { display: flex; gap: .45rem; flex-wrap: wrap; }
        .filters button { border: 1px solid #e6edf5; background: #fff; border-radius: 999px; min-height: 40px; padding: 0 .9rem; font-weight: 700; color: #526274; cursor: pointer; }
        .filters button.on { background: #2f6bff; color: #fff; border-color: #2f6bff; }
        .err { background: #fff1f1; color: #9b1c1c; border-radius: 12px; padding: .7rem .8rem; margin-bottom: .8rem; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .9rem; margin-top: 1.1rem; }
        .card { position: relative; border-radius: 22px; padding: 1.15rem .95rem 1rem; border: 1px solid transparent; }
        .card.mint { background: linear-gradient(180deg, #f3fbf6, #eefaf3); }
        .card.sky { background: linear-gradient(180deg, #f4f8ff, #eef4ff); }
        .card.lilac { background: linear-gradient(180deg, #f7f4ff, #f3efff); }
        .card.sand { background: linear-gradient(180deg, #fff8ee, #fff4e4); }
        .badge { position: absolute; top: .75rem; left: .75rem; background: #22c55e; color: #fff; border-radius: 999px; font-size: .68rem; font-weight: 800; letter-spacing: .03em; padding: .28rem .55rem; }
        .icon { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: .35rem auto .35rem; background: #fff; }
        .card.mint .icon { color: #16a34a; }
        .card.sky .icon { color: #2563eb; }
        .card.lilac .icon { color: #7c3aed; }
        .card.sand .icon { color: #f59e0b; }
        .card h4 { text-align: center; margin: .15rem 0; font-size: 1.35rem; }
        .blurb { text-align: center; color: #7d8b99; margin: 0 0 .75rem; font-size: .92rem; }
        .card ul { list-style: none; padding: 0; margin: 0 0 .85rem; }
        .card li { margin: .38rem 0; font-size: .92rem; }
        .card li:before { content: "✓  "; color: #22a35a; font-weight: 800; }
        .amount { text-align: center; font-size: 1.7rem; font-weight: 800; margin: .15rem 0 .75rem; }
        .pay, .cardbtn, .go { width: 100%; border-radius: 12px; min-height: 46px; font-weight: 700; cursor: pointer; font-size: .95rem; }
        .pay { border: 0; color: #fff; margin-bottom: .45rem; }
        .card.mint .pay { background: #22c55e; }
        .card.sky .pay { background: #2f6bff; }
        .card.lilac .pay { background: #7c3aed; }
        .card.sand .pay { background: #f5a524; }
        .cardbtn, .go { border: 1px solid #d7e0ea; background: #fff; color: #334155; }
        .go { background: #2f6bff; color: #fff; border: 0; margin-top: .45rem; }
        .why { display: grid; grid-template-columns: repeat(4, 1fr); gap: .8rem; margin: 0 3vw 1.2rem; background: #fff; border-radius: 22px; padding: 1rem 1.1rem; box-shadow: 0 10px 30px rgba(18, 52, 110, .05); }
        .why > div { display: flex; gap: .7rem; align-items: center; }
        .bubble { width: 42px; height: 42px; border-radius: 50%; background: #eef4ff; color: #2f6bff; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .why strong { display: block; font-size: .95rem; }
        .why span { color: #7b8794; font-size: .82rem; }
        .foot { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: .2rem 4vw 1.4rem; color: #8b98a6; font-size: .85rem; }
        .hidden { display: none; }
        @media (max-width: 1100px) { .grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 720px) {
            .grid, .why { grid-template-columns: 1fr; }
            .sheet { margin: -2rem 0 1rem; border-radius: 22px 22px 0 0; }
            .hero-bar { padding: .7rem 1rem; }
            .brand img { height: 64px; }
        }
    </style>
</head>
<body>
    @php
        $logo = \App\Support\SiteBrand::logoUrl();
        $hero = url('public/branding/hotspot-hero-2.jpg');
        $wa = 'https://wa.me/'.preg_replace('/\D+/', '', (string) $support);
        $blurbs = [
            '1 Hour' => 'Perfect for quick access',
            '1 Day' => 'Ideal for daily use',
            '1 Week' => 'Great for regular users',
            '1 Month' => 'Best value for frequent users',
        ];
    @endphp
    <section class="hero">
        <img class="banner" src="{{ $hero }}" alt="Stay connected with Beyond Tech World">
        <header class="hero-bar">
            <a class="brand" href="{{ route('hotspot.home') }}"><img src="{{ $logo }}" alt="Beyond Tech World"></a>
            <a class="help" href="{{ $wa }}"><span><small>Need help?</small>WhatsApp support</span></a>
        </header>
    </section>
    <section class="sheet" id="packages">
        @if(session('hotspot_error'))<div class="err">{{ session('hotspot_error') }}</div>@endif
        @if(!empty($errorText))<div class="err">{{ $errorText }}</div>@endif
        <div class="phone-row">
            <label class="field"><span>☎</span><input id="hs-phone" inputmode="tel" autocomplete="tel" placeholder="Enter your phone number"></label>
            <div class="links">
                <button type="button" id="tab-login">Login</button>
                <button type="button" id="show-voucher">Voucher</button>
            </div>
        </div>
        <form id="login-box" class="extra hidden" method="POST" action="{{ route('hotspot.resume') }}">
            @csrf
            <label class="field"><span>☎</span><input name="phone" inputmode="tel" placeholder="Phone number used to pay" required></label>
            <button class="go" type="submit">Open my access</button>
        </form>
        <form id="voucher-box" class="extra hidden" method="POST" action="{{ route('hotspot.voucher') }}">
            @csrf
            <input type="hidden" name="phone" class="phone-copy">
            <label class="field"><span>#</span><input name="code" placeholder="Voucher code" required></label>
            <button class="go" type="submit">Redeem code</button>
        </form>
        <div class="section-head">
            <div>
                <h3>Choose Your Internet Plan</h3>
                <p class="sub">Simple, affordable and secure. Pay with Mobile Money or card.</p>
            </div>
            <div class="filters">
                <button type="button" class="on" data-filter="time">Time based</button>
                <button type="button" data-filter="data">Data based</button>
                <button type="button" data-filter="unlimited">Unlimited</button>
            </div>
        </div>
        @if($packages->isEmpty())
            <p class="sub">No packages are on sale yet. Add them in the hotspot admin.</p>
        @else
            <div class="grid">
                @foreach($packages as $index => $package)
                    @php
                        $tone = ['mint', 'sky', 'lilac', 'sand'][$index % 4];
                        $kinds = 'time';
                        if ($package->data_bytes) { $kinds .= ' data'; } else { $kinds .= ' unlimited'; }
                        $openSpeed = \App\Hotspot\HotspotLimits::unlimited($package->rate_up) && \App\Hotspot\HotspotLimits::unlimited($package->rate_down);
                        $speedLine = $openSpeed ? 'High speed internet' : $package->rateLabel();
                        $accessLine = $package->data_bytes ? \App\Hotspot\HotspotLimits::humanBytes($package->data_bytes).' included' : 'Full access to all sites';
                        $blurb = isset($blurbs[$package->name]) ? $blurbs[$package->name] : \App\Hotspot\HotspotLimits::humanDuration($package->validity_seconds);
                        $devices = max(1, (int) $package->shared_users);
                    @endphp
                    <article class="card {{ $tone }}" data-kinds="{{ $kinds }}">
                        @if($index === 0 && $packages->count() > 1)<span class="badge">MOST POPULAR</span>@endif
                        <div class="icon">
                            @if($index % 4 === 3)
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 16l3-9h10l3 9"/><path d="M8 16v2h8v-2"/><path d="M12 7v3"/></svg>
                            @elseif($index % 4 === 2)
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
                            @else
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
                            @endif
                        </div>
                        <h4>{{ $package->name }}</h4>
                        <p class="blurb">{{ $blurb }}</p>
                        <ul>
                            <li>{{ $speedLine }}</li>
                            <li>Up to {{ $devices }} {{ $devices === 1 ? 'device' : 'devices' }}</li>
                            <li>{{ $accessLine }}</li>
                            <li>No hidden charges</li>
                        </ul>
                        <div class="amount">{{ number_format($package->price) }} XAF</div>
                        <form method="POST" action="{{ route('hotspot.pay') }}">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ $package->id }}">
                            <input type="hidden" name="phone" class="phone-copy">
                            <button class="pay" type="submit" name="method" value="momo">Pay with Mobile Money →</button>
                            <button class="cardbtn" type="submit" name="method" value="visa">Pay with Card →</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
    <section class="why">
        <div><i class="bubble">⚡</i><div><strong>Instant Activation</strong><span>Starts when the payment is confirmed.</span></div></div>
        <div><i class="bubble">▤</i><div><strong>Multiple Payment Options</strong><span>Mobile Money and Visa cards.</span></div></div>
        <div><i class="bubble">☎</i><div><strong>24/7 Support</strong><span>Need help? Contact us on WhatsApp.</span></div></div>
        <div><i class="bubble">✓</i><div><strong>Safe &amp; Reliable</strong><span>One payment opens one access.</span></div></div>
    </section>
    <footer class="foot"><span>© {{ date('Y') }} Beyond Tech World. All rights reserved.</span><a href="{{ $wa }}">Contact support</a></footer>
    <script>
        (function () {
            var phone = document.getElementById('hs-phone');
            var login = document.getElementById('login-box');
            var voucher = document.getElementById('voucher-box');
            function copyPhone() {
                var value = phone ? phone.value : '';
                var fields = document.querySelectorAll('.phone-copy');
                for (var i = 0; i < fields.length; i++) fields[i].value = value;
            }
            document.getElementById('tab-login').onclick = function () {
                login.className = login.className.indexOf('hidden') === -1 ? 'extra hidden' : 'extra';
                voucher.className = 'extra hidden';
            };
            document.getElementById('show-voucher').onclick = function () {
                copyPhone();
                voucher.className = voucher.className.indexOf('hidden') === -1 ? 'extra hidden' : 'extra';
                login.className = 'extra hidden';
            };
            var forms = document.querySelectorAll('form');
            for (var f = 0; f < forms.length; f++) {
                forms[f].addEventListener('submit', function (event) {
                    copyPhone();
                    var hidden = this.querySelector('.phone-copy');
                    if (hidden && hidden.value.trim() === '') {
                        event.preventDefault();
                        if (phone) phone.focus();
                    }
                });
            }
            var filters = document.querySelectorAll('.filters button');
            for (var b = 0; b < filters.length; b++) {
                filters[b].onclick = function () {
                    for (var i = 0; i < filters.length; i++) filters[i].className = '';
                    this.className = 'on';
                    var kind = this.getAttribute('data-filter');
                    var cards = document.querySelectorAll('.card');
                    for (var c = 0; c < cards.length; c++) {
                        cards[c].style.display = cards[c].getAttribute('data-kinds').indexOf(kind) === -1 ? 'none' : '';
                    }
                };
            }
        })();
    </script>
</body>
</html>
