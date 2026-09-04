<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pa Ngwayu Francis · In loving memory')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-2: #f0d57a;
            --ink: #f7f1e4;
            --muted: #c4b498;
            --bg: #0d0a08;
            --paper: #14100c;
            --card: #1c160f;
            --line: #3d3118;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif;
            color: var(--ink);
            background: var(--bg);
        }
        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(260px, 40%) 1fr;
        }
        .portrait {
            position: sticky;
            top: 0;
            height: 100vh;
            background: #050403;
            overflow: hidden;
        }
        .portrait img {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: contain;
            object-position: center;
            padding: 18px 18px 78px;
            opacity: 0; transition: opacity 1.4s ease;
        }
        .portrait img.on { opacity: 1; }
        .portrait .wash {
            position: absolute; inset: 0;
            pointer-events: none;
            background: linear-gradient(180deg, transparent 72%, rgba(5,4,3,.72) 100%);
        }
        .portrait .caption {
            position: absolute; left: 18px; right: 18px; bottom: 20px;
            color: #f7f1e4;
            text-shadow: 0 2px 12px rgba(0,0,0,.55);
        }
        .portrait .caption strong {
            display: block;
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
        }
        .main {
            background: var(--paper);
            padding: 28px 32px 40px;
            overflow: auto;
        }
        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0 0 28px;
        }
        .nav a, .nav button {
            border: 1px solid #5a4a22;
            background: transparent;
            color: var(--gold-2);
            border-radius: 999px;
            padding: 14px 22px;
            font-weight: 700;
            font-size: 17px;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
        }
        .nav a.on, .nav a:hover, .nav button:hover {
            background: var(--gold);
            color: #1a1408;
            border-color: var(--gold);
        }
        .kicker { letter-spacing: .24em; text-transform: uppercase; color: var(--gold); font-size: 12px; margin: 0 0 8px; font-weight: 700; }
        h1 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(34px, 5vw, 52px);
            line-height: 1.05; margin: 0 0 8px; font-weight: 700; color: #fff8e8;
        }
        h2 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(26px, 3.6vw, 36px);
            margin: 0 0 10px;
            color: #fff8e8;
        }
        .meta { color: var(--muted); margin: 0 0 20px; font-size: 17px; line-height: 1.45; }
        .lead { color: #e8dcc0; font-size: 18px; line-height: 1.65; max-width: 38em; }
        .rings { display: grid; grid-template-columns: repeat(4, 86px); gap: 12px; margin-bottom: 22px; }
        .ring {
            width: 86px; height: 86px; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: radial-gradient(circle at center, #1c160f 56%, transparent 57%),
                        conic-gradient(#d4af37 var(--p, 0%), #3a2f18 0);
            border: 1px solid #6a5420;
        }
        .ring b { font-family: "Cormorant Garamond", serif; font-size: 28px; color: #fff8e8; line-height: 1; }
        .ring span { font-size: 10px; letter-spacing: .12em; color: var(--muted); text-transform: uppercase; font-weight: 700; }
        .landing-home {
            min-height: calc(100vh - 220px);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding-bottom: 8px;
        }
        .landing-home h1 {
            font-size: clamp(40px, 6.4vw, 68px);
            margin: 0 0 28px;
            max-width: 14em;
        }
        .landing-home .rings {
            grid-template-columns: repeat(4, minmax(72px, 1fr));
            gap: 14px;
            margin: 0 0 28px;
            max-width: 560px;
        }
        .landing-home .ring {
            width: 100%;
            height: auto;
            aspect-ratio: 1;
        }
        .landing-home .ring b { font-size: clamp(28px, 5vw, 48px); }
        .landing-home .ring span { font-size: 12px; letter-spacing: .14em; }
        .sun-row {
            display: flex;
            gap: 28px;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 18px;
        }
        .sun-item span {
            display: block;
            color: var(--gold);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .sun-item strong {
            display: block;
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(32px, 5vw, 44px);
            font-weight: 700;
            color: #fff8e8;
            line-height: 1;
        }
        .eulogies.is-hidden { display: none; }
        .flash { background: #1d3a22; border: 1px solid #4a9a5c; color: #d8f5de; padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; }
        .flash.bad { background: #3a1612; border-color: #a85a4c; color: #f3cfc8; }
        .eulogies { margin-top: 28px; padding-top: 8px; }
        .eulogies-count {
            display: inline-block; margin: 0 0 16px; color: var(--gold);
            font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        }
        .eulogy-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px 20px 16px;
            margin-bottom: 14px;
        }
        .eulogy-box p {
            margin: 0 0 14px;
            color: #f3ead6;
            font-size: 18px;
            line-height: 1.7;
            white-space: pre-wrap;
        }
        .eulogy-who {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid #3a2f18;
            padding-top: 12px;
        }
        .eulogy-who cite {
            font-family: "Cormorant Garamond", serif;
            font-style: normal;
            font-size: 22px;
            font-weight: 600;
            color: #fff8e8;
        }
        .eulogy-box .sig { height: 48px; max-width: 160px; object-fit: contain; background: #fffdf8; border-radius: 6px; padding: 2px 6px; }
        .selfie {
            height: 52px; width: 52px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--gold);
        }
        .eulogies-empty {
            background: var(--card);
            border: 1px dashed #6a5420;
            border-radius: 16px;
            padding: 22px 18px;
            color: var(--muted);
            font-size: 17px;
            line-height: 1.55;
        }
        .order, .hymn {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px 18px 14px;
            margin-bottom: 12px;
        }
        .order ol { margin: 8px 0 0; padding-left: 22px; color: #e8dcc0; line-height: 1.7; }
        .order li { margin: 6px 0; }
        .hymn h3 {
            font-family: "Cormorant Garamond", serif;
            margin: 0 0 8px;
            color: var(--gold-2);
            font-size: 24px;
        }
        .hymn p { margin: 0; white-space: pre-wrap; color: #e8dcc0; line-height: 1.7; }
        .verse { font-size: 13px; color: var(--gold); letter-spacing: .08em; text-transform: uppercase; font-weight: 700; margin: 0 0 6px; }
        .sig-pad { width: 100%; height: 150px; border: 1px dashed #6a5420; border-radius: 10px; background: #fffdf8; touch-action: none; }
        .foot { margin-top: 28px; text-align: center; color: #8a7b62; font-size: 13px; }
        .modal {
            display: none; position: fixed; inset: 0; z-index: 20;
            background: rgba(8, 6, 10, .82); padding: 18px 12px; overflow: auto;
        }
        .modal.on { display: block; }
        .sheet {
            max-width: 440px; margin: 24px auto; background: #1c160f; border: 1px solid var(--line);
            border-radius: 18px; padding: 18px 16px 16px; color: var(--ink);
        }
        #eulogyModal .sheet { max-width: 560px; }
        .sheet h2 { font-family: "Cormorant Garamond", serif; margin: 0 0 12px; color: #fff8e8; }
        label { display: block; font-size: 12px; color: var(--muted); margin: 10px 0 4px; font-weight: 700; }
        input, select, textarea {
            width: 100%; border: 1px solid #6a5420; background: #0d0a08; color: #fff8e8;
            border-radius: 10px; padding: 11px 12px; font-size: 16px; font-family: inherit;
        }
        textarea { min-height: 180px; resize: vertical; line-height: 1.6; }
        .phone-row { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .actions3 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .btn {
            border: 0; border-radius: 12px; padding: 12px 10px; font-weight: 700; cursor: pointer; font-size: 15px;
        }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #d4af37); color: #1a1408; }
        .btn-ghost { background: transparent; color: var(--gold-2); border: 1px solid var(--line); }
        .err { color: #e7a399; font-size: 13px; min-height: 18px; margin-top: 8px; }
        .hint { color: #8a7b62; font-size: 12px; margin: 4px 0 0; }
        .close { float: right; background: none; border: 0; color: var(--muted); font-size: 22px; cursor: pointer; }
        .selfie-preview { display: none; margin-top: 8px; width: 88px; height: 88px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); }
        @media (max-width: 860px) {
            .shell { grid-template-columns: 1fr; }
            .portrait { position: relative; min-height: 320px; height: 78vw; max-height: 520px; }
            .portrait img { padding: 10px 10px 12px; }
            .portrait .caption { display: none; }
            .main { padding: 16px 14px 28px; }
            .rings { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
            .ring { width: 100%; height: auto; aspect-ratio: 1; }
            .ring b { font-size: 24px; }
            .nav a, .nav button { padding: 12px 16px; font-size: 15px; }
            .landing-home { min-height: auto; }
            .landing-home .rings { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
            .landing-home .ring b { font-size: 22px; }
            .landing-home .ring span { font-size: 9px; }
            .sun-row { gap: 18px; }
        }
        @media (max-width: 420px) {
            .phone-row { grid-template-columns: 1fr; }
            .actions3 { grid-template-columns: 1fr; }
        }
        body.is-landing { background: #000; }
        body.is-landing .shell {
            display: block;
            min-height: 100vh;
            position: relative;
        }
        body.is-landing .portrait {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: #000;
        }
        body.is-landing .portrait img {
            padding: 0;
            object-fit: contain;
            object-position: center;
        }
        body.is-landing .portrait .wash,
        body.is-landing .portrait .caption { display: none; }
        body.is-landing .main {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            background: transparent;
            display: flex;
            flex-direction: column;
        }
        body.is-landing .landing-home h1 { display: none; }
        body.is-landing .landing-home {
            flex: 1;
            min-height: 0;
            justify-content: flex-end;
        }
        body.is-landing .eulogies:not(.is-hidden) {
            background: rgba(8,6,4,.9);
            border: 1px solid #4a3b1c;
            border-radius: 18px;
            padding: 20px 18px;
        }
        .music-btn {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 40;
            border: 1px solid #d4af37;
            background: rgba(13,10,8,.82);
            color: #f0d57a;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }
        @yield('styles')
    </style>
</head>
<body class="{{ ($navActive ?? '') === 'remember' ? 'is-landing' : '' }}">
<div class="shell">
<aside class="portrait" id="bg">
    @foreach($photos as $i => $src)
        <img src="{{ $src }}" alt="Pa Ngwayu Francis" class="{{ $i === 0 ? 'on' : '' }}">
    @endforeach
    <div class="wash"></div>
    <div class="caption">
        <strong>Pa Ngwayu Francis</strong>
        <span>1952 — 2025 · 73 years</span>
    </div>
</aside>
<main class="main">
    <nav class="nav" aria-label="Memorial pages">
        <a href="{{ route('funeral.pangwayu.remember') }}" class="{{ ($navActive ?? '') === 'remember' ? 'on' : '' }}">Home</a>
        <a href="{{ route('funeral.pangwayu.program') }}" class="{{ ($navActive ?? '') === 'program' ? 'on' : '' }}">Funeral program</a>
        <a href="{{ route('funeral.pangwayu.hymns') }}" class="{{ ($navActive ?? '') === 'hymns' ? 'on' : '' }}">Hymns</a>
        <a href="{{ route('funeral.pangwayu.remember') }}#eulogies">Eulogies</a>
        @if(($navActive ?? '') !== 'remember')
            <a href="{{ route('funeral.pangwayu.remember') }}?donate=1">Donate</a>
        @endif
        @yield('nav_extra')
    </nav>
    @yield('content')
    <p class="foot">Developed By: Sr. Engr. Tefu R. Mbole |
        <a href="https://wa.me/237675321739" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">+237 675-321-739</a>
    </p>
</main>
</div>
@yield('modals')
<script>
(function () {
    if (document.body.classList.contains('is-landing')) return;
    var imgs = document.querySelectorAll('#bg img');
    var idx = 0;
    function nextPhoto() {
        if (!imgs.length) return;
        imgs[idx].classList.remove('on');
        idx = (idx + 1) % imgs.length;
        imgs[idx].classList.add('on');
    }
    setTimeout(function () {
        nextPhoto();
        setInterval(nextPhoto, 15000);
    }, 15000);
})();
</script>
@yield('scripts')
</body>
</html>
