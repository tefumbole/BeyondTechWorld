@extends('beyond.auth.layout')

@section('title', 'Supervisor login')

@php
    $title = 'Supervisor login';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Internship Supervisor</h1><p class="text-brand-blue text-sm mt-1">Sign in with WhatsApp OTP</p>';
@endphp

@section('auth_body')
@if(($step ?? 'phone') === 'otp')
    <p class="text-sm text-gray-600 mb-4">Enter the 6-digit code sent to {{ $maskedPhone ?? 'your WhatsApp' }}.</p>
    <form method="POST" action="{{ url('/staff-otp-login/verify') }}" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-semibold text-gray-700">Verification code</label>
            <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required autofocus
                   class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 tracking-widest text-center text-lg"
                   placeholder="000000">
            @error('otp')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md">
            Verify and continue
        </button>
    </form>
    <form method="POST" action="{{ url('/staff-otp-login/resend') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="text-sm text-brand-gold font-semibold hover:underline">Resend code</button>
    </form>
@else
    <p class="text-sm text-gray-600 mb-4">
        Use the WhatsApp number linked to your supervisor account.
        Existing staff can also <a href="{{ url('/login') }}" class="text-brand-blue font-semibold hover:underline">sign in with email and password</a>.
    </p>
    <form method="POST" action="{{ url('/staff-otp-login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-semibold text-gray-700">WhatsApp number</label>
            <div class="flex gap-2 mt-1">
                <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 w-40 shrink-0">
                    @foreach(($countryCodes ?? []) as $code => $label)
                        <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phone" required value="{{ old('phone') }}"
                       placeholder="Phone number"
                       class="flex-1 rounded-md border border-gray-200 px-3 py-2">
            </div>
            @error('phone')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
            <i data-lucide="message-circle" class="w-4 h-4"></i> Send WhatsApp OTP
        </button>
    </form>
@endif

<p class="text-center mt-6 text-sm">
    <a href="{{ url('/login') }}" class="text-brand-gold hover:underline inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Sign in
    </a>
</p>
@endsection
