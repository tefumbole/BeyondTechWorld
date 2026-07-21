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
            background: linear-gradient(135deg, #003D82 0%, #001f42 55%, #002855 100%);
        }
        @keyframes beyondLogoSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .auth-card {
            width: 100%;
            max-width: 460px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.28);
            padding: 36px 32px 32px;
            text-align: center;
            overflow: hidden;
        }
        .logo-wrap {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto 16px;
        }
        .logo-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: conic-gradient(from 0deg, #D4AF37, #003D82, #0066CC, #D4AF37);
            animation: beyondLogoSpin 8s linear infinite;
        }
        .logo-inner {
            position: absolute;
            inset: 3px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .auth-logo {
            width: 80%;
            height: 80%;
            object-fit: contain;
            animation: beyondLogoSpin 6s linear infinite;
        }
        .auth-title {
            margin: 0;
            color: #003D82;
            font-size: clamp(22px, 4.5vw, 30px);
            font-weight: 800;
            line-height: 1.2;
        }
        .auth-sub {
            margin: 6px 0 0;
            color: #003D82;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.85;
        }
        .auth-rule {
            width: 96px;
            height: 4px;
            margin: 16px auto 28px;
            border-radius: 999px;
            background: #003D82;
        }
        .form-label {
            display: block;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .auth-input {
            width: 100%;
            height: 48px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #fff;
            font-size: 15px;
            padding: 0 14px;
            margin-bottom: 16px;
            transition: border-color .15s, box-shadow .15s;
        }
        .auth-input:focus {
            outline: none;
            border-color: #003D82;
            box-shadow: 0 0 0 3px rgba(0, 61, 130, 0.15);
        }
        .btn-login {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 8px;
            background: #003D82;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin-top: 4px;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-login:hover { background: #002855; }
        .alert {
            text-align: left;
            font-size: 14px;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .portal-link {
            margin-top: 18px;
            font-size: 13px;
            color: #6b7280;
        }
        .portal-link a {
            color: #D4AF37;
            font-weight: 600;
            text-decoration: none;
        }
        .portal-link a:hover { text-decoration: underline; }
        .version-footer {
            position: fixed;
            bottom: 14px;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255, 255, 255, .72);
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="logo-wrap">
        <div class="logo-ring" aria-hidden="true"></div>
        <div class="logo-inner">
            <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="auth-logo">
        </div>
    </div>
    <h1 class="auth-title">{{ $appName }}</h1>
    <p class="auth-sub">Staff Admin Portal</p>
    <div class="auth-rule"></div>

    @if(session()->has('delete_message'))
        <div class="alert alert-danger">{{ session()->get('delete_message') }}</div>
    @endif
    @if ($errors->has('name'))
        <div class="alert alert-danger">{{ $errors->first('name') }}</div>
    @endif
    @if ($errors->has('password'))
        <div class="alert alert-danger">{{ $errors->first('password') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="login-form">
        @csrf
        <label class="form-label" for="login-username">Email or Username</label>
        <input id="login-username" type="text" name="name" class="auth-input" value="{{ old('name') }}" required autocomplete="username">

        <label class="form-label" for="login-password">Password</label>
        <input id="login-password" type="password" name="password" class="auth-input" required autocomplete="current-password">

        <button type="submit" class="btn-login">Login</button>
    </form>

    <p class="portal-link">
        User portal? <a href="{{ url('/login') }}">Sign in here</a>
    </p>
</div>
<div class="version-footer">
    {{ \App\Support\AppVersion::display() }}
</div>
</body>
</html>
