@extends('beyond.auth.layout')

@section('title', 'OTP Verification')

@php
    $title = 'Verify OTP';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">WhatsApp Verification</h1><p class="text-brand-blue text-sm mt-1">Enter the 6-digit code sent to '.$maskedPhone.'</p>';
@endphp

@section('auth_body')
<form method="POST" action="{{ url('/otp-verification') }}" class="space-y-5">
    @csrf
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Verification Code</label>
        <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus
               class="w-full text-center text-2xl tracking-[0.5em] rounded-md border border-gray-200 px-3 py-3 focus:border-brand-blue outline-none"
               placeholder="000000">
    </div>
    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md">Verify & Continue</button>
</form>
<form method="POST" action="{{ url('/otp-verification/resend') }}" class="mt-4">
    @csrf
    <button type="submit" class="w-full border border-brand-gold text-brand-gold hover:bg-brand-gold hover:text-brand-blue font-semibold py-2 rounded-md flex items-center justify-center gap-2">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Resend Code
    </button>
</form>
<form method="POST" action="{{ url('/logout') }}" class="mt-3">
    @csrf
    <button type="submit" class="w-full text-sm text-gray-500 hover:text-brand-blue">Back to Login</button>
</form>
@endsection
