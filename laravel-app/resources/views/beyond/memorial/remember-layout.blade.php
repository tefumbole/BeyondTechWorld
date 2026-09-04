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
            gap: 10px;
            margin: 0 0 28px;
        }
        .nav a, .nav button {
            border: 2px solid #d4af37;
            background: rgba(20, 14, 8, .92);
            color: #fff8e8;
            border-radius: 999px;
            padding: 14px 22px;
            font-weight: 800;
            font-size: 16px;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 6px 18px rgba(0,0,0,.35);
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
        .sig-pad {
            width: 100%; height: 150px; border: 1px solid rgba(212,175,55,.35);
            border-radius: 14px; background: #fffdf8; touch-action: none;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
        }
        .sig-actions { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
        .foot { margin-top: 28px; text-align: center; color: #8a7b62; font-size: 13px; }
        .modal {
            display: none; position: fixed; inset: 0; z-index: 20;
            background: rgba(4, 3, 2, .78);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 18px 12px; overflow: auto;
        }
        .modal.on { display: block; }
        .sheet {
            max-width: 440px; margin: 24px auto;
            background: linear-gradient(165deg, #221a12 0%, #14100c 55%, #100c09 100%);
            border: 1px solid rgba(212,175,55,.32);
            border-radius: 22px; padding: 22px 18px 18px; color: var(--ink);
            box-shadow: 0 28px 64px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.03) inset;
        }
        #eulogyModal .sheet { max-width: 560px; }
        .sheet h2 { font-family: "Cormorant Garamond", serif; margin: 0 0 12px; color: #fff8e8; font-size: clamp(28px, 4vw, 34px); }
        label { display: block; font-size: 12px; color: var(--muted); margin: 14px 0 6px; font-weight: 700; letter-spacing: .04em; }
        input, select, textarea {
            width: 100%; border: 1px solid rgba(212,175,55,.28); background: rgba(8,6,4,.72); color: #fff8e8;
            border-radius: 12px; padding: 12px 14px; font-size: 16px; font-family: inherit;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgba(240,213,122,.75);
            box-shadow: 0 0 0 3px rgba(212,175,55,.18);
        }
        textarea { min-height: 160px; resize: vertical; line-height: 1.6; }
        .phone-row { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .actions3 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px; }
        .btn {
            border: 0; border-radius: 12px; padding: 12px 10px; font-weight: 700; cursor: pointer; font-size: 15px;
            font-family: inherit;
        }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #d4af37); color: #1a1408; }
        .btn-ghost { background: transparent; color: var(--gold-2); border: 1px solid rgba(212,175,55,.28); }
        .btn-sm { padding: 8px 12px; font-size: 13px; border-radius: 10px; }
        .err { color: #e7a399; font-size: 13px; min-height: 18px; margin-top: 8px; }
        .hint { color: #8a7b62; font-size: 12px; margin: 6px 0 0; line-height: 1.45; }
        .close {
            float: right; background: rgba(255,255,255,.04); border: 1px solid rgba(212,175,55,.22);
            color: var(--muted); width: 34px; height: 34px; border-radius: 50%;
            font-size: 20px; line-height: 1; cursor: pointer;
        }
        .close:hover { color: #fff8e8; border-color: var(--gold); }

        /* Selfie camera */
        .selfie-box {
            border: 1px solid rgba(212,175,55,.28);
            border-radius: 16px;
            background: rgba(8,6,4,.55);
            padding: 12px;
            margin-top: 4px;
        }
        .selfie-stage {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            max-height: 280px;
            border-radius: 12px;
            overflow: hidden;
            background: #0a0806;
            display: none;
        }
        .selfie-stage.on { display: block; }
        .selfie-stage video,
        .selfie-stage img.selfie-shot {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .selfie-stage video { transform: scaleX(-1); }
        .selfie-stage img.selfie-shot { display: none; }
        .selfie-stage img.selfie-shot.on { display: block; }
        .selfie-stage video.is-hidden { display: none; }
        .selfie-toolbar {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;
        }
        .selfie-toolbar .btn { flex: 1 1 120px; }
        .selfie-preview-row {
            display: none; align-items: center; gap: 12px; margin-top: 12px;
        }
        .selfie-preview-row.on { display: flex; }
        .selfie-preview {
            width: 72px; height: 72px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--gold);
        }
        .selfie-preview-row span { color: #c4b498; font-size: 13px; }
        input#euSelfie { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

        /* Music player */
        .music-player {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: min(320px, calc(100vw - 32px));
            padding: 10px 12px;
            border-radius: 18px;
            border: 1px solid rgba(212,175,55,.4);
            background: rgba(10, 8, 6, .92);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 12px 36px rgba(0,0,0,.45);
            font-family: inherit;
        }
        .music-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .music-controls button {
            width: 40px; height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(212,175,55,.45);
            background: rgba(212,175,55,.12);
            color: #f0d57a;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .music-controls button:hover {
            background: linear-gradient(180deg, #f0d57a, #d4af37);
            color: #1a1408;
            border-color: transparent;
        }
        .music-controls button svg { width: 18px; height: 18px; fill: currentColor; }
        .music-controls #musicPlay svg { width: 16px; height: 16px; }
        .music-meta { flex: 1; min-width: 0; }
        .music-title {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #fff8e8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 6px;
        }
        .music-bar {
            position: relative;
            height: 5px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            cursor: pointer;
            touch-action: none;
        }
        .music-fill {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, #d4af37, #f0d57a);
            pointer-events: none;
        }
        .music-time {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            font-size: 10px;
            color: #a89878;
            letter-spacing: .04em;
            font-variant-numeric: tabular-nums;
        }
        @media (max-width: 420px) {
            .music-player {
                left: 12px;
                right: 12px;
                bottom: 12px;
                min-width: 0;
            }
            .music-title { font-size: 11px; }
        }
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
        body.is-landing { background: #050403; }
        body.is-landing .shell {
            display: block;
            min-height: 100vh;
            position: relative;
        }
        body.is-landing .portrait {
            position: relative;
            width: 100%;
            height: auto;
            min-height: 0;
            max-height: none;
            background: #050403;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
        }
        body.is-landing .portrait img {
            position: relative;
            inset: auto;
            width: min(1100px, 100%);
            height: auto;
            max-height: 72vh;
            padding: 0;
            object-fit: contain;
            object-position: center;
            opacity: 1 !important;
            image-rendering: auto;
        }
        body.is-landing .portrait .wash,
        body.is-landing .portrait .caption { display: none; }
        body.is-landing .main {
            position: relative;
            z-index: 2;
            min-height: auto;
            background: #050403;
            display: flex;
            flex-direction: column;
            padding: 18px 20px 28px;
        }
        body.is-landing .nav {
            justify-content: center;
            margin-bottom: 18px;
            position: sticky;
            top: 0;
            z-index: 5;
            padding: 10px 0;
            background: linear-gradient(180deg, #050403 70%, transparent);
        }
        body.is-landing .landing-home h1 { display: none; }
        body.is-landing .landing-home {
            flex: 0 0 auto;
            min-height: 0;
            justify-content: flex-start;
            align-items: center;
            text-align: center;
            padding: 8px 0 0;
        }
        body.is-landing .landing-home .rings {
            margin: 0 auto 18px;
            max-width: 520px;
        }
        body.is-landing .sun-row {
            justify-content: center;
            margin-top: 0;
            padding-top: 0;
        }
        body.is-landing .eulogy-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 22px auto 8px;
            padding: 14px 28px;
            border-radius: 999px;
            border: 2px solid #d4af37;
            background: linear-gradient(180deg, #f0d57a, #d4af37);
            color: #1a1408;
            font-weight: 800;
            font-size: 17px;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
        }
        body.is-landing .eulogies:not(.is-hidden) {
            background: rgba(8,6,4,.95);
            border: 1px solid #4a3b1c;
            border-radius: 18px;
            padding: 20px 18px;
            margin-top: 18px;
        }
        @media (max-width: 860px) {
            body.is-landing .portrait { padding: 0 8px; }
            body.is-landing .portrait img { max-height: none; width: 100%; }
            body.is-landing .main { padding: 12px 12px 24px; }
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
        <span>1953 — 2026 · 73 years</span>
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
