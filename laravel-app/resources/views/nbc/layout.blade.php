<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Nkwen Baptist Church Praise Team')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --ink: #1c160e;
            --paper: #f7f1e4;
            --muted: #5c5348;
            --card: #fffdf8;
            --line: #e4d3a4;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: "Source Sans Pro", sans-serif; }
        a { color: inherit; }
        .nav {
            display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
            padding: 18px 16px 8px; background: #f3ead6;
        }
        .nav a, .nav span {
            border: 1px solid #c9b36a; background: #fffdf8; color: var(--ink);
            border-radius: 999px; padding: 8px 16px; text-decoration: none; font-weight: 600; font-size: 15px;
        }
        .nav a.on { background: #efe2c4; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 28px 20px 64px; }
        .kicker { letter-spacing: .22em; text-transform: uppercase; color: #6b5410; font-size: 12px; font-weight: 700; margin: 0 0 8px; }
        h1 { font-family: "Cormorant Garamond", serif; font-size: clamp(40px, 6vw, 64px); line-height: 1.05; margin: 0 0 8px; font-weight: 700; }
        h2 { font-family: "Cormorant Garamond", serif; font-size: 32px; margin: 28px 0 8px; }
        .sub { color: var(--muted); margin: 0 0 22px; }
        .row {
            display: grid; grid-template-columns: 42px 1fr; gap: 12px;
            padding: 14px 12px; border-bottom: 1px solid var(--line); text-decoration: none;
        }
        .row:hover { background: #f3ead6; }
        .num { color: #6b5410; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 16px 18px; margin: 0 0 12px; }
        label { display: block; font-weight: 700; margin: 12px 0 4px; }
        input, textarea, select {
            width: 100%; border: 1px solid #c9b36a; background: #fff; color: var(--ink);
            border-radius: 10px; padding: 10px 12px; font: inherit;
        }
        input[type="checkbox"] { width: auto; margin-right: 6px; }
        textarea { min-height: 120px; }
        button, .btn {
            display: inline-block; margin-top: 14px; border: 0; background: #1c160e; color: #f7f1e4;
            border-radius: 999px; padding: 10px 18px; font-weight: 700; cursor: pointer; text-decoration: none;
        }
        .alert { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
        .error { border-color: #b23b32; color: #7a241e; }
        .bylaws { white-space: pre-wrap; line-height: 1.55; }
        canvas { width: 100%; height: 160px; border: 1px dashed #c9b36a; border-radius: 12px; background: #fff; touch-action: none; }
        .modules { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 640px) { .modules { grid-template-columns: 1fr; } }
        .module {
            display: block; background: var(--card); border: 1px solid var(--line); border-radius: 14px;
            padding: 16px; text-decoration: none; font-family: "Cormorant Garamond", serif; font-size: 26px;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .checks label { font-weight: 600; }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="{{ route('nbc.home') }}" class="{{ request()->routeIs('nbc.home') ? 'on' : '' }}">Home</a>
        <a href="{{ route('nbc.program') }}" class="{{ request()->routeIs('nbc.program') ? 'on' : '' }}">Program</a>
        <a href="{{ route('nbc.events') }}" class="{{ request()->routeIs('nbc.events') ? 'on' : '' }}">Events</a>
        <a href="{{ route('nbc.announcements') }}" class="{{ request()->routeIs('nbc.announcements') || request()->routeIs('nbc.announcement') ? 'on' : '' }}">Announcements</a>
        <a href="{{ route('nbc.join') }}" class="{{ request()->routeIs('nbc.join') ? 'on' : '' }}">Join</a>
        @if(!empty($nbcMember))
            <a href="{{ route('nbc.portal') }}" class="{{ request()->routeIs('nbc.portal') ? 'on' : '' }}">Portal</a>
            <a href="{{ route('nbc.logout') }}">Log out</a>
        @else
            <a href="{{ route('nbc.login') }}" class="{{ request()->routeIs('nbc.login') ? 'on' : '' }}">Sign in</a>
        @endif
    </nav>
    <main class="wrap">
        @if(session('nbc_message'))<div class="alert">{{ session('nbc_message') }}</div>@endif
        @if(session('nbc_error'))<div class="alert error">{{ session('nbc_error') }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
