<!doctype html>
<html lang="{{ session('travel_lang') === 'fr' ? 'fr' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Epic Travels</title>
    <style>
        *{box-sizing:border-box}
        :root{--ink:#132d29;--green:#124638;--lime:#d9f68e}
        body{margin:0;font:15px system-ui,sans-serif;color:var(--ink);background:#f5f6f0}
        header,main,footer{max-width:1120px;margin:auto;padding:22px}
        header{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-block:22px;border-bottom:1px solid #dce4d8}
        .brand{display:flex;align-items:center;gap:10px;font-size:21px;font-weight:800;border:0;background:none;padding:0;text-decoration:none;color:inherit}
        .mark{background:var(--green);color:var(--lime);border-radius:12px;padding:9px;display:grid;place-items:center}
        .mark svg,.tile svg,.back svg{width:23px;height:23px}
        .head-note{font-size:12px;color:#52675f;letter-spacing:.08em}
        .back{display:inline-flex;gap:8px;align-items:center;margin-bottom:16px;text-decoration:none;color:inherit;font-weight:700}
        .landing{isolation:isolate;position:relative;overflow:hidden;background:#113c32;color:#fff;padding:34px 40px;min-height:235px;border-radius:24px}
        .landing h1{font-size:clamp(37px,6vw,69px);max-width:570px;line-height:.95;margin:16px 0;letter-spacing:-.055em}
        .landing p{max-width:380px;color:#e0eee6}
        .landing small{letter-spacing:.1em;text-transform:uppercase;font-weight:700;color:var(--lime)}
        .lines{position:absolute;inset:0 0 0 auto;width:58%;height:100%;z-index:-1;opacity:.8}
        .section-heading{margin:27px 0 10px;display:flex;align-items:baseline;justify-content:space-between;gap:10px}
        .section-heading h2{margin:0;font-size:20px;letter-spacing:-.03em}
        .section-heading span{font-size:12px;color:#52675f}
        .tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .staff-tiles{grid-template-columns:repeat(5,minmax(0,1fr))}
        .tile{text-align:left;display:flex;flex-direction:column;align-items:start;gap:10px;position:relative;padding:17px;min-height:130px;border:1px solid #dce4d8;border-radius:16px;background:#fff;color:inherit;text-decoration:none;transition:transform .15s,box-shadow .15s}
        .tile:hover{transform:translateY(-2px);box-shadow:0 8px 22px #18372b13}
        .tile:focus-visible,.brand:focus-visible{outline:3px solid #328568;outline-offset:3px}
        .tile b{font-size:16px;line-height:1.1}
        .tile .copy{font-size:13px;color:#52675f;line-height:1.35}
        .tile .symbol{display:grid;place-items:center;width:39px;height:39px;background:#edf2e8;border-radius:11px;color:#174a3b}
        .tile.feature{background:var(--lime);border-color:transparent}
        .tile.feature .symbol{background:#173f32;color:#fff}
        .tile .arrow{position:absolute;right:15px;top:17px;width:17px;height:17px;color:#406956}
        .staff-tiles .tile{min-height:145px}
        button,input,select,textarea{font:inherit;border:1px solid #d5dfd5;border-radius:10px;padding:11px;background:#fff;color:inherit}
        button{cursor:pointer}
        button:hover{border-color:#217058}
        button:disabled{opacity:.5;cursor:default}
        .primary{background:#16533f;color:#fff;border-color:#16533f}
        .hero{background:#123d33;color:#fff;padding:clamp(24px,6vw,65px);border-radius:24px}
        .hero small{letter-spacing:.1em;text-transform:uppercase;font-weight:700;color:#d9f49b}
        h1{font-size:clamp(36px,7vw,64px);line-height:1;margin:16px 0;letter-spacing:-.055em}
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
        .combo{position:relative}
        .combo-list{position:absolute;z-index:30;left:0;right:0;top:calc(100% + 6px);background:#fff;color:var(--ink);border:1px solid #dce4d8;border-radius:14px;box-shadow:0 14px 32px #18372b24;max-height:260px;overflow:auto;padding:6px}
        .combo-group{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#52675f;font-weight:800;padding:8px 10px 4px}
        .combo-option{display:block;width:100%;text-align:left;border:0;background:transparent;border-radius:10px;padding:10px 12px;color:inherit}
        .combo-option:hover,.combo-option:focus{background:#edf6df}
        .combo-empty{padding:10px 12px;color:#52675f}
        .search-card{display:grid;grid-template-columns:1.2fr 1.2fr .9fr auto;gap:12px;align-items:end}
        .trip{display:flex;justify-content:space-between;gap:16px;align-items:center;background:#fff;border:1px solid #e0e7de;border-radius:18px;padding:18px 20px;margin-top:12px}
        .trip h3{margin:6px 0 4px;font-size:28px;letter-spacing:-.04em}
        .trip .route{font-weight:750}
        .trip .side{text-align:right;min-width:140px}
        .trip .side a{display:inline-block;margin-top:8px}
        .seats{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;max-width:450px;margin:16px 0}
        .seats button{padding:8px 3px}
        .seats .active{background:#16533f;color:#fff}
        canvas{display:block;width:100%;max-width:420px;height:120px;border:1px dashed #758e7e;touch-action:none;margin:12px 0;background:#fff}
        footer{font-size:13px;color:#52675f}
        .notice{background:#fff2d4;padding:10px;border-radius:8px}
        .error{background:#fde8e4;padding:10px;border-radius:8px}
        p{line-height:1.55}
        .langs a{color:#16533f;margin-left:8px}
        @media(max-width:760px){.staff-tiles{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media(max-width:510px){
            header,main,footer{padding-inline:15px}
            .landing{padding:26px 21px}
            .tiles,.staff-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
            .tile.feature{grid-column:span 2}
            .search-card{grid-template-columns:1fr}
            .trip{flex-direction:column;align-items:flex-start}
            .trip .side{text-align:left}
            .lines{opacity:.45}
            .section-heading span{display:none}
        }
        @media(prefers-reduced-motion:reduce){.tile{transition:none}}
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ route('travel.home') }}">
        <span class="mark">@include('travel.icon', ['icon' => 'bus'])</span>
        Epic Travels
    </a>
    <span class="head-note">CAMEROON
        <a class="langs" href="{{ route('travel.lang', 'en') }}">EN</a>
        <a class="langs" href="{{ route('travel.lang', 'fr') }}">FR</a>
    </span>
</header>
<main>
    @if(!request()->routeIs('travel.home'))
        <a class="back" href="{{ route('travel.home') }}">@include('travel.icon', ['icon' => 'back']) All services</a>
    @endif
    @if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
    @if(session('error'))<p class="error">{{ session('error') }}</p>@endif
    @yield('content')
</main>
<footer>EPIC TRAVELS · CAMEROON</footer>
</body>
</html>
