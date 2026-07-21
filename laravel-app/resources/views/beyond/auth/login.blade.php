@extends('beyond.auth.layout')

@section('title', 'Sign in')

@php
    $title = 'Sign in';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Beyond Enterprise</h1><p class="text-brand-blue text-sm mt-1">Sign in to continue</p>';
    $activeTab = $tab ?? 'signin';
    $asCustomer = !empty($asCustomer);
    $signinQuery = [];
    if (request('redirect')) { $signinQuery['redirect'] = request('redirect'); }
    if ($asCustomer) { $signinQuery['as'] = 'customer'; }
    $signinUrl = url('/login'.(count($signinQuery) ? '?'.http_build_query($signinQuery) : ''));
    $signupUrl = url('/login?tab=signup'.(request('redirect') ? '&redirect='.urlencode(request('redirect')) : ''));
@endphp

@section('auth_body')
<div class="flex rounded-lg bg-gray-100 p-1 mb-6" role="tablist">
    <a href="{{ $signinUrl }}"
       class="flex-1 text-center py-2 rounded-md text-sm font-bold transition {{ $activeTab === 'signin' ? 'bg-white text-brand-blue shadow' : 'text-gray-600 hover:text-brand-blue' }}">
        Sign in
    </a>
    <a href="{{ $signupUrl }}"
       class="flex-1 text-center py-2 rounded-md text-sm font-bold transition {{ $activeTab === 'signup' ? 'bg-white text-brand-blue shadow' : 'text-gray-600 hover:text-brand-blue' }}">
        Sign up
    </a>
</div>

@if($activeTab === 'signup')
<form method="POST" action="{{ url('/signup') }}" class="space-y-4">
    @csrf
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Full Name</label>
        <input type="text" name="full_name" value="{{ old('full_name') }}" required
               class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
               placeholder="Your full name">
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Email (optional)</label>
        <input type="email" name="email" value="{{ old('email') }}"
               class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
               placeholder="you@example.com">
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">WhatsApp number</label>
        <div class="flex gap-2">
            <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 w-40 shrink-0">
                @foreach(($countryCodes ?? []) as $code => $label)
                    <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
            <input type="tel" name="phone" value="{{ old('phone') }}" required
                   class="flex-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                   placeholder="Phone number">
        </div>
        <p class="text-xs text-gray-500">Any country — we verify with WhatsApp OTP.</p>
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Password</label>
        <input type="password" name="password" required minlength="8"
               class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
               placeholder="Min. 8 characters">
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Confirm Password</label>
        <input type="password" name="password_confirmation" required minlength="8"
               class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
    </div>
    <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md flex items-center justify-center gap-2">
        Create account <i data-lucide="arrow-right" class="w-4 h-4"></i>
    </button>
    <p class="text-center text-sm text-gray-600">
        Already registered?
        <a href="{{ url('/login'.(request('redirect') ? '?redirect='.urlencode(request('redirect')) : '')) }}" class="text-brand-blue font-semibold hover:underline">Sign in</a>
    </p>
</form>
@else
@if($asCustomer)
    <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
        Signing in as a <strong>customer</strong> account.
        <a href="{{ url('/login'.(request('redirect') ? '?redirect='.urlencode(request('redirect')) : '')) }}" class="font-semibold underline">Use staff login instead</a>
    </div>
@endif
<form method="POST" action="{{ url('/login') }}" class="space-y-5">
    @csrf
    @if($asCustomer)
        <input type="hidden" name="as" value="customer">
    @endif
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Email or Username</label>
        <div class="relative">
            <i data-lucide="user" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
            <input type="text" name="identifier" value="{{ old('identifier', $prefill) }}" required
                   class="w-full pl-10 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                   placeholder="email or username" autocomplete="username">
        </div>
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Password</label>
        <div class="relative">
            <i data-lucide="lock" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
            <input type="password" name="password" required
                   value="{{ $guestPassword ? 'system' : '' }}"
                   class="w-full pl-10 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                   placeholder="••••••••" autocomplete="current-password">
        </div>
    </div>
    <div class="flex items-center justify-between text-sm">
        <a href="{{ url('/forgot-password') }}" class="text-brand-light hover:text-brand-blue font-medium">Forgot Password?</a>
        <a href="{{ $signupUrl }}" class="text-brand-gold font-semibold hover:underline">Sign up</a>
    </div>
    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
        Sign in <i data-lucide="arrow-right" class="w-4 h-4"></i>
    </button>
    @unless($asCustomer)
    <p class="text-center text-xs text-gray-500">
        Same email as a customer account?
        <a href="{{ url('/login?as=customer'.(request('redirect') ? '&redirect='.urlencode(request('redirect')) : '')) }}" class="text-brand-gold font-semibold hover:underline">Sign in as customer</a>
    </p>
    @endunless
</form>
@endif
@endsection
