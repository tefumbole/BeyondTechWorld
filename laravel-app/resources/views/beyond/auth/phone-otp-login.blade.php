@extends('beyond.auth.layout')

@section('title', 'WhatsApp login')

@php
    $header = '<h1 class="auth-title">WhatsApp login</h1><p class="auth-sub">Phone number + OTP. A password is optional.</p>';
@endphp

@section('auth_body')
@if(($step ?? 'phone') === 'otp')
    <p class="text-sm text-gray-600 mb-4">Enter the 6-digit code sent to <strong>{{ $maskedPhone ?? 'your WhatsApp' }}</strong>.</p>
    <form method="POST" action="{{ url('/phone-login/verify') }}">
        @csrf
        <div class="auth-field">
            <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required autofocus
                   class="auth-input" placeholder="000000" style="text-align:center;letter-spacing:.3em;">
        </div>
        <button type="submit" class="auth-btn">Verify and continue</button>
    </form>
    <form method="POST" action="{{ url('/phone-login/resend') }}" class="mt-3">
        @csrf
        <button type="submit" class="auth-links" style="background:none;border:0;cursor:pointer;color:#0b3f90;font-weight:600;">Resend code</button>
    </form>
@else
    <p class="text-sm text-gray-600 mb-4" style="text-align:left;">
        Use the WhatsApp number already in the system. We show part of it after we send the code.
        You can add a password later if you want.
    </p>
    <form method="POST" action="{{ url('/phone-login') }}">
        @csrf
        @php $loginRedirect = request('redirect') ?: session('beyond_intended'); @endphp
        @if($loginRedirect)
            <input type="hidden" name="redirect" value="{{ $loginRedirect }}">
        @endif
        <div class="auth-field" style="display:flex;gap:8px;padding:0;">
            <select name="country_code" class="auth-input" style="width:42%;padding-left:16px;">
                @foreach(($countryCodes ?? []) as $code => $label)
                    <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                @endforeach
            </select>
            <input type="tel" name="phone" required value="{{ old('phone') }}"
                   class="auth-input" style="padding-left:16px;" placeholder="675321739">
        </div>
        <button type="submit" class="auth-btn">Send WhatsApp OTP</button>
    </form>
@endif

<div class="auth-links">
    <a href="{{ url('/login') }}">Sign in with password instead</a>
    <a href="{{ url('/') }}">Back to Homepage</a>
</div>
@endsection
