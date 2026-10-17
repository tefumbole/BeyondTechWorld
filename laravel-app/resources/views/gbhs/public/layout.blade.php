<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->seo_title ?? 'GBHS Kumbo Ex-Students' }}</title>
    <meta name="description" content="{{ $setting->seo_description ?? 'GBHS Kumbo Ex-Students Association' }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&family=Source+Sans+3:wght@400;600;700&display=swap">
    <style>
        :root {
            --forest: #0c3a22;
            --forest-deep: #071f13;
            --leaf: #1d6a3a;
            --gold: #e8c15a;
            --gold-deep: #c8962e;
            --royal: #1b4e9b;
            --cream: #f7f3e8;
            --ink: #12261a;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Source Sans 3", sans-serif; font-size: 1.2rem; line-height: 1.6; color: var(--ink); background: var(--cream); }
        a { color: var(--leaf); }
        header { background: var(--forest-deep); color: var(--cream); }
        .bar { display: flex; align-items: center; gap: 28px; padding: 10px 4vw; }
        .brand { display: flex; align-items: center; text-decoration: none; }
        .brand img { height: 96px; width: auto; display: block; background: transparent; }
        nav { display: flex; flex-wrap: wrap; gap: 8px 22px; flex: 1; }
        nav a { color: var(--cream); text-decoration: none; font-size: 1.15rem; font-weight: 600; }
        nav a:hover { color: var(--gold); }
        .login {
            margin-left: auto;
            color: var(--gold);
            border: 1px solid var(--gold);
            background: var(--forest-deep);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.05rem;
            border-radius: 6px;
            padding: 10px 18px;
            white-space: nowrap;
        }
        .login:hover { background: var(--gold); color: var(--forest-deep); }
        .banner-wrap { background: var(--forest-deep); line-height: 0; }
        .banner { width: 100%; height: auto; display: block; }
        main { padding: 0 0 72px; }
        .page { max-width: 980px; margin: 0 auto; padding: 36px 5vw 0; }
        h1, h2, h3 { font-family: "Source Serif 4", serif; color: var(--forest); line-height: 1.2; }
        h1 { font-size: 2.6rem; margin: 0 0 12px; }
        h2 { font-size: 1.8rem; }
        p { font-size: 1.2rem; }
        .card { background: #fffdf8; border: 1px solid #e4d3a2; border-radius: 12px; padding: 18px 20px; margin: 14px 0; font-size: 1.15rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
        label { display: block; font-weight: 700; margin-top: 12px; font-size: 1.05rem; }
        input, select, textarea { width: 100%; padding: 12px 14px; border: 1px solid #d7c59a; border-radius: 8px; font-size: 1.1rem; font-family: inherit; }
        button, .btn { background: var(--forest); color: var(--gold); border: 0; border-radius: 8px; padding: 12px 18px; text-decoration: none; display: inline-block; margin: 14px 10px 0 0; font-size: 1.1rem; font-weight: 700; }
        .btn.gold { background: var(--gold); color: var(--forest-deep); }
        .muted { color: #3d5346; font-size: 1.15rem; }
        .alert { padding: 12px 14px; border-radius: 8px; background: #fff4d2; margin-bottom: 16px; }
        img.fit { max-width: 100%; height: auto; border-radius: 12px; }
        footer { background: var(--forest-deep); color: #d7e6db; padding: 22px 4vw 32px; border-top: 10px solid var(--royal); box-shadow: inset 0 3px 0 var(--gold); font-size: 1.05rem; }
        footer a { color: var(--gold); }
        body.home {
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        body.home header { flex: 0 0 auto; }
        body.home header .brand { display: none; }
        body.home .bar { padding: 8px 3vw; }
        body.home .banner-wrap {
            flex: 1 1 auto;
            min-height: 0;
            container-type: size;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #063018;
        }
        body.home .banner-frame {
            position: relative;
            width: min(100cqw, calc(100cqh * 1024 / 576));
            aspect-ratio: 1024 / 576;
        }
        body.home .banner { width: 100%; height: 100%; object-fit: contain; }
        body.home .banner-crest {
            position: absolute;
            left: 2.4%;
            top: 14%;
            height: 48%;
            display: block;
        }
        body.home .banner-crest img { height: 100%; width: auto; display: block; }
        body.home footer { flex: 0 0 auto; padding: 8px 4vw 12px; }
        body.home main { display: none; }
        body.testimonials .page { max-width: 1100px; }
        @media (max-width: 800px) {
            .bar { flex-wrap: wrap; }
            nav { order: 3; width: 100%; }
            h1 { font-size: 2.1rem; }
        }
    </style>
</head>
<body class="@yield('body-class')">
<header>
    <div class="bar">
        <a class="brand" href="{{ route('gbhs.home') }}">
            <img src="{{ url('public/gbhs/gbhs-kumbo-crest.png') }}" width="96" height="96" alt="{{ $setting->hero_title ?? 'GBHS Kumbo' }}">
        </a>
        <nav>
            <a href="{{ route('gbhs.home') }}">Home</a>
            <a href="{{ route('gbhs.page', 'about') }}">About Us</a>
            <a href="{{ route('gbhs.membership') }}">Membership</a>
            <a href="{{ route('gbhs.page', 'leaders') }}">Testimonials</a>
            <a href="{{ route('gbhs.donate') }}">Donate</a>
        </nav>
        <a class="login" href="{{ url('/login') }}">Login</a>
    </div>
</header>
@yield('hero')
<main>
    <div class="page">
        @if(session('message'))<div class="alert">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert">{{ session('not_permitted') }}</div>@endif
        @yield('content')
    </div>
</main>
<footer>
    {{ $setting->address ?? 'GBHS Kumbo Ex-Students Association' }}
    @if(!empty($setting->phone)) · {{ $setting->phone }} @endif
    @if(!empty($setting->email)) · {{ $setting->email }} @endif
</footer>
</body>
</html>
