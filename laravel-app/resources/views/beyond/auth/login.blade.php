@extends('beyond.auth.layout')

@section('title', 'Sign in')

@php
    $asCustomer = !empty($asCustomer);
@endphp

@section('auth_body')
<style>
    .entry-switch { display: flex; gap: 8px; margin: 0 0 14px; }
    .entry-switch label { flex: 1; border: 1px solid #d6deea; border-radius: 999px; padding: 8px 6px; font-size: 13px; font-weight: 700; color: #0b3f90; cursor: pointer; }
    .entry-switch label.on { background: #0b3f90; color: #fff; }
    .mai-login select, .mai-login input { width: 100%; margin-top: 4px; }
    .mai-country { color: #0b3f90; font-weight: 800; margin: 6px 0 10px; text-align: left; }
    .mai-login .bad { background: #fdecec; color: #7f1d1d; border-radius: 10px; padding: 8px 10px; text-align: left; }
</style>
@if(session('not_permitted'))
    <div class="auth-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">{{ session('not_permitted') }}</div>
@endif
@if($asCustomer)
    <div class="auth-alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;">
        Signing in as a <strong>customer</strong> account.
        <a href="{{ url('/login'.(request('redirect') ? '?redirect='.urlencode(request('redirect')) : '')) }}" style="font-weight:600;text-decoration:underline;">Use staff login</a>
    </div>
@endif

<div class="entry-switch" id="entry-switch">
    <label id="entry-dashboard" class="on"><input type="radio" name="entry" value="dashboard" checked> Dashboard</label>
    <label id="entry-mai"><input type="radio" name="entry" value="mai"> Whats MAI</label>
</div>

<form method="POST" action="{{ url('/login') }}" autocomplete="on" id="dashboard-login">
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
               placeholder="Name, username, phone or email" autocomplete="username">
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

<form method="POST" action="{{ route('whatsapp.mai.session') }}" id="mai-login" class="mai-login" hidden>
    @csrf
    <label for="mai-dial" style="display:block;text-align:left;font-weight:700;color:#0b3f90;">Country code</label>
    <select id="mai-dial" name="dial" class="auth-input" style="padding-left:16px;">
        @foreach(\App\Support\CountryDialCodes::all() as $label)
            @php
                $dialCode = preg_replace('/\D+/', '', $label);
                $countryName = trim(preg_replace('/\(\+\d+\)/', '', $label));
            @endphp
            <option value="{{ $dialCode }}" data-name="{{ $countryName }}" {{ $dialCode === '237' ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <p class="mai-country" id="mai-country">Cameroon</p>
    <input id="mai-phone" name="phone" class="auth-input" style="padding-left:16px;" inputmode="tel" autocomplete="tel" placeholder="Phone number" required>
    <button type="button" class="auth-btn" id="mai-send" style="margin-top:10px;">Send WhatsApp code</button>
    <p class="bad" id="mai-error" hidden></p>
    <input id="mai-code" name="code" class="auth-input" style="padding-left:16px;margin-top:10px;" inputmode="numeric" autocomplete="one-time-code" placeholder="Verification code" required>
    <button type="submit" class="auth-btn">Verify and continue</button>
</form>

<div class="auth-links">
    <a class="wa" href="{{ url('/phone-login') }}">
        Trouble with your account? Sign in with WhatsApp OTP
    </a>
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
    var dash = document.getElementById('dashboard-login');
    var mai = document.getElementById('mai-login');
    var dashLabel = document.getElementById('entry-dashboard');
    var maiLabel = document.getElementById('entry-mai');
    function showEntry(which) {
        var isMai = which === 'mai';
        if (dash) dash.hidden = isMai;
        if (mai) mai.hidden = !isMai;
        if (dashLabel) dashLabel.className = isMai ? '' : 'on';
        if (maiLabel) maiLabel.className = isMai ? 'on' : '';
        var picked = document.querySelector('input[name="entry"][value="' + which + '"]');
        if (picked) picked.checked = true;
    }
    var radios = document.querySelectorAll('input[name="entry"]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].onchange = function () { showEntry(this.value); };
    }
    if (window.location.search.indexOf('mai=1') !== -1) showEntry('mai');
    var dial = document.getElementById('mai-dial');
    var country = document.getElementById('mai-country');
    if (dial && country) {
        dial.onchange = function () {
            var option = dial.options[dial.selectedIndex];
            country.textContent = option ? option.getAttribute('data-name') : '';
        };
    }
    var send = document.getElementById('mai-send');
    if (send) {
        send.onclick = function () {
            var error = document.getElementById('mai-error');
            error.hidden = true;
            var token = mai.querySelector('input[name="_token"]').value;
            var code = dial ? dial.value : '237';
            var digits = (document.getElementById('mai-phone').value || '').replace(/\D+/g, '').replace(/^0+/, '');
            var data = new FormData();
            data.append('_token', token);
            data.append('phone', '+' + code + digits);
            data.append('dial', code);
            data.append('account_kind', 'personal');
            data.append('mai', '1');
            fetch('{{ route('cloud.register.otp') }}', { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json().then(function (json) {
                    if (!response.ok) {
                        error.hidden = false;
                        error.textContent = json.message || 'The code could not be sent.';
                        return;
                    }
                    document.getElementById('mai-code').focus();
                }); })
                .catch(function () { error.hidden = false; error.textContent = 'Try again.'; });
        };
    }
})();
</script>
@endpush
