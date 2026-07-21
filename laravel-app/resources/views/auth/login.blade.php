<?php $general_setting = DB::table('general_settings')->find(1); ?>
@php
    $appName = \App\Support\SiteBrand::siteTitle($general_setting ?? null);
    $logoUrl = \App\Support\SiteBrand::logoUrl($general_setting ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $appName }} — Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{ url('manifest.json') }}">
    <link rel="icon" type="image/png" href="{{ $logoUrl }}" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: #0a2540;
        }
        @keyframes logoSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes logoGlow {
            0%, 100% { box-shadow: 0 0 0 6px rgba(212, 175, 55, 0.18), 0 0 28px rgba(212, 175, 55, 0.45); }
            50% { box-shadow: 0 0 0 8px rgba(212, 175, 55, 0.28), 0 0 40px rgba(212, 175, 55, 0.65); }
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 22px 48px rgba(0, 0, 0, 0.28);
            padding: 40px 32px 28px;
            text-align: center;
        }
        .logo-spin-wrap {
            width: 104px;
            height: 104px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: logoGlow 2.8s ease-in-out infinite;
        }
        .auth-logo {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            object-fit: contain;
            background: #fff;
            animation: logoSpin 6s linear infinite;
        }
        .auth-title {
            margin: 0;
            color: #0b3f90;
            font-size: clamp(22px, 4.5vw, 28px);
            font-weight: 800;
            letter-spacing: 0.02em;
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
            margin: 18px 0 24px;
            background: #0b3f90;
            border: 0;
        }
        .field {
            position: relative;
            margin-bottom: 14px;
        }
        .field svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #0b3f90;
            pointer-events: none;
        }
        .auth-input {
            width: 100%;
            height: 48px;
            border-radius: 999px;
            border: 0;
            background: #f3f1e8;
            font-size: 15px;
            padding: 0 16px 0 42px;
            color: #1f2937;
        }
        .auth-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 63, 144, 0.25);
        }
        .btn-login {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 999px;
            background: #0b3f90;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin-top: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-login:hover { background: #083272; }
        .alert {
            text-align: left;
            font-size: 14px;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .portal-link {
            margin-top: 18px;
            font-size: 13px;
            color: #4b5563;
        }
        .portal-link a {
            color: #0b3f90;
            font-weight: 600;
            text-decoration: none;
        }
        .portal-link a:hover { text-decoration: underline; }
        .credit-footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            line-height: 1.55;
            color: #6b7280;
        }
        .credit-footer .version {
            font-weight: 700;
            color: #0b3f90;
            letter-spacing: 0.02em;
        }
        .credit-footer .dev {
            margin-top: 4px;
        }
        .credit-footer .dev strong {
            color: #374151;
            font-weight: 600;
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-logo, .logo-spin-wrap { animation: none; }
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="logo-spin-wrap" aria-hidden="true">
        <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="auth-logo">
    </div>
    <h1 class="auth-title">{{ $appName }}</h1>
    <p class="auth-sub">Sign in to the dashboard</p>
    <hr class="auth-rule">

    @if(session()->has('delete_message'))
        <div class="alert">{{ session()->get('delete_message') }}</div>
    @endif
    @if ($errors->has('name'))
        <div class="alert">{{ $errors->first('name') }}</div>
    @endif
    @if ($errors->has('password'))
        <div class="alert">{{ $errors->first('password') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="login-form">
        @csrf
        <div class="field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <input id="login-username" type="text" name="name" class="auth-input" value="{{ old('name') }}" placeholder="Username" required autocomplete="username">
        </div>
        <div class="field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input id="login-password" type="password" name="password" class="auth-input" placeholder="Password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
            </svg>
            Sign In
        </button>
    </form>

    <p class="portal-link">
        ← <a href="{{ url('/') }}">Back to Homepage</a>
        &nbsp;·&nbsp;
        <a href="{{ url('/login') }}">User portal</a>
    </p>

    <div class="credit-footer">
        <div class="version">{{ \App\Support\AppVersion::bcl() }}</div>
        <div class="dev">Developed By: <strong>Sr. Engr. Tefu R. Mbole</strong></div>
    </div>
</div>
</body>
</html>
