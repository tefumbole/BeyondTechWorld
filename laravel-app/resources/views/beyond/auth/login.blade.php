@extends('beyond.auth.layout')

@section('title', 'Login')

@php
    $title = 'Login';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Beyond Enterprise</h1><p class="text-brand-blue text-sm mt-1">Secure Portal Login</p>';
@endphp

@section('auth_body')
<form method="POST" action="{{ url('/login') }}" class="space-y-5">
    @csrf
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Email or Username</label>
        <div class="relative">
            <i data-lucide="user" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
            <input type="text" name="identifier" value="{{ old('identifier', $prefill) }}" required
                   class="w-full pl-10 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                   placeholder="email or username">
        </div>
    </div>
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Password</label>
        <div class="relative">
            <i data-lucide="lock" class="absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
            <input type="password" name="password" required
                   value="{{ $guestPassword ? 'system' : '' }}"
                   class="w-full pl-10 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                   placeholder="••••••••">
        </div>
    </div>
    <div class="flex items-center justify-between text-sm">
        <a href="{{ url('/forgot-password') }}" class="text-brand-light hover:text-brand-blue font-medium">Forgot Password?</a>
    </div>
    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
        Login <i data-lucide="arrow-right" class="w-4 h-4"></i>
    </button>
    <p class="text-center text-sm text-gray-600">
        Staff admin? <a href="{{ url('/admin/login') }}" class="text-brand-gold font-semibold hover:underline">Admin login</a>
    </p>
</form>
@endsection
