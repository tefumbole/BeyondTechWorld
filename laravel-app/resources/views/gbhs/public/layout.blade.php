<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->seo_title ?? 'GBHS Kumbo Ex-Students' }}</title>
    <meta name="description" content="{{ $setting->seo_description ?? 'GBHS Kumbo Ex-Students Association' }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,700&family=Source+Sans+3:wght@400;600&display=swap">
    <style>
        :root { --ink:#1c2430; --gold:#b8860b; --paper:#f7f3ea; --card:#fffdf8; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:"Source Sans 3", sans-serif; color:var(--ink); background:var(--paper); }
        a { color:#6d4c0b; }
        header { background:#1c2430; color:#f7f3ea; }
        .bar { display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 6vw; }
        .brand { font-family:"Source Serif 4", serif; font-size:1.25rem; color:#f7f3ea; text-decoration:none; }
        nav { display:flex; flex-wrap:wrap; gap:12px 16px; padding:0 6vw 14px; }
        nav a { color:#f3e6c4; text-decoration:none; font-size:.92rem; }
        main { padding:28px 6vw 64px; max-width:1100px; }
        h1,h2,h3 { font-family:"Source Serif 4", serif; }
        .hero { display:grid; grid-template-columns:1.4fr .8fr; gap:28px; align-items:center; }
        .card { background:var(--card); border:1px solid #eadfca; border-radius:12px; padding:16px 18px; margin:12px 0; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
        label { display:block; font-weight:600; margin-top:10px; }
        input,select,textarea { width:100%; padding:8px 10px; border:1px solid #d9cbb0; border-radius:8px; }
        button,.btn { background:#1c2430; color:#f7f3ea; border:0; border-radius:8px; padding:10px 14px; text-decoration:none; display:inline-block; margin-top:12px; }
        .muted { color:#5c6570; }
        .alert { padding:10px 12px; border-radius:8px; background:#fff4d6; }
        img.fit { max-width:100%; height:auto; border-radius:12px; }
        footer { padding:24px 6vw 40px; color:#5c6570; }
        @media (max-width:800px) { .hero { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <div class="bar">
        <a class="brand" href="{{ route('gbhs.home') }}">{{ $setting->hero_title ?? 'GBHS Kumbo' }}</a>
        <a href="{{ url('/login') }}" style="color:#f3e6c4">Admin Login</a>
    </div>
    <nav>
        <a href="{{ route('gbhs.page', 'about') }}">About</a>
        <a href="{{ route('gbhs.page', 'jubilee') }}">Jubilee</a>
        <a href="{{ route('gbhs.membership') }}">Membership</a>
        <a href="{{ route('gbhs.page', 'batches') }}">Batches</a>
        <a href="{{ route('gbhs.page', 'contributions') }}">Contributions</a>
        <a href="{{ route('gbhs.page', 'projects') }}">Projects</a>
        <a href="{{ route('gbhs.page', 'news') }}">News</a>
        <a href="{{ route('gbhs.page', 'documents') }}">Documents</a>
        <a href="{{ route('gbhs.page', 'contact') }}">Contact</a>
        <a href="{{ route('gbhs.donate') }}">Donate</a>
        <a href="{{ route('gbhs.status') }}">Status</a>
        <a href="{{ route('gbhs.member') }}">Member home</a>
    </nav>
</header>
<main>
    @if(session('message'))<div class="alert">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert">{{ session('not_permitted') }}</div>@endif
    @yield('content')
</main>
<footer>
    {{ $setting->address ?? '' }}
    @if(!empty($setting->phone)) · {{ $setting->phone }} @endif
    @if(!empty($setting->email)) · {{ $setting->email }} @endif
    <div><a href="{{ url('/login') }}">Admin Login</a></div>
</footer>
</body>
</html>
