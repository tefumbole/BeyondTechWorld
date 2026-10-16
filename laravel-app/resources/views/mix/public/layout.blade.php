<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mix & Master') · Beyond Tech World</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #0c1016; color: #e7eef8; }
        a { color: #8fd8ff; }
        header { padding: 22px 20px 8px; max-width: 1100px; margin: 0 auto; }
        header a.brand { color: #fff; text-decoration: none; font-weight: 800; letter-spacing: .04em; }
        main { max-width: 1100px; margin: 0 auto; padding: 8px 20px 48px; }
        .search { display: flex; gap: 8px; margin: 18px 0; }
        .search input { flex: 1; background: #171e29; color: #fff; border: 1px solid #2c3a4e; border-radius: 12px; padding: 14px 16px; font-size: 16px; }
        .search button, .btn { background: #1d6fd6; color: #fff; border: 0; border-radius: 999px; padding: 12px 16px; font-weight: 700; text-decoration: none; display: inline-block; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
        .chips a { background: #171e29; color: #d5deea; text-decoration: none; border-radius: 999px; padding: 8px 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
        .card { background: #151c27; border: 1px solid #263244; border-radius: 16px; padding: 14px; }
        .card h3 { margin: 0 0 6px; font-size: 18px; }
        .muted { color: #93a4ba; }
        .tag { display: inline-block; background: #12324a; color: #9ad7ff; border-radius: 999px; padding: 2px 8px; font-size: 12px; }
        h2 { margin: 28px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 6px 4px; border-bottom: 1px solid #263244; }
        svg { width: 100%; height: auto; border-radius: 12px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; }
        @media (max-width: 640px) { .search { flex-direction: column; } }
    </style>
</head>
<body>
<header><a class="brand" href="{{ route('mix.home') }}">BEYOND MIX & MASTER</a></header>
<main>@yield('content')</main>
<script src="{{ url('public/js/mix-eq.js') }}"></script>
</body>
</html>
