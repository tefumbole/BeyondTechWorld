{{-- Global WhatsApp phone auto-format. Paste/blur → +237681239720 (no spaces).
     Local fields (next to country_code, or data-wa-phone="local") keep national digits
     and set the country select when a full international number is pasted. --}}
<script>
(function () {
    if (window.__waPhoneInit) return;
    window.__waPhoneInit = true;

    var DEFAULT_CC = @json(\App\Support\WhatsAppPhone::countryCode());

    var NAME_RE = /(phone|whatsapp|telephone|mobile|cellphone|cell_phone|wa_number|mtn_number|full_phone)/i;
    var SKIP_RE = /(password|otp|code|search|filter|fax|extension|ext\b)/i;

    var COUNTRY_CODES = [
        '237','234','233','254','255','256','250','243','225','221','27','33','44','1','49','32','31','39','20','91','86','971'
    ].sort(function (a, b) { return b.length - a.length; });

    function digitsOnly(v) {
        return String(v || '').replace(/\D/g, '');
    }

    function looksInternational(digits) {
        if (!digits || digits.length < 11) return false;
        for (var i = 0; i < COUNTRY_CODES.length; i++) {
            var cc = COUNTRY_CODES[i];
            if (digits.indexOf(cc) === 0) {
                var local = digits.slice(cc.length);
                if (local.length >= 7 && local.length <= 12) return true;
            }
        }
        return digits.length >= 11 && digits.length <= 15;
    }

    function normalizeFull(raw) {
        var digits = digitsOnly(raw);
        if (!digits) return '';
        if (digits.charAt(0) === '0' && digits.length >= 9) {
            digits = digits.replace(/^0+/, '');
        }
        if (!digits) return '';
        if (looksInternational(digits)) {
            // de-dupe default country double prefix
            var dbl = DEFAULT_CC + DEFAULT_CC;
            while (digits.indexOf(dbl) === 0) {
                digits = digits.slice(DEFAULT_CC.length);
            }
            return '+' + digits;
        }
        if (digits.length >= 8 && digits.length <= 10) {
            return '+' + DEFAULT_CC + digits;
        }
        // Partial / unknown — still strip junk so paste cleans spaces
        return digits.length ? '+' + digits : '';
    }

    function splitInternational(fullPlus) {
        var digits = digitsOnly(fullPlus);
        for (var i = 0; i < COUNTRY_CODES.length; i++) {
            var cc = COUNTRY_CODES[i];
            if (digits.indexOf(cc) === 0) {
                var local = digits.slice(cc.length);
                if (local.length >= 7 && local.length <= 12) {
                    return { cc: cc, local: local };
                }
            }
        }
        if (digits.length >= 8 && digits.length <= 10) {
            return { cc: DEFAULT_CC, local: digits };
        }
        return { cc: DEFAULT_CC, local: digits };
    }

    function findCountrySelect(input) {
        var form = input.form || input.closest('form');
        if (!form) return null;
        return form.querySelector('select[name="country_code"], select[name="countryCode"], select[name="phone_country"], select[name="dial_code"]');
    }

    function isLocalField(input) {
        if ((input.getAttribute('data-wa-phone') || '').toLowerCase() === 'local') return true;
        if ((input.getAttribute('data-wa-phone') || '').toLowerCase() === 'full') return false;
        var name = (input.getAttribute('name') || '').toLowerCase();
        if (name === 'whatsapp_number' || name === 'phone_number' || name === 'phone' || name === 'mobile') {
            return !!findCountrySelect(input);
        }
        return false;
    }

    function isPhoneInput(el) {
        if (!el || el.disabled || el.readOnly) return false;
        if (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') return false;
        if (el.type === 'hidden' || el.type === 'password' || el.type === 'checkbox' || el.type === 'radio' || el.type === 'file') return false;
        if (el.getAttribute('data-wa-phone') === 'off') return false;
        if (el.classList.contains('wa-phone-off')) return false;

        var name = el.getAttribute('name') || '';
        var id = el.getAttribute('id') || '';
        var placeholder = el.getAttribute('placeholder') || '';
        var autocomplete = (el.getAttribute('autocomplete') || '').toLowerCase();
        var type = (el.type || '').toLowerCase();

        if (SKIP_RE.test(name) || SKIP_RE.test(id)) return false;
        if (el.getAttribute('data-wa-phone')) return true;
        if (el.classList.contains('wa-phone')) return true;
        if (type === 'tel') return true;
        if (autocomplete === 'tel' || autocomplete === 'tel-national' || autocomplete === 'tel-local') return true;
        if (NAME_RE.test(name) || NAME_RE.test(id)) return true;
        if (/whatsapp|phone|mobile/i.test(placeholder) && type === 'text') return true;
        return false;
    }

    function setCountrySelect(select, cc) {
        if (!select || !cc) return;
        var want = String(cc);
        var wantPlus = '+' + want;
        var opts = select.options;
        for (var i = 0; i < opts.length; i++) {
            var v = String(opts[i].value || '');
            if (v === want || v === wantPlus || digitsOnly(v) === want) {
                select.value = opts[i].value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }
    }

    function applyValue(input, next) {
        if (next === null || typeof next === 'undefined') return;
        if (String(input.value) === String(next)) return;
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function formatInput(input) {
        var raw = input.value;
        if (!raw || !String(raw).trim()) return;

        if (isLocalField(input)) {
            var full = normalizeFull(raw);
            var parts = splitInternational(full || raw);
            var select = findCountrySelect(input);
            if (select) setCountrySelect(select, parts.cc);
            applyValue(input, parts.local);
            return;
        }

        var formatted = normalizeFull(raw);
        if (formatted) applyValue(input, formatted);
    }

    function onPaste(e) {
        var input = e.target;
        if (!isPhoneInput(input)) return;
        var text = (e.clipboardData || window.clipboardData);
        text = text ? text.getData('text') : '';
        if (!text) return;
        e.preventDefault();
        // Replace current selection / whole value with pasted text, then normalize
        try {
            var start = input.selectionStart;
            var end = input.selectionEnd;
            var cur = String(input.value || '');
            if (typeof start === 'number' && typeof end === 'number') {
                input.value = cur.slice(0, start) + text + cur.slice(end);
            } else {
                input.value = text;
            }
        } catch (err) {
            input.value = text;
        }
        formatInput(input);
    }

    function onBlur(e) {
        if (isPhoneInput(e.target)) formatInput(e.target);
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('input, textarea').forEach(function (el) {
            if (!isPhoneInput(el) || el.__waPhoneBound) return;
            el.__waPhoneBound = true;
            // Normalize existing prefilled values once
            if (el.value && String(el.value).replace(/\D/g, '').length >= 8) {
                formatInput(el);
            }
        });
    }

    document.addEventListener('paste', onPaste, true);
    document.addEventListener('blur', onBlur, true);
    document.addEventListener('change', function (e) {
        if (isPhoneInput(e.target)) formatInput(e.target);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { enhanceAll(document); });
    } else {
        enhanceAll(document);
    }

    // Bootstrap modals / dynamic forms
    document.addEventListener('shown.bs.modal', function (e) { enhanceAll(e.target || document); });
    if (window.MutationObserver) {
        var mo = new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                m.addedNodes && m.addedNodes.forEach(function (n) {
                    if (n.nodeType === 1) enhanceAll(n);
                });
            });
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
    }

    window.BeyondWhatsAppPhone = {
        normalize: normalizeFull,
        defaultCountryCode: DEFAULT_CC,
        formatInput: formatInput
    };
})();
</script>
