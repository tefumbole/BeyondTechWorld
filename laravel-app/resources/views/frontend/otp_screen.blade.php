@extends('frontend.layout.main')
@section('content')
<style>
    .topbar, .header, .section-box.box-newsletter, .footer, .box-notify, #preloader-active {
        display: none !important;
    }
    body {
        background: #063f8f;
    }
    .main {
        min-height: 100vh;
        padding: 28px 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .otp-card {
        width: 100%;
        max-width: 980px;
        background: #ffffff;
        border-radius: 20px;
        overflow: hidden;
        display: grid;
        grid-template-columns: 1fr 1fr;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
    }
    .otp-left {
        background: #f4f6f9;
        padding: 32px 24px;
        text-align: center;
        border-right: 1px solid #e7e8ed;
    }
    .otp-left-preview {
        margin: 0 auto 16px;
        max-width: min(220px, 70%);
        border-radius: 14px;
        background: #0b3f90;
        padding: 14px;
    }
    .otp-left-preview img {
        width: 100%;
        border-radius: 8px;
        display: block;
    }
    .otp-left h3 {
        margin: 0 0 10px;
        font-size: clamp(30px, 4vw, 40px);
        color: #14396f;
        font-weight: 700;
    }
    .otp-left p {
        margin: 0 auto;
        max-width: 360px;
        color: #5f6778;
        font-size: clamp(19px, 2.4vw, 24px);
    }
    .otp-right {
        padding: 34px 26px;
    }
    .otp-right h2 {
        margin: 0;
        color: #1c2433;
        font-size: clamp(34px, 5vw, 52px);
        font-weight: 700;
    }
    .otp-right p {
        margin: 8px 0 0;
        color: #5f6778;
        font-size: clamp(22px, 3.1vw, 30px);
    }
    .otp-alert {
        margin-top: 16px;
    }
    .otp-code-input {
        width: 100%;
        height: 82px;
        margin-top: 18px;
        border: 2px solid #c0a954;
        border-radius: 16px;
        text-align: center;
        font-size: clamp(34px, 5vw, 50px);
        font-weight: 700;
        letter-spacing: clamp(8px, 1.6vw, 16px);
        padding-left: 12px;
    }
    .otp-meta {
        display: flex;
        justify-content: space-between;
        margin-top: 16px;
        font-size: clamp(13px, 1.5vw, 16px);
        color: #596173;
    }
    .otp-meta strong {
        color: #14396f;
    }
    .otp-btn {
        width: 100%;
        margin-top: 18px;
        border: 0;
        height: 72px;
        border-radius: 10px;
        background: #0b3f90;
        color: #fff;
        font-size: clamp(28px, 3.7vw, 34px);
        font-weight: 700;
    }
    .otp-resend {
        text-align: center;
        margin-top: 22px;
    }
    .otp-resend a {
        color: #69758f;
        font-weight: 600;
        text-decoration: none;
        font-size: clamp(16px, 2.4vw, 22px);
    }
    .otp-back {
        text-align: center;
        margin-top: 14px;
    }
    .otp-back a {
        color: #8a92a3;
        text-decoration: none;
        font-size: clamp(16px, 2.4vw, 22px);
    }
    @media (max-width: 992px) {
        .otp-card {
            grid-template-columns: 1fr;
        }
        .otp-left {
            border-right: 0;
            border-bottom: 1px solid #e7e8ed;
        }
    }
</style>

<main class="main">
    <div class="otp-card">
        <div class="otp-left">
            <div class="otp-left-preview">
                <img src="{{ asset('public/logo/'.$general_setting->site_logo) }}" alt="Security verification preview">
            </div>
            <h3>Secure Authentication</h3>
            <p>We use Two-Factor Authentication to ensure your account remains safe.</p>
        </div>
        <div class="otp-right">
            <h2>Verify It's You</h2>
            <p>Enter the 6-digit code sent to your WhatsApp</p>

            @if($errors->has('name'))
                <div class="alert alert-danger otp-alert">{{ $errors->first('name') }}</div>
            @endif
            @if(session()->has('not_permitted'))
                <div class="alert alert-danger otp-alert">{{ session()->get('not_permitted') }}</div>
            @endif
            @if(session()->has('success'))
                <div class="alert alert-success otp-alert">{{ session()->get('success') }}</div>
            @endif

            <form method="POST" action="{{ route('otp_verify') }}" id="otp-form">
                @csrf
                @if(auth()->user())
                    <input name="user" type="hidden" value="{{ auth()->user()->id }}">
                @else
                    <input name="user" type="hidden" value="">
                @endif

                <input id="otp-code" class="otp-code-input" name="otp" type="text" maxlength="6" placeholder="000000" required>

                <div class="otp-meta">
                    <span>Expires in: <strong id="otp-timer">5:00</strong></span>
                    <span>5 attempts left</span>
                </div>

                <button type="submit" class="otp-btn">Verify OTP</button>
            </form>

            <div class="otp-resend">
                <a id="resend-link" href="{{ route('otp.resend') }}">Resend Code</a>
            </div>
            <div class="otp-back">
                <a href="{{ route('shop.login') }}">&larr; Back to Login</a>
            </div>
        </div>
    </div>
</main>

<script>
    (function () {
        var otpInput = document.getElementById('otp-code');
        var timerEl = document.getElementById('otp-timer');
        var resendLink = document.getElementById('resend-link');
        var seconds = 300;

        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        function tick() {
            var min = Math.floor(seconds / 60);
            var sec = seconds % 60;
            timerEl.textContent = min + ':' + (sec < 10 ? '0' + sec : sec);
            if (seconds > 0) {
                seconds -= 1;
                setTimeout(tick, 1000);
            }
        }
        tick();

        resendLink.addEventListener('click', function () {
            seconds = 300;
        });
    })();
</script>
@endsection
