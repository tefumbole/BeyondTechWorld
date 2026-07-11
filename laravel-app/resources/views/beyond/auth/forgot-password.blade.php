@extends('beyond.auth.layout')

@section('title', 'Reset Password')

@php
    $title = 'Reset Password';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Reset Password</h1>';
@endphp

@section('auth_body')

@if (session('reset_complete'))
    <div class="text-center space-y-4">
        <i data-lucide="check-circle-2" class="w-16 h-16 text-green-600 mx-auto"></i>
        <p class="text-gray-700">Password updated. You can now sign in.</p>
        <a href="{{ url('/login') }}" class="inline-block bg-brand-blue text-white px-6 py-3 rounded-md font-semibold">Go to Login</a>
    </div>
@elseif (session('password_reset_step') == 2)
    <p class="text-sm text-gray-600 mb-4">Enter the code sent to {{ session('password_reset_masked') }} and choose a new password.</p>
    <form method="POST" action="{{ url('/forgot-password/confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-semibold text-gray-700">Verification Code</label>
            <input type="text" name="otp" maxlength="6" required class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
        </div>
        <div>
            <label class="text-sm font-semibold text-gray-700">New Password</label>
            <input type="password" name="password" required minlength="8" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
        </div>
        <div>
            <label class="text-sm font-semibold text-gray-700">Confirm Password</label>
            <input type="password" name="password_confirmation" required class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
        </div>
        <button type="submit" class="w-full bg-brand-blue text-white font-bold py-3 rounded-md">Reset Password</button>
    </form>
@else
    <p class="text-sm text-gray-600 mb-4">Enter the phone number linked to your account. We will send a verification code via WhatsApp.</p>
    <form method="POST" action="{{ url('/forgot-password') }}" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-semibold text-gray-700">Phone Number</label>
            <div class="relative mt-1">
                <i data-lucide="phone" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
                <input type="tel" name="phone" required placeholder="+237675321739" class="w-full pl-10 rounded-md border border-gray-200 px-3 py-2">
            </div>
        </div>
        <button type="submit" class="w-full bg-brand-blue text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
            <i data-lucide="message-circle" class="w-4 h-4"></i> Send Verification Code
        </button>
    </form>
@endif

<p class="text-center mt-6 text-sm">
    <a href="{{ url('/login') }}" class="text-brand-gold hover:underline inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Login
    </a>
</p>
@endsection
