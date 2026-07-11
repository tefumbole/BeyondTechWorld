<?php $general_setting = DB::table('general_settings')->find(1); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{$general_setting->site_title}}</title>
    <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
    <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
    <style>
        body {
            margin: 0;
            font-family: "Nunito", sans-serif;
            background: #063a83;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .otp-card {
            width: 100%;
            max-width: 460px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
            padding: 36px 32px 28px;
            text-align: center;
        }
        .auth-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            margin: 0 auto 14px;
            display: block;
            background: transparent;
        }
        .auth-title {
            margin: 0 0 24px;
            color: #0b3f90;
            font-size: clamp(24px, 4.5vw, 34px);
            font-weight: 800;
            line-height: 1.2;
            word-break: break-word;
        }
        .otp-input {
            width: 100%;
            height: 56px;
            border: 2px solid #c6ab47;
            border-radius: 12px;
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 10px;
            padding-left: 10px;
            margin-bottom: 14px;
        }
        .otp-input:focus {
            outline: none;
            border-color: #b49332;
            box-shadow: 0 0 0 2px rgba(180, 147, 50, 0.2);
        }
        .btn-verify {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 10px;
            background: #0b3f90;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
        }
        .otp-meta {
            margin: 12px 0 4px;
            font-size: 14px;
            color: #5d677a;
        }
        .otp-resend {
            margin-top: 14px;
        }
        .otp-resend button {
            border: 0;
            background: transparent;
            color: #8a92a3;
            font-size: 14px;
            font-weight: 600;
            padding: 0;
        }
        .otp-resend button.is-ready {
            color: #0b3f90;
            cursor: pointer;
        }
        .otp-resend button:disabled {
            cursor: not-allowed;
        }
        .otp-back {
            margin-top: 16px;
        }
        .otp-back a {
            color: #5d677a;
            font-size: 14px;
            text-decoration: none;
        }
        .app-version {
            margin-top: 18px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            letter-spacing: 0.04em;
        }
        .alert {
            text-align: left;
            font-size: 14px;
        }
    </style>
</head>
<body>
@php
    $appName = $general_setting->site_title ?? config('app.name', 'Application');
    $resendSeconds = isset($resend_seconds) ? (int) $resend_seconds : 0;
@endphp
<div class="otp-card">
    @if(!empty($general_setting->site_logo))
        <img src="{{url('public/logo', $general_setting->site_logo)}}" alt="{{$appName}}" class="auth-logo">
    @endif
    <h1 class="auth-title">{{$appName}}</h1>

    @if($errors->has('name'))
        <div class="alert alert-danger">{{ $errors->first('name') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success">{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger">{{ session()->get('not_permitted') }}</div>
    @endif
    @if(!empty($whatsapp_error))
        <div class="alert alert-danger">{{ $whatsapp_error }}</div>
    @endif

    @if(Auth::user()->is_active)
        <form action="{{ route('check.otp.store') }}" method="post">
            @csrf
            <input id="otp-code" class="otp-input" type="text" name="otp" maxlength="6" placeholder="000000" required>
            @if ($errors->has('otp'))
                <div class="alert alert-danger">{{ $errors->first('otp') }}</div>
            @endif
            <div class="otp-meta">Code expires in <strong id="otp-timer">5:00</strong></div>
            <button type="submit" class="btn-verify">Verify OTP</button>
        </form>

        <div class="otp-resend">
            <form id="otp-resend-form" action="{{ route('check.otp.resend') }}" method="post">
                @csrf
                <button type="submit" id="otp-resend-btn" disabled>
                    Resend OTP in <span id="resend-countdown">{{ max(0, $resendSeconds) }}</span>s
                </button>
            </form>
        </div>

        <div class="otp-back">
            <a href="{{ route('login') }}">&larr; Back to Login</a>
        </div>
    @else
        <div class="alert alert-warning">You are logged in but the account is not active. Please contact admin.</div>
    @endif
</div>

<div class="app-version">{{ \App\Support\AppVersion::display() }}</div>

<script>
    (function () {
        var otpInput = document.getElementById('otp-code');
        if (otpInput) {
            otpInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }

        var timerEl = document.getElementById('otp-timer');
        var resendBtn = document.getElementById('otp-resend-btn');
        var resendCountdownEl = document.getElementById('resend-countdown');
        var expirySeconds = 300;
        var resendSeconds = {{ max(0, $resendSeconds) }};

        function formatTime(total) {
            var min = Math.floor(total / 60);
            var sec = total % 60;
            return min + ':' + (sec < 10 ? '0' + sec : sec);
        }

        function tickExpiry() {
            if (expirySeconds <= 0) {
                if (timerEl) {
                    timerEl.textContent = '0:00';
                }
                return;
            }
            if (timerEl) {
                timerEl.textContent = formatTime(expirySeconds);
            }
            expirySeconds -= 1;
            setTimeout(tickExpiry, 1000);
        }

        function tickResend() {
            if (resendSeconds <= 0) {
                if (resendBtn) {
                    resendBtn.disabled = false;
                    resendBtn.classList.add('is-ready');
                    resendBtn.innerHTML = 'Resend OTP';
                }
                return;
            }
            if (resendCountdownEl) {
                resendCountdownEl.textContent = resendSeconds;
            }
            resendSeconds -= 1;
            setTimeout(tickResend, 1000);
        }

        tickExpiry();
        tickResend();
    })();
</script>
</body>
</html>
