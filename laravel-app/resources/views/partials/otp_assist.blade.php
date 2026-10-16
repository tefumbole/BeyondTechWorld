@php
    $otpAssistId = $otpInputId ?? 'otp-code';
    $otpAssistCode = '';
    $agent = (string) request()->userAgent();
    if (preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $agent)) {
        $otpAssistCode = preg_replace('/\D+/', '', (string) session('wa_otp_autofill', ''));
    }
@endphp
<script>
(function () {
    var input = document.getElementById(@json($otpAssistId));
    if (!input) return;
    function apply(value) {
        var digits = String(value || '').replace(/\D/g, '').slice(0, 6);
        if (digits.length !== 6) return;
        input.value = digits;
        try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
    }
    apply(@json($otpAssistCode));
    if (window.OTPCredential && navigator.credentials && navigator.credentials.get) {
        var ac = new AbortController();
        var timer = setTimeout(function () { ac.abort(); }, 60000);
        navigator.credentials.get({
            otp: { transport: ['sms'] },
            signal: ac.signal
        }).then(function (otp) {
            clearTimeout(timer);
            if (otp && otp.code) apply(otp.code);
        }).catch(function () {
            clearTimeout(timer);
        });
    }
})();
</script>
