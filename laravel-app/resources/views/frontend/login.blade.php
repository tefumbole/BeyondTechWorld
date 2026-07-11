@extends('frontend.layout.main')
@section('content')
<style>
    .topbar, .header, .section-box.box-newsletter, .footer, .box-notify, #preloader-active {
        display: none !important;
    }
    body {
        background: #073a85;
    }
    .main {
        min-height: 100vh;
        padding: 32px 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .auth-card {
        width: 100%;
        max-width: 540px;
        border-radius: 18px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
    }
    .auth-card-top {
        background: #c6ab47;
        text-align: center;
        padding: 28px 24px 22px;
    }
    .auth-card-top h2 {
        margin: 0;
        font-size: clamp(28px, 5vw, 44px);
        font-weight: 700;
        color: #0a3f90;
        line-height: 1.1;
        word-break: break-word;
    }
    .auth-version {
        margin-top: 8px;
        font-size: clamp(12px, 2vw, 16px);
        font-weight: 600;
        color: #163f79;
    }
    .auth-card-body {
        padding: 34px 38px;
    }
    .auth-title {
        font-size: clamp(34px, 6vw, 44px);
        font-weight: 700;
        margin: 0;
        color: #152238;
        text-align: center;
    }
    .auth-subtitle {
        text-align: center;
        margin: 8px 0 22px;
        color: #5a6272;
        font-size: clamp(20px, 3.5vw, 24px);
    }
    .auth-alert {
        margin-bottom: 18px;
    }
    .auth-label {
        display: block;
        margin-bottom: 8px;
        font-size: clamp(22px, 3.5vw, 28px);
        font-weight: 600;
        color: #1e2533;
    }
    .auth-field {
        width: 100%;
        height: 78px;
        border: 1px solid #d8dda6;
        border-radius: 12px;
        background: #edf3bb;
        padding: 0 18px;
        font-size: clamp(20px, 3.3vw, 28px);
        margin-bottom: 18px;
    }
    .auth-field:focus {
        border-color: #b9ad61;
        box-shadow: 0 0 0 2px rgba(185, 173, 97, 0.18);
    }
    .auth-row {
        display: flex;
        justify-content: flex-end;
        margin: -4px 0 14px;
    }
    .auth-row a {
        color: #0a3f90;
        font-weight: 600;
        font-size: 22px;
        text-decoration: none;
    }
    .btn-login {
        width: 100%;
        border: 0;
        height: 82px;
        border-radius: 12px;
        background: #0b3f90;
        color: #fff;
        font-size: clamp(32px, 5vw, 42px);
        font-weight: 700;
        margin-top: 6px;
    }
    .auth-divider {
        text-align: center;
        color: #8a91a1;
        font-size: 24px;
        margin: 18px 0;
    }
    .btn-outline-auth {
        width: 100%;
        display: block;
        text-align: center;
        border: 1px solid #c7cfda;
        color: #14396f;
        border-radius: 10px;
        height: 74px;
        line-height: 74px;
        font-size: 34px;
        font-weight: 600;
        margin-bottom: 14px;
        text-decoration: none;
    }
    .auth-signup {
        margin-top: 12px;
        text-align: center;
        font-size: 18px;
        color: #5b6272;
    }
    .auth-signup a {
        color: #0a3f90;
        font-weight: 700;
        text-decoration: none;
    }
</style>

<main class="main">
    @php
        $appName = $general_setting->site_title ?? config('app.name', 'Application');
        $appVersion = $general_setting->app_version ?? env('APP_VERSION', 'V1.1.21');
    @endphp
    <div class="auth-card">
        <div class="auth-card-top">
            @if(!empty($general_setting->site_logo))
                <img src="{{url('public/logo', $general_setting->site_logo)}}" alt="{{$appName}} logo" style="width:52px;height:52px;object-fit:contain;margin-bottom:8px;">
            @endif
            <h2 title="{{$appName}}">{{$appName}}</h2>
            <div class="auth-version">Version {{$appVersion}}</div>
        </div>
        <div class="auth-card-body">
            <h3 class="auth-title">Welcome Back</h3>
            <p class="auth-subtitle">Sign in with your Email or Username</p>

            @if(session()->has('not_permitted'))
                <div class="alert alert-danger auth-alert">{{ session()->get('not_permitted') }}</div>
            @endif
            @if(session()->has('success'))
                <div class="alert alert-success auth-alert">{{ session()->get('success') }}</div>
            @endif
            @if ($errors->has('name'))
                <div class="alert alert-danger auth-alert">{{ $errors->first('name') }}</div>
            @endif
            @if ($errors->has('password'))
                <div class="alert alert-danger auth-alert">{{ $errors->first('password') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="login-form">
                @csrf
                <label class="auth-label" for="name">Email or Username</label>
                <input id="name" class="auth-field" name="name" type="text" value="{{ old('name') }}" required>

                <label class="auth-label" for="password">Password</label>
                <input id="password" class="auth-field" name="password" type="password" required>

                <div class="auth-row">
                    <a href="{{ route('forgot.password') }}">Forgot Password?</a>
                </div>

                <button class="btn-login" type="submit">Login &rarr;</button>
            </form>

            <div class="auth-divider">OR</div>
            <a class="btn-outline-auth" href="{{ route('shop.signup') }}">Create Customer Account</a>
            <a class="btn-outline-auth" href="{{ route('otp_screen') }}">Login with WhatsApp OTP</a>

            <div class="auth-signup">
                Don't have an account? <a href="{{ route('shop.signup') }}">Sign Up</a> to view tasks assigned to you.
            </div>
        </div>
    </div>
</main>
@endsection
