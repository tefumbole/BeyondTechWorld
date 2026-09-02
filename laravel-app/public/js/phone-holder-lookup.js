(function () {
    var URL = window.PEOPLE_PHONE_LOOKUP;
    if (!URL) return;

    var timers = new WeakMap();
    var last = new WeakMap();

    function digits(v) {
        return String(v || '').replace(/\D/g, '');
    }

    function usable(v) {
        var s = String(v || '').trim();
        if (!s) return '';
        var u = s.toUpperCase();
        if (u === 'N/A' || u === 'NAN') return '';
        return s;
    }

    function isPhoneInput(el) {
        if (!el || el.nodeName !== 'INPUT') return false;
        if (el.classList && el.classList.contains('js-phone-holder')) return true;
        var id = el.id || '';
        if (id === 'an-new-phone' || id === 'qc_customer_phone' || id === 'ct-qc-phone'
            || id === 'jb-new-sup-phone' || id === 'quick_customer_phone') return true;
        if (el.classList && el.classList.contains('tm-new-phone')) return true;
        return false;
    }

    function rootOf(input) {
        return input.closest('form')
            || input.closest('.modal')
            || input.closest('.an-add-recipient-panel')
            || input.closest('.tm-add-panel')
            || input.closest('#jb-add-supervisor-panel')
            || input.parentElement;
    }

    function findField(root, input, kind) {
        var attr = input.getAttribute(kind === 'name' ? 'data-holder-name' : 'data-holder-address');
        if (attr === '__none__') return null;
        if (attr) {
            if (attr.charAt(0) === '#' || attr.charAt(0) === '.') {
                return document.querySelector(attr);
            }
            var byName = root.querySelector('[name="' + attr + '"]');
            if (byName) return byName;
            var byId = document.getElementById(attr);
            if (byId) return byId;
        }
        if (kind === 'name') {
            return root.querySelector('[name="customer_name"]')
                || root.querySelector('#qc_customer_name')
                || root.querySelector('#an-new-name')
                || root.querySelector('#ct-qc-name')
                || root.querySelector('#jb-new-sup-name')
                || root.querySelector('#quick_customer_name')
                || root.querySelector('.tm-new-name')
                || root.querySelector('[name="name"]');
        }
        return root.querySelector('[name="address"]')
            || root.querySelector('#qc_customer_address')
            || root.querySelector('#an-new-address')
            || root.querySelector('#ct-qc-address')
            || root.querySelector('#jb-new-sup-address')
            || root.querySelector('#quick_customer_address')
            || root.querySelector('.tm-new-address');
    }

    function setVal(el, value) {
        if (!el || value == null) return;
        var next = String(value);
        if (el.value === next) return;
        el.value = next;
        if (typeof Event === 'function') {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function pickerHost(input) {
        var id = 'ph-pick-' + (input.id || input.name || 'x');
        var existing = document.getElementById(id);
        if (existing) return existing;
        var box = document.createElement('div');
        box.id = id;
        box.className = 'ph-holder-pick';
        box.style.cssText = 'margin:6px 0 10px;font-size:12px;line-height:1.35;';
        input.parentNode.insertBefore(box, input.nextSibling);
        return box;
    }

    function renderPicker(input, data) {
        var host = pickerHost(input);
        var sys = usable(data.system_name);
        var orig = usable(data.original_name);
        var sysA = usable(data.system_address);
        var origA = usable(data.original_address);
        var bits = [];
        if (!sys && !orig && !sysA && !origA) {
            host.innerHTML = '<span style="color:#64748b;">No name or address found for this number. Type them if needed.</span>';
            return;
        }
        if (sys || orig) {
            bits.push('<div style="color:#64748b;margin-bottom:4px;">Select the name for this record (you can still edit it):</div>');
            if (sys) {
                bits.push('<button type="button" class="btn btn-sm btn-outline-primary ph-pick-name" data-which="system" style="margin:0 6px 6px 0;">System: ' + escapeHtml(sys) + '</button>');
            }
            if (orig && orig !== sys) {
                bits.push('<button type="button" class="btn btn-sm btn-outline-secondary ph-pick-name" data-which="register" style="margin:0 6px 6px 0;">Register: ' + escapeHtml(orig) + '</button>');
            } else if (orig && !sys) {
                bits.push('<button type="button" class="btn btn-sm btn-outline-secondary ph-pick-name" data-which="register" style="margin:0 6px 6px 0;">Register: ' + escapeHtml(orig) + '</button>');
            }
        }
        var addrNote = origA && origA !== sysA ? origA : (sysA || origA);
        if (addrNote) {
            bits.push('<div style="color:#64748b;margin-top:2px;">Address: ' + escapeHtml(addrNote) + '</div>');
        }
        host.innerHTML = bits.join('');
        host.onclick = function (ev) {
            var btn = ev.target.closest('.ph-pick-name');
            if (!btn) return;
            var which = btn.getAttribute('data-which');
            applyChoice(input, data, which);
            var all = host.querySelectorAll('.ph-pick-name');
            for (var i = 0; i < all.length; i++) {
                all[i].classList.remove('btn-primary');
                all[i].classList.add(all[i].getAttribute('data-which') === 'register' ? 'btn-outline-secondary' : 'btn-outline-primary');
            }
            btn.classList.remove('btn-outline-primary', 'btn-outline-secondary');
            btn.classList.add('btn-primary');
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function applyChoice(input, data, which) {
        var root = rootOf(input);
        var nameEl = findField(root, input, 'name');
        var addrEl = findField(root, input, 'address');
        if (which === 'register') {
            setVal(nameEl, usable(data.original_name) || usable(data.system_name));
            setVal(addrEl, usable(data.original_address) || usable(data.system_address));
            return;
        }
        setVal(nameEl, usable(data.system_name) || usable(data.original_name));
        setVal(addrEl, usable(data.system_address) || usable(data.original_address));
    }

    function lookup(input) {
        var phone = String(input.value || '').trim();
        if (digits(phone).length < 8) {
            var host = document.getElementById('ph-pick-' + (input.id || input.name || 'x'));
            if (host) host.innerHTML = '';
            return;
        }
        if (last.get(input) === phone) return;
        last.set(input, phone);
        var url = URL + (URL.indexOf('?') >= 0 ? '&' : '?') + 'phone=' + encodeURIComponent(phone);
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.ok === false) return;
                applyChoice(input, data, data.found ? 'system' : 'register');
                renderPicker(input, data);
            })
            .catch(function () {});
    }

    function schedule(input) {
        var t = timers.get(input);
        if (t) clearTimeout(t);
        timers.set(input, setTimeout(function () { lookup(input); }, 450));
    }

    document.addEventListener('input', function (e) {
        if (isPhoneInput(e.target)) schedule(e.target);
    });
    document.addEventListener('change', function (e) {
        if (isPhoneInput(e.target)) lookup(e.target);
    });
    document.addEventListener('blur', function (e) {
        if (isPhoneInput(e.target)) lookup(e.target);
    }, true);
})();
