<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wi-Fi | Beyond Tech World</title>
    <link rel="icon" href="{{ \App\Support\SiteBrand::logoUrl() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #17324d; background: #fff; }
        a { color: inherit; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 6vw; background: #fff; }
        .brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; font-weight: 800; letter-spacing: .04em; }
        .brand img { height: 42px; width: auto; }
        .help { display: flex; align-items: center; gap: .45rem; color: #178a4a; font-weight: 700; text-decoration: none; font-size: .92rem; }
        .hero { position: relative; margin: 0 4vw; border-radius: 18px; overflow: hidden; min-height: 430px; color: #fff; background: radial-gradient(900px 380px at 15% 20%, rgba(47,123,255,.45), transparent 55%), linear-gradient(115deg, #09182c 0%, #14375f 48%, #0c243f 100%); }
        .hero:before { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(8,22,42,.82) 0%, rgba(10,28,52,.55) 48%, rgba(8,22,42,.35) 100%); }
        .hero-grid { position: relative; display: grid; grid-template-columns: 1.2fr .8fr; gap: 2rem; align-items: center; padding: 3.2rem 3rem; }
        .kicker { margin: 0 0 .4rem; font-size: 1.35rem; font-weight: 600; }
        h1 { margin: 0; font-size: clamp(2.1rem, 4vw, 3.4rem); line-height: 1.05; font-weight: 800; }
        .tag { margin: .8rem 0 1.2rem; font-size: 1.25rem; font-weight: 700; }
        .pills { display: flex; flex-wrap: wrap; gap: .8rem 1.1rem; }
        .pills span { display: flex; align-items: center; gap: .45rem; font-weight: 600; font-size: .95rem; }
        .dot { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(47,123,255,.95); font-size: .85rem; }
        .panel { background: #fff; color: #17324d; border-radius: 16px; box-shadow: 0 18px 50px rgba(0,0,0,.18); padding: 1.1rem; }
        .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: 1rem; }
        .tabs button { border: 0; background: #f3f5f8; border-radius: 10px; min-height: 44px; font-weight: 700; color: #5d6b7a; cursor: pointer; }
        .tabs button.on { background: #2f7bff; color: #fff; }
        .panel h2 { margin: .2rem 0 .25rem; font-size: 1.35rem; }
        .panel p { margin: 0 0 .8rem; color: #66788a; }
        .field { display: flex; align-items: center; gap: .5rem; border: 1px solid #e1e7ee; border-radius: 10px; padding: 0 .75rem; min-height: 46px; margin-bottom: .45rem; }
        .field input { border: 0; width: 100%; font: inherit; font-size: 16px; outline: none; background: transparent; }
        .hint { font-size: .82rem; color: #8b98a6; margin-bottom: .7rem; }
        .go, .green, .ghost { width: 100%; border-radius: 10px; min-height: 46px; font-weight: 700; cursor: pointer; font-size: .98rem; }
        .go { border: 0; background: #2f7bff; color: #fff; }
        .or { display: flex; align-items: center; gap: .6rem; color: #9aa6b2; font-size: .8rem; margin: .75rem 0; }
        .or:before, .or:after { content: ""; flex: 1; height: 1px; background: #e6ebf0; }
        .ghost { border: 1px solid #d5dee8; background: #fff; color: #2f7bff; }
        .err { background: #fff1f1; color: #9b1c1c; border-radius: 10px; padding: .7rem .8rem; margin-bottom: .7rem; }
        .section { padding: 2.2rem 6vw 1rem; }
        .section-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        h3 { margin: 0; font-size: 1.7rem; }
        .sub { margin: .25rem 0 0; color: #7b8794; }
        .filters { display: flex; gap: .5rem; flex-wrap: wrap; }
        .filters button { border: 1px solid #e1e7ee; background: #fff; border-radius: 10px; min-height: 40px; padding: 0 .8rem; font-weight: 700; color: #526274; cursor: pointer; }
        .filters button.on { background: #2f7bff; color: #fff; border-color: #2f7bff; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1.2rem; }
        .card { border-radius: 18px; padding: 1.1rem 1rem 1rem; border: 1px solid transparent; }
        .card.mint { background: #f3fbf6; }
        .card.sky { background: #f3f8ff; }
        .card.lilac { background: #f7f4ff; }
        .card.sand { background: #fff8ee; }
        .badge { display: inline-block; background: #3dce7a; color: #fff; border-radius: 999px; font-size: .68rem; font-weight: 800; padding: .15rem .45rem; margin-left: .35rem; }
        .icon { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: .2rem auto .4rem; background: #fff; font-size: 1.2rem; }
        .card h4 { text-align: center; margin: .2rem 0; font-size: 1.45rem; }
        .blurb { text-align: center; color: #7d8b99; margin: 0 0 .7rem; }
        .card ul { list-style: none; padding: 0; margin: 0 0 .8rem; }
        .card li { margin: .35rem 0; }
        .card li:before { content: "✓ "; color: #21a35a; font-weight: 800; }
        .amount { text-align: center; font-size: 1.55rem; font-weight: 800; margin: .4rem 0 .7rem; }
        .green { border: 0; background: #2fbe63; color: #fff; margin-bottom: .45rem; }
        .why { display: grid; grid-template-columns: repeat(4, 1fr); gap: .8rem; margin: 1.4rem 6vw 2rem; background: #f4f7fb; border-radius: 16px; padding: 1rem; }
        .why div { display: flex; gap: .6rem; align-items: center; }
        .why strong { display: block; }
        .why span { color: #7b8794; font-size: .85rem; }
        .foot { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: .8rem 6vw 1.4rem; color: #8b98a6; font-size: .85rem; }
        .hidden { display: none; }
        @media (max-width: 980px) {
            .hero-grid, .grid, .why { grid-template-columns: 1fr; }
            .hero { margin: 0; border-radius: 0; }
            .hero-grid { padding: 1.6rem 1rem 1.2rem; }
        }
    </style>
</head>
<body>
    @php $logo = \App\Support\SiteBrand::logoUrl(); $wa = 'https://wa.me/'.preg_replace('/\D+/', '', (string) $support); @endphp
    <header class="top">
        <a class="brand" href="{{ route('hotspot.home') }}"><img src="{{ $logo }}" alt="Beyond Tech World"></a>
        <a class="help" href="{{ $wa }}">WhatsApp support</a>
    </header>
    <section class="hero">
        <div class="hero-grid">
            <div>
                <p class="kicker">Welcome to</p>
                <h1>Beyond Tech World<br>Wi-Fi Hotspot</h1>
                <p class="tag">{{ $location ? $location->name : 'Fast. Reliable. Affordable internet access' }}</p>
                <div class="pills">
                    <span><i class="dot">⌁</i> Speed you set</span>
                    <span><i class="dot">▣</i> Secure payment</span>
                    <span><i class="dot">☰</i> Phone is the login</span>
                    <span><i class="dot">◈</i> Your prices</span>
                </div>
            </div>
            <div class="panel">
                @if(session('hotspot_error'))<div class="err">{{ session('hotspot_error') }}</div>@endif
                @if(!empty($errorText))<div class="err">{{ $errorText }}</div>@endif
                <div class="tabs">
                    <button type="button" class="on" id="tab-buy">Buy internet</button>
                    <button type="button" id="tab-login">Login</button>
                </div>
                <div id="buy-box">
                    <h2>Get connected</h2>
                    <p>Choose a package and pay to use the internet.</p>
                    <label class="field"><span>☎</span><input id="hs-phone" inputmode="tel" autocomplete="tel" placeholder="Enter your phone number"></label>
                    <div class="hint">We’ll use this number for the receipt on WhatsApp.</div>
                    <button class="go" type="button" id="view-packages">View packages</button>
                    <div class="or">or</div>
                    <button class="ghost" type="button" id="show-voucher">I have a voucher code</button>
                    <form id="voucher-box" class="hidden" method="POST" action="{{ route('hotspot.voucher') }}" style="margin-top:.8rem">
                        @csrf
                        <input type="hidden" name="phone" class="phone-copy">
                        <label class="field"><span>#</span><input name="code" placeholder="Voucher code" required></label>
                        <button class="go" type="submit">Redeem code</button>
                    </form>
                </div>
                <form id="login-box" class="hidden" method="POST" action="{{ route('hotspot.resume') }}">
                    @csrf
                    <h2>Already paid?</h2>
                    <p>Enter the same phone number. We’ll open your access.</p>
                    <label class="field"><span>☎</span><input name="phone" inputmode="tel" placeholder="Phone number" required></label>
                    <button class="go" type="submit">Continue</button>
                </form>
            </div>
        </div>
    </section>
    <section class="section" id="packages">
        <div class="section-head">
            <div>
                <h3>Internet packages</h3>
                <p class="sub">Choose a plan that fits your needs</p>
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
                    @endphp
                    <article class="card {{ $tone }}" data-kinds="{{ $kinds }}">
                        <div style="text-align:center">@if($index === 0 && $packages->count() > 1)<span class="badge">POPULAR</span>@endif</div>
                        <div class="icon">{{ $package->data_bytes ? '▣' : '◷' }}</div>
                        <h4>{{ $package->name }}</h4>
                        <p class="blurb">{{ \App\Hotspot\HotspotLimits::humanDuration($package->validity_seconds) }}</p>
                        <ul>
                            <li>{{ $package->rateLabel() }} up / down</li>
                            <li>Up to {{ max(1, (int) $package->shared_users) }} {{ (int) $package->shared_users === 1 ? 'device' : 'devices' }}</li>
                            <li>{{ $package->data_bytes ? \App\Hotspot\HotspotLimits::humanBytes($package->data_bytes).' included' : 'No data cap' }}</li>
                        </ul>
                        <div class="amount">{{ number_format($package->price) }} XAF</div>
                        <form method="POST" action="{{ route('hotspot.pay') }}">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ $package->id }}">
                            <input type="hidden" name="phone" class="phone-copy">
                            <button class="green" type="submit" name="method" value="momo">Pay with Mobile Money</button>
                            <button class="ghost" type="submit" name="method" value="visa">Pay with card</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
    <section class="why">
        <div><i class="dot">⚡</i><div><strong>Activation after payment</strong><span>Access starts when the payment is confirmed.</span></div></div>
        <div><i class="dot">▤</i><div><strong>MoMo and Visa</strong><span>Mobile money and card payments.</span></div></div>
        <div><i class="dot">☎</i><div><strong>WhatsApp receipt</strong><span>The username is sent to the same number.</span></div></div>
        <div><i class="dot">✓</i><div><strong>One payment, one access</strong><span>A repeated return does not buy it twice.</span></div></div>
    </section>
    <footer class="foot"><span>© {{ date('Y') }} Beyond Tech World</span><a href="{{ $wa }}">Contact support</a></footer>
    <script>
        (function () {
            var phone = document.getElementById('hs-phone');
            var buy = document.getElementById('buy-box');
            var login = document.getElementById('login-box');
            function copyPhone() {
                var value = phone ? phone.value : '';
                var fields = document.querySelectorAll('.phone-copy');
                for (var i = 0; i < fields.length; i++) fields[i].value = value;
            }
            document.getElementById('tab-buy').onclick = function () {
                this.className = 'on';
                document.getElementById('tab-login').className = '';
                buy.className = '';
                login.className = 'hidden';
            };
            document.getElementById('tab-login').onclick = function () {
                this.className = 'on';
                document.getElementById('tab-buy').className = '';
                buy.className = 'hidden';
                login.className = '';
            };
            document.getElementById('view-packages').onclick = function () {
                document.getElementById('packages').scrollIntoView({ behavior: 'smooth' });
            };
            document.getElementById('show-voucher').onclick = function () {
                copyPhone();
                document.getElementById('voucher-box').className = '';
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
