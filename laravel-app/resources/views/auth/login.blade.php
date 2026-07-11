<?php $general_setting = DB::table('general_settings')->find(1); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{$general_setting->site_title}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{url('manifest.json')}}">
    <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
    <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
    <style>
        body {
            margin: 0;
            font-family: "Nunito", sans-serif;
            background: #063a83;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .auth-card {
            width: 100%;
            max-width: 460px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
            padding: 36px 32px 32px;
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
            margin: 0 0 28px;
            color: #0b3f90;
            font-size: clamp(24px, 4.5vw, 34px);
            font-weight: 800;
            line-height: 1.2;
            word-break: break-word;
        }
        .form-label {
            display: block;
            text-align: left;
            font-size: 15px;
            font-weight: 700;
            color: #1f2738;
            margin-bottom: 6px;
        }
        .auth-input {
            width: 100%;
            height: 50px;
            border-radius: 10px;
            border: 1px solid #d4dca2;
            background: #f7f9ef;
            font-size: 16px;
            padding: 0 14px;
            margin-bottom: 14px;
        }
        .auth-input:focus {
            outline: none;
            border-color: #c6ab47;
            box-shadow: 0 0 0 2px rgba(198, 171, 71, 0.25);
        }
        .btn-login {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 10px;
            background: #0b3f90;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            margin-top: 6px;
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
@endphp
<div class="auth-card">
    @if(!empty($general_setting->site_logo))
        <img src="{{url('public/logo', $general_setting->site_logo)}}" alt="{{$appName}}" class="auth-logo">
    @endif
    <h1 class="auth-title">{{$appName}}</h1>

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
        <input id="login-username" type="text" name="name" class="auth-input" value="{{ old('name') }}" required>

        <label class="form-label" for="login-password">Password</label>
        <input id="login-password" type="password" name="password" class="auth-input" required>

        <button type="submit" class="btn-login">Login</button>
    </form>
</div>
<div style="position:fixed;bottom:14px;left:0;right:0;text-align:center;color:rgba(255,255,255,.72);font-size:12px;">
    {{ \App\Support\AppVersion::display() }}
</div>
</body>
</html>
