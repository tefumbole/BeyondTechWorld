<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Company portal' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            color: #10233f;
            background: #071833 url('{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}') center / cover fixed;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 20, 50, .55), rgba(0, 40, 85, .78));
            pointer-events: none;
        }
        header, main { position: relative; z-index: 1; }
        header {
            background: #003D82;
            color: #fff;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        header a { color: #fff; margin-left: 16px; text-decoration: none; font-weight: 600; }
        header button { background: transparent; color: #fff; border: 0; padding: 0; font-weight: 600; cursor: pointer; }
        main { max-width: 960px; margin: 28px auto; padding: 0 16px 48px; }
        .card {
            background: rgba(255,255,255,.96);
            border: 1px solid rgba(212, 175, 55, .55);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, .22);
            margin-bottom: 16px;
        }
        .stage-kicker {
            margin: 0 0 6px;
            color: #003D82;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .banner {
            background: #ecfdf3;
            color: #14532d;
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        label { display: block; font-weight: 700; margin: 12px 0 4px; }
        input, textarea, select {
            width: 100%;
            max-width: 28rem;
            padding: 10px 12px;
            border: 1px solid #c9d4e5;
            border-radius: 999px;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            width: auto;
            max-width: none;
            padding: 0;
            border-radius: 4px;
            margin-right: 6px;
        }
        textarea { border-radius: 12px; }
        button, .btn {
            background: #D4AF37;
            color: #002855;
            border: 0;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 800;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        .btn.alt { background: #003D82; color: #fff; margin-left: 8px; }
        .muted { color: #5c6b7a; }
        .ok { background: #ecfdf3; color: #14532d; padding: 10px 12px; border-radius: 12px; }
        .bad { background: #fdecec; color: #7f1d1d; padding: 10px 12px; border-radius: 12px; }
        .hero { width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; margin-top: 12px; }
        .svc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .svc {
            border-radius: 14px;
            padding: 12px 14px;
            border: 2px solid transparent;
            color: #10233f;
        }
        .svc strong { display: block; }
        .svc-SALES_INVOICES { background: #dbeafe; border-color: #1d4ed8; }
        .svc-RENTALS { background: #ccfbf1; border-color: #0f766e; }
        .svc-MESSAGING { background: #dcfce7; border-color: #15803d; }
        .svc-QUOTATIONS { background: #fef3c7; border-color: #b45309; }
        .svc-DIGITAL_INVITATIONS { background: #ede9fe; border-color: #6d28d9; }
        .check { margin: 6px 0; }
        .check.done { color: #15803d; }
        @media (max-width: 700px) {
            .svc-grid { grid-template-columns: 1fr; }
            header { flex-direction: column; align-items: flex-start; }
        }
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
        <form method="POST" action="{{ route('cloud.logout') }}" style="display:inline;">@csrf<button type="submit">Sign out</button></form>
    </nav>
</header>
<main>
    @if(session('message'))<p class="ok">{{ session('message') }}</p>@endif
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    @yield('content')
</main>
</body>
</html>
