@extends('beyond.auth.layout')

@section('title', 'OTP Verification')

@php
    $title = 'Verify OTP';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">WhatsApp Verification</h1><p class="text-brand-blue text-sm mt-1">Enter the 6-digit code sent to '.$maskedPhone.'</p>';
@endphp

@section('auth_body')
<form method="POST" action="{{ url('/otp-verification') }}" class="space-y-4">
    @csrf
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700">Verification Code</label>
        <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus
               class="w-full text-center text-3xl tracking-[0.5em] rounded-lg border-2 border-brand-gold/60 px-3 py-3 focus:border-brand-blue outline-none"
               placeholder="000000">
    </div>
    <p class="text-center text-sm font-semibold text-red-500">
        Code expires in <span id="otp-expiry">10:00</span>
    </p>
    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md">Verify OTP</button>
</form>
<form method="POST" action="{{ url('/otp-verification/resend') }}" class="mt-4" id="resend-form">
    @csrf
    <button type="submit" id="resend-btn"
            class="w-full text-center text-sm text-gray-500 hover:text-brand-blue disabled:opacity-60">
        <span id="resend-label">Resend OTP</span>
    </button>
</form>
<form method="POST" action="{{ url('/logout') }}" class="mt-2">
    @csrf
    <button type="submit" class="w-full text-sm text-gray-500 hover:text-brand-blue flex items-center justify-center gap-1">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Login
    </button>
</form>

@push('scripts')
<script>
(function () {
    // Expiry countdown (10 minutes)
    var expiry = 10 * 60;
    var expEl = document.getElementById('otp-expiry');
    var expTimer = setInterval(function () {
        expiry--;
        if (expiry <= 0) { clearInterval(expTimer); if (expEl) expEl.textContent = 'expired'; return; }
        var m = Math.floor(expiry / 60), s = expiry % 60;
        if (expEl) expEl.textContent = m + ':' + (s < 10 ? '0' + s : s);
    }, 1000);

    // Resend cooldown (60 seconds)
    var cooldown = 60;
    var btn = document.getElementById('resend-btn');
    var label = document.getElementById('resend-label');
    function tick() {
        if (cooldown <= 0) { btn.disabled = false; label.textContent = 'Resend OTP'; return; }
        btn.disabled = true;
        label.textContent = 'Resend OTP in ' + cooldown + 's';
        cooldown--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
@endpush
@endsection
