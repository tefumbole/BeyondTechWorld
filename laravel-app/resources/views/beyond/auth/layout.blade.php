<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $siteLogoUrl = \App\Support\SiteBrand::logoUrl($general_setting ?? null);
        $siteTitle = \App\Support\SiteBrand::siteTitle($general_setting ?? null);
        $developerPhone = '+237675321739';
        $versionLabel = \App\Support\AppVersion::label();
    @endphp
    <title>@yield('title', 'Sign in') | {{ $siteTitle }}</title>
    <link rel="icon" href="{{ $siteLogoUrl }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { blue: '#0b3f90', dark: '#083272', light: '#2563eb', gold: '#D4AF37', navy: '#0a2540' },
                    },
                },
            },
        };
    </script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            min-height: 100vh;
            background: #0a2540;
        }
        .auth-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 28px 16px 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 56px rgba(0, 0, 0, 0.32);
            padding: 36px 32px 28px;
            text-align: center;
        }
        .auth-logo-ring {
            width: 108px;
            height: 108px;
            margin: 0 auto 16px;
            border-radius: 50%;
            padding: 4px;
            background: linear-gradient(180deg, #f0d36a 0%, #c9a227 55%, #8a6d12 100%);
            box-shadow: 0 0 0 6px rgba(212, 175, 55, 0.18), 0 0 28px rgba(212, 175, 55, 0.38);
            animation: authLogoGlow 3.2s ease-in-out infinite;
        }
        .auth-logo-ring img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: contain;
            background: #fff;
            display: block;
            animation: authLogoSpin 8s linear infinite, authLogoMetal 5s ease-in-out infinite;
        }
        @keyframes authLogoSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes authLogoMetal {
            0%, 100% {
                filter: sepia(1) saturate(4.2) hue-rotate(2deg) brightness(1.12) contrast(1.05);
            }
            50% {
                filter: grayscale(1) brightness(1.45) contrast(1.15) saturate(0.2);
            }
        }
        @keyframes authLogoGlow {
            0%, 100% {
                box-shadow: 0 0 0 6px rgba(212, 175, 55, 0.18), 0 0 28px rgba(212, 175, 55, 0.38);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(220, 220, 230, 0.22), 0 0 36px rgba(220, 220, 230, 0.55);
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-logo-ring,
            .auth-logo-ring img { animation: none; }
        }
        .auth-title {
            margin: 0;
            color: #0b3f90;
            font-size: clamp(22px, 4.6vw, 28px);
            font-weight: 800;
            letter-spacing: 0.03em;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .auth-sub {
            margin: 8px 0 0;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }
        .auth-rule {
            width: 100%;
            height: 2px;
            margin: 18px 0 22px;
            background: #0b3f90;
            border: 0;
        }
        .auth-field {
            position: relative;
            margin-bottom: 14px;
            text-align: left;
        }
        .auth-field > svg.auth-ico {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #0b3f90;
            pointer-events: none;
        }
        .auth-input {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 999px;
            background: #f3f1e8;
            font-size: 15px;
            padding: 0 44px 0 44px;
            color: #1f2937;
        }
        .auth-input::placeholder { color: #9ca3af; }
        .auth-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 63, 144, 0.28);
        }
        .auth-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            padding: 4px;
            color: #6b7280;
            cursor: pointer;
            display: inline-flex;
        }
        .auth-toggle:hover { color: #0b3f90; }
        .auth-btn {
            width: 100%;
            height: 52px;
            margin-top: 6px;
            border: 0;
            border-radius: 999px;
            background: #0b3f90;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .auth-btn:hover { background: #083272; }
        .auth-links {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        .auth-links a {
            color: #6b7280;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .auth-links a:hover { color: #0b3f90; text-decoration: underline; }
        .auth-links .wa { color: #16a34a; }
        .auth-alert {
            text-align: left;
            font-size: 14px;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .auth-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .auth-alert-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .auth-screen-footer {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
        }
        .auth-credit {
            color: #e8c547;
            font-size: 12px;
            font-weight: 500;
        }
        .auth-version {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            background: #111;
            border: 1px solid #d4af37;
            color: #f0d36a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
    </style>
    @stack('head')
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo-ring" aria-hidden="true">
            <img src="{{ $siteLogoUrl }}" alt="{{ $siteTitle }}">
        </div>

        @isset($header)
            {!! $header !!}
        @else
            <h1 class="auth-title">{{ $siteTitle }}</h1>
            <p class="auth-sub">@yield('auth_subtitle', 'Sign in to the dashboard')</p>
        @endisset

        <hr class="auth-rule">

        @if (session('success'))
            <div class="auth-alert auth-alert-ok">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="auth-alert auth-alert-error">{{ $errors->first() }}</div>
        @endif

        @yield('auth_body')
    </div>

    <div class="auth-screen-footer">
        <div class="auth-credit">Developed By | Sr. Engr. Tefu R. Mbole ({{ $developerPhone }})</div>
        <div class="auth-version">BCL V.{{ $versionLabel }}</div>
    </div>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    if (window.lucide) lucide.createIcons();
</script>
@stack('scripts')
</body>
</html>
