@extends('beyond.auth.layout')

@section('title', 'Sign in')

@php
    $asCustomer = !empty($asCustomer);
@endphp

@section('auth_body')
@if($asCustomer)
    <div class="auth-alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;">
        Signing in as a <strong>customer</strong> account.
        <a href="{{ url('/login'.(request('redirect') ? '?redirect='.urlencode(request('redirect')) : '')) }}" style="font-weight:600;text-decoration:underline;">Use staff login</a>
    </div>
@endif

<form method="POST" action="{{ url('/login') }}" autocomplete="on">
    @csrf
    @php
        $loginRedirect = request('redirect') ?: session('beyond_intended');
    @endphp
    @if($loginRedirect)
        <input type="hidden" name="redirect" value="{{ $loginRedirect }}">
    @endif
    @if($asCustomer)
        <input type="hidden" name="as" value="customer">
    @endif

    <div class="auth-field">
        <svg class="auth-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
        </svg>
        <input type="text" name="identifier" class="auth-input"
               value="{{ old('identifier', $prefill) }}" required
               placeholder="Name, email or phone" autocomplete="username">
    </div>

    <div class="auth-field">
        <svg class="auth-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <input id="login-password" type="password" name="password" class="auth-input" required
               value="{{ $guestPassword ? 'system' : '' }}"
               placeholder="Password" autocomplete="current-password">
        <button type="button" class="auth-toggle" id="toggle-password" aria-label="Show password">
            <svg id="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.77 21.77 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
        </button>
    </div>

    <button type="submit" class="auth-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Sign In
    </button>
</form>

<div class="auth-links">
    <a class="wa" href="{{ url('/forgot-password') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true">
            <path d="M20.5 3.5A11 11 0 0 0 2.1 17.2L1 23l5.9-1.1A11 11 0 1 0 20.5 3.5zm-8.5 17a9 9 0 0 1-4.6-1.3l-.3-.2-3.5.7.7-3.4-.2-.3A9 9 0 1 1 12 20.5zm5.2-6.7c-.3-.1-1.6-.8-1.9-.9s-.4-.1-.6.1-.7.9-.8 1-.3.2-.6.1a7.4 7.4 0 0 1-2.2-1.4 8.2 8.2 0 0 1-1.5-1.9c-.2-.3 0-.4.1-.6l.4-.5.1-.3c0-.1 0-.3-.1-.4s-.6-1.4-.8-1.9-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3a2.3 2.3 0 0 0-.7 1.7 4 4 0 0 0 .8 2.1c.2.3 1.7 2.7 4.2 3.7 2.5 1 2.5.7 3 .6a2.5 2.5 0 0 0 1.6-1.2c.2-.4.2-.8.1-.9s-.2-.1-.5-.2z"/>
        </svg>
        Forgot password? Reset via WhatsApp
    </a>
    <a href="{{ url('/') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Homepage
    </a>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('login-password');
    var btn = document.getElementById('toggle-password');
    if (!input || !btn) return;
    var open = document.getElementById('eye-open');
    var off = document.getElementById('eye-off');
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (open) open.style.display = show ? 'none' : 'block';
        if (off) off.style.display = show ? 'block' : 'none';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
})();
</script>
@endpush
