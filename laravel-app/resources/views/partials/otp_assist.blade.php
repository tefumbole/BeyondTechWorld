<script>
(function () {
    var input = document.getElementById(@json($otpInputId ?? 'otp-code'));
    if (!input) return;
    var sent = false;
    var form = input.form;
    if (form && form.getAttribute('data-otp-bound') !== '1') {
        form.setAttribute('data-otp-bound', '1');
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-otp-sent') === '1') {
                event.preventDefault();
                return;
            }
            form.setAttribute('data-otp-sent', '1');
            var button = form.querySelector('[type="submit"]');
            if (button) button.disabled = true;
        });
    }
    function maybeSubmit() {
        if (input.getAttribute('data-autosubmit') !== '1' || sent) return;
        var need = parseInt(input.getAttribute('maxlength') || '6', 10) || 6;
        var digits = String(input.value || '').replace(/\D/g, '');
        if (digits.length < need) return;
        if (!form || form.getAttribute('data-otp-sent') === '1') return;
        sent = true;
        input.value = digits.slice(0, need);
        setTimeout(function () {
            if (form.getAttribute('data-otp-sent') === '1') return;
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
        this.value = String(this.value || '').replace(/\D/g, '').slice(0, 6);
        maybeSubmit();
    });
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
