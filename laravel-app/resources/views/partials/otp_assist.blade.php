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
    var sent = false;
    function maybeSubmit() {
        if (input.getAttribute('data-autosubmit') !== '1' || sent) return;
        var need = parseInt(input.getAttribute('maxlength') || '6', 10) || 6;
        var digits = String(input.value || '').replace(/\D/g, '');
        if (digits.length < need) return;
        var form = input.form;
        if (!form) return;
        sent = true;
        input.value = digits.slice(0, need);
        setTimeout(function () {
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        }, 40);
    }
    function apply(value) {
        var digits = String(value || '').replace(/\D/g, '').slice(0, 6);
        if (digits.length !== 6) return;
        input.value = digits;
        try { input.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
        maybeSubmit();
    }
    input.addEventListener('input', function () {
        maybeSubmit();
    });
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
