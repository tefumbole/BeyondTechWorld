<!doctype html>
<html lang="{{ session('travel_lang') === 'fr' ? 'fr' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Epic Travels</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font:15px system-ui,sans-serif;color:#152d29;background:#f5f7f2}
        header,main,footer{max-width:1120px;margin:auto;padding:22px}
        header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        strong.brand{font-size:23px}
        nav{display:flex;flex-wrap:wrap;gap:6px}
        a.nav,button,input,select,textarea{font:inherit;border:1px solid #d5dfd5;border-radius:10px;padding:11px;background:#fff;color:inherit}
        a.nav{text-decoration:none}
        button{cursor:pointer}
        button:hover,a.nav:hover{border-color:#217058}
        button:disabled{opacity:.5;cursor:default}
        .active,.primary{background:#16533f;color:#fff}
        .hero{background:#123d33;color:#fff;padding:clamp(24px,6vw,65px);border-radius:24px;background-image:radial-gradient(ellipse at top right,#397957,transparent 60%)}
        .hero small,small.kicker{letter-spacing:.1em;text-transform:uppercase;font-weight:700}
        .hero small{color:#d9f49b}
        h1{font-size:clamp(36px,7vw,76px);line-height:1;margin:16px 0;letter-spacing:-.055em}
        h2,h3{margin-top:0}
        .panel,.card{background:#fff;border:1px solid #e0e7de;border-radius:18px;padding:22px;margin-top:16px}
        a.card{display:block;text-decoration:none;color:inherit}
        .form{display:flex;flex-wrap:wrap;align-items:end;gap:12px}
        label{display:grid;gap:5px;font-weight:600;flex:1;min-width:145px}
        input,select,textarea{min-width:0;width:100%}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px}
        .tag{display:inline-block;background:#e6f4ce;border-radius:20px;padding:5px 10px;font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:700}
        .muted{color:#52675f}
        .price{font-size:22px;font-weight:750}
        .seats{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;max-width:450px;margin:16px 0}
        .seats button,.seats .aisle{padding:8px 3px;text-align:center}
        .seats .aisle{border:0;background:transparent}
        canvas{display:block;width:100%;max-width:420px;height:120px;border:1px dashed #758e7e;touch-action:none;margin:12px 0;background:#fff}
        footer{font-size:13px;color:#52675f}
        .notice{background:#fff2d4;padding:10px;border-radius:8px}
        .error{background:#fde8e4;padding:10px;border-radius:8px}
        p{line-height:1.55}
        .langs a{color:#16533f}
    </style>
</head>
<body>
<header>
    <strong class="brand">◈ Epic Travels</strong>
    <nav aria-label="Main navigation">
        <a class="nav {{ request()->routeIs('travel.staff') ? 'active' : '' }}" href="{{ route('travel.staff') }}">Staff</a>
        <a class="nav {{ request()->routeIs('travel.parcels') ? 'active' : '' }}" href="{{ route('travel.parcels') }}">Parcels</a>
        <a class="nav {{ request()->routeIs('travel.counter') ? 'active' : '' }}" href="{{ route('travel.counter') }}">Counter</a>
        <a class="nav {{ request()->routeIs('travel.dispatch') || request()->routeIs('travel.manifest') ? 'active' : '' }}" href="{{ route('travel.dispatch') }}">Dispatch</a>
        <a class="nav {{ request()->routeIs('travel.reconcile') ? 'active' : '' }}" href="{{ route('travel.reconcile') }}">Reconciliation</a>
        <a class="nav {{ request()->routeIs('travel.home') || request()->routeIs('travel.departure') || request()->routeIs('travel.pay') ? 'active' : '' }}" href="{{ route('travel.home') }}">{{ \App\Travel\TravelCopy::line('book') }}</a>
        <a class="nav {{ request()->routeIs('travel.trips') ? 'active' : '' }}" href="{{ route('travel.trips') }}">{{ \App\Travel\TravelCopy::line('trips') }}</a>
        <a class="nav {{ request()->routeIs('travel.track') ? 'active' : '' }}" href="{{ route('travel.track') }}">{{ \App\Travel\TravelCopy::line('track') }}</a>
    </nav>
    <span class="langs">
        <a href="{{ route('travel.lang', 'en') }}">EN</a>
        <a href="{{ route('travel.lang', 'fr') }}">FR</a>
    </span>
</header>
<main>
    @if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
    @if(session('error'))<p class="error">{{ session('error') }}</p>@endif
    @yield('content')
</main>
<footer>EPIC TRAVELS · CAMEROON</footer>
</body>
</html>
