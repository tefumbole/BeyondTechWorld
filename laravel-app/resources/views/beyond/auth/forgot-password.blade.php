@extends('beyond.auth.layout')

@section('title', 'Reset Password')

@php
    $title = 'Reset Password';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Reset Password</h1>';
    $prefill = $prefillPhone ?? '';
    $prefillLooksFull = strlen(preg_replace('/\D/', '', $prefill)) >= 10 && strpos($prefill, '+') === 0;
@endphp

@section('auth_body')

@if (session('reset_complete'))
    <div class="text-center space-y-4">
        <i data-lucide="check-circle-2" class="w-16 h-16 text-green-600 mx-auto"></i>
        <p class="text-gray-700">Password updated. You can now sign in.</p>
        <a href="{{ url('/login') }}" class="inline-block bg-brand-blue text-white px-6 py-3 rounded-md font-semibold">Go to Sign in</a>
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
    <p class="text-sm text-gray-600 mb-4">Enter the WhatsApp number linked to your account. We will send a verification code.</p>
    <form method="POST" action="{{ url('/forgot-password') }}" class="space-y-4">
        @csrf
        @if($prefillLooksFull)
            <input type="hidden" name="phone" value="{{ $prefill }}">
            <div class="rounded-md bg-blue-50 border border-blue-100 px-3 py-2 text-sm text-blue-900">
                Reset code will be sent to <strong>{{ $prefill }}</strong>
            </div>
        @else
            <div>
                <label class="text-sm font-semibold text-gray-700">Phone Number</label>
                <div class="flex gap-2 mt-1">
                    <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 w-40 shrink-0">
                        @foreach(($countryCodes ?? []) as $code => $label)
                            <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="tel" name="phone" required value="{{ old('phone', $prefill) }}"
                           placeholder="Phone number"
                           class="flex-1 rounded-md border border-gray-200 px-3 py-2">
                </div>
            </div>
        @endif
        <button type="submit" class="w-full bg-brand-blue text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
            <i data-lucide="message-circle" class="w-4 h-4"></i> Send Verification Code
        </button>
    </form>
@endif

<p class="text-center mt-6 text-sm space-x-3">
    <a href="{{ url('/login') }}" class="text-brand-gold hover:underline inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Sign in
    </a>
    <a href="{{ url('/login?tab=signup') }}" class="text-brand-blue hover:underline font-semibold">Sign up</a>
</p>
@endsection
