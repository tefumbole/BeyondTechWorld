(function (window) {
    function digits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    window.attachPhoneNameLookup = function (opts) {
        var phoneEl = document.querySelector(opts.phone);
        var nameEl = document.querySelector(opts.name);
        if (!phoneEl || !nameEl || !opts.url) {
            return;
        }
        var codeEl = opts.code ? document.querySelector(opts.code) : null;
        var addressEl = opts.address ? document.querySelector(opts.address) : null;
        var emailEl = opts.email ? document.querySelector(opts.email) : null;
        var statusEl = opts.status ? document.querySelector(opts.status) : null;
        var timer = null;

        function setStatus(text, ok) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.style.display = text ? 'block' : 'none';
            statusEl.style.color = ok ? '#166534' : '#64748b';
        }

        function lookup() {
            var phone = phoneEl.value;
            if (digits(phone).length < 8) {
                setStatus('');
                return;
            }
            var url = opts.url + '?phone=' + encodeURIComponent(phone);
            if (codeEl && codeEl.value) {
                url += '&country_code=' + encodeURIComponent(codeEl.value);
            }
            fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data || !data.found) {
                    setStatus('Number not on file — type your name yourself.', false);
                    return;
                }
                var n = data.original_name || data.name || data.system_name;
                if (n) {
                    nameEl.value = n;
                }
                if (addressEl && (data.original_address || data.address || data.system_address)) {
                    addressEl.value = data.original_address || data.address || data.system_address;
                }
                if (emailEl && data.email && !emailEl.value) {
                    emailEl.value = data.email;
                }
                setStatus(n
                    ? 'We found this number. Name filled in — you can still edit it.'
                    : 'We found this number in the system.', true);
            }).catch(function () {});
        }

        phoneEl.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(lookup, 450);
        });
        phoneEl.addEventListener('blur', lookup);
        if (codeEl) {
            codeEl.addEventListener('change', lookup);
        }
    };
})(window);
