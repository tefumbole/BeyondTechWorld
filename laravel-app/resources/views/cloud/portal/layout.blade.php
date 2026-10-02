<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Company portal' }}</title>
    <style>
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f4f7fb; color: #1c2430; }
        header { background: #0b3f90; color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; margin-left: 16px; text-decoration: none; }
        main { max-width: 880px; margin: 28px auto; padding: 0 16px 40px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 8px 24px rgba(11,63,144,.08); margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin: 12px 0 4px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #c9d4e5; border-radius: 8px; box-sizing: border-box; }
        button, .btn { background: #0b3f90; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; text-decoration: none; display: inline-block; cursor: pointer; }
        .muted { color: #5c6b7a; }
        .ok { background: #e8f6ee; padding: 10px 12px; border-radius: 8px; }
        .bad { background: #fdecec; padding: 10px 12px; border-radius: 8px; }
        .hero { width: 100%; max-height: 280px; object-fit: cover; border-radius: 12px; }
    </style>
</head>
<body>
<header>
    <strong>{{ $tenant->system_name ?? $tenant->name ?? 'Beyond Cloud' }}</strong>
    <nav>
        <a href="{{ route('cloud.home') }}">Home</a>
        <a href="{{ route('cloud.subscribe') }}">Subscribe</a>
        <a href="{{ route('cloud.messaging') }}">Messaging</a>
        <a href="{{ route('cloud.settings') }}">Settings</a>
        <form method="POST" action="{{ route('cloud.logout') }}" style="display:inline;">@csrf<button type="submit" style="background:transparent;color:#fff;padding:0;">Sign out</button></form>
    </nav>
</header>
<main>
    @if(session('message'))<p class="ok">{{ session('message') }}</p>@endif
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    @yield('content')
</main>
</body>
</html>
