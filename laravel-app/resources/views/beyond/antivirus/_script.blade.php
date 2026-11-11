@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var regionBox = document.getElementById('region-box');
    if (regionBox) {
        var regionInput = document.getElementById('region-search');
        var regionValue = document.getElementById('region-value');
        var regionList = document.getElementById('region-list');
        var regionItems = Array.prototype.slice.call(regionList.querySelectorAll('[data-region]'));
        function paintRegions(query) {
            var q = (query || '').toLowerCase();
            regionItems.forEach(function (item) {
                item.hidden = q !== '' && item.getAttribute('data-region').toLowerCase().indexOf(q) === -1;
            });
        }
        function chooseRegion(item) {
            regionValue.value = item.getAttribute('data-region');
            regionInput.value = regionValue.value;
            regionList.hidden = true;
        }
        regionInput.addEventListener('input', function () {
            regionValue.value = '';
            regionList.hidden = false;
            paintRegions(regionInput.value);
        });
        regionInput.addEventListener('focus', function () {
            regionList.hidden = false;
            paintRegions(regionInput.value === regionValue.value ? '' : regionInput.value);
        });
        regionInput.addEventListener('keydown', function (event) {
            var visible = regionItems.filter(function (item) { return !item.hidden; });
            var current = -1;
            visible.forEach(function (item, index) {
                if (item.classList.contains('is-active')) current = index;
            });
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                current = event.key === 'ArrowDown' ? Math.min(visible.length - 1, current + 1) : Math.max(0, current - 1);
                visible.forEach(function (item) { item.classList.remove('is-active'); });
                if (visible[current]) visible[current].classList.add('is-active');
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (visible[current] || visible[0]) chooseRegion(visible[current] || visible[0]);
            }
        });
        regionItems.forEach(function (item) {
            item.addEventListener('click', function () { chooseRegion(item); });
        });
        if (regionValue.value) regionInput.value = regionValue.value;
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    function phoneDigits(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (digits.charAt(0) === '0') digits = digits.replace(/^0+/, '');
        return digits;
    }
    function phoneReady(value) {
        var digits = phoneDigits(value);
        return (digits.length === 9 && digits.charAt(0) === '6') || (digits.indexOf('237') === 0 && digits.length === 12);
    }
    function approverReady(value) {
        var digits = phoneDigits(value);
        if (digits.length === 9 && digits.charAt(0) === '6') return true;
        return digits.length >= 10 && digits.length <= 15;
    }
    function lookupPhone(url, value) {
        var body = new FormData();
        body.append('_token', csrf());
        body.append('phone', value);
        return fetch(url, { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); });
    }
    function bindRoleSearch(search, hidden, menu, otherWrap, roles) {
        if (!search || !hidden || !menu) return;
        function paint(query) {
            var q = (query || '').toLowerCase();
            menu.innerHTML = '';
            Object.keys(roles).forEach(function (key) {
                var label = roles[key];
                if (q && label.toLowerCase().indexOf(q) === -1) return;
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;
                if (hidden.value === key) button.className = 'is-on';
                button.addEventListener('click', function () {
                    hidden.value = key;
                    search.value = label;
                    menu.hidden = true;
                    if (otherWrap) otherWrap.classList.toggle('is-hidden', key !== 'other');
                });
                menu.appendChild(button);
            });
            menu.hidden = menu.children.length === 0;
        }
        search.addEventListener('focus', function () {
            var exact = hidden.value && roles[hidden.value] && search.value === roles[hidden.value];
            paint(exact ? '' : search.value);
        });
        search.addEventListener('input', function () {
            hidden.value = '';
            if (otherWrap) otherWrap.classList.add('is-hidden');
            paint(search.value);
        });
        document.addEventListener('click', function (event) {
            if (!search.parentElement.contains(event.target)) menu.hidden = true;
        });
    }
    var requesterRolesNode = document.getElementById('av-requester-roles');
    if (requesterRolesNode) {
        bindRoleSearch(
            document.getElementById('role-search'),
            document.getElementById('role-value'),
            document.getElementById('role-menu'),
            document.getElementById('role-other-wrap'),
            JSON.parse(requesterRolesNode.textContent || '{}')
        );
    }
    var identityPhone = document.getElementById('av-phone');
    var identityName = document.getElementById('av-name');
    var identityWrap = document.getElementById('av-name-wrap');
    if (identityPhone && identityName) {
        var identityTimer;
        identityPhone.addEventListener('input', function () {
            clearTimeout(identityTimer);
            if (!phoneReady(identityPhone.value)) return;
            identityTimer = setTimeout(function () {
                lookupPhone(identityPhone.getAttribute('data-lookup-url'), identityPhone.value).then(function (data) {
                    if (identityWrap) identityWrap.classList.remove('is-hidden');
                    if (!identityName.value || identityName.getAttribute('data-filled') === '1') {
                        identityName.value = data && data.name ? data.name : '';
                        identityName.setAttribute('data-filled', '1');
                    }
                }).catch(function () {
                    if (identityWrap) identityWrap.classList.remove('is-hidden');
                });
            }, 350);
        });
        identityName.addEventListener('input', function () { identityName.setAttribute('data-filled', '0'); });
    }

    var approverForm = document.getElementById('av-approver-form');
    var approverRolesNode = document.getElementById('av-approver-roles');
    var approverRoles = approverRolesNode ? JSON.parse(approverRolesNode.textContent || '{}') : {};
    function bindApprover(card) {
        var phone = card.querySelector('.av-approver-phone');
        var name = card.querySelector('[name$="_manual_name"]');
        var search = card.querySelector('.av-role-search');
        var hidden = card.querySelector('input[type="hidden"]');
        var menu = card.querySelector('.av-menu');
        var other = card.querySelector('.av-role-other');
        bindRoleSearch(search, hidden, menu, other, approverRoles);
        if (!phone || !approverForm) return;
        var timer;
        var lookupSeq = 0;
        function resolveApprover() {
            clearTimeout(timer);
            if (!approverReady(phone.value)) return;
            var seq = ++lookupSeq;
            var typed = phone.value;
            timer = setTimeout(function () {
                lookupPhone(approverForm.getAttribute('data-lookup-url'), typed).then(function (data) {
                    if (seq !== lookupSeq || !name) return;
                    if (data && data.name && (!name.value || name.getAttribute('data-filled') === '1')) {
                        name.value = data.name;
                        name.setAttribute('data-filled', '1');
                    }
                }).catch(function () {});
            }, 350);
        }
        phone.addEventListener('input', resolveApprover);
        phone.addEventListener('change', resolveApprover);
        phone.addEventListener('blur', resolveApprover);
        phone.addEventListener('paste', function () { setTimeout(resolveApprover, 0); });
        if (approverReady(phone.value)) resolveApprover();
        if (name) name.addEventListener('input', function () { name.setAttribute('data-filled', '0'); });
    }
    document.querySelectorAll('.av-person').forEach(bindApprover);
    var addApprover = document.getElementById('add-approver');
    var approverList = document.getElementById('approver-list');
    if (addApprover && approverList) {
        function nextSlot() {
            return approverList.querySelectorAll('.av-person').length + 1;
        }
        if (nextSlot() > 2) addApprover.hidden = true;
        addApprover.addEventListener('click', function () {
            var slot = nextSlot();
            if (slot > 2) return;
            var card = document.createElement('div');
            card.className = 'av-person';
            card.innerHTML = ''
                + '<label class="av-field">Phone number</label>'
                + '<input class="av-input av-approver-phone" name="approver' + slot + '_phone" type="tel" inputmode="tel" placeholder="+250 794 006 160">'
                + '<label class="av-field">Name</label>'
                + '<input class="av-input" name="approver' + slot + '_manual_name" data-wa-phone="off" placeholder="Full name">'
                + '<label class="av-field">Role</label>'
                + '<div class="av-combo"><input class="av-input av-role-search" type="search" data-wa-phone="off" autocomplete="off" placeholder="Search the role"><input type="hidden" name="approver' + slot + '_role" value=""><div class="av-menu" hidden></div></div>'
                + '<div class="is-hidden av-role-other"><label class="av-field">Describe the role</label><input class="av-input" name="approver' + slot + '_role_other" data-wa-phone="off"></div>';
            approverList.appendChild(card);
            bindApprover(card);
            var field = card.querySelector('.av-approver-phone');
            if (field) field.focus();
            if (nextSlot() > 2) addApprover.hidden = true;
        });
    }

    var padCanvas = document.getElementById('av-pad');
    var pad;
    if (padCanvas && window.SignaturePad) {
        function fitPad() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var rect = padCanvas.getBoundingClientRect();
            padCanvas.width = rect.width * ratio;
            padCanvas.height = rect.height * ratio;
            padCanvas.getContext('2d').scale(ratio, ratio);
        }
        fitPad();
        pad = new SignaturePad(padCanvas, { minWidth: 0.8, maxWidth: 2.4, penColor: '#0b3f90' });
        var clearButton = document.getElementById('av-clear');
        if (clearButton) clearButton.addEventListener('click', function () { pad.clear(); });
    }

    function bindSign(form) {
        if (!form || !pad) return;
        form.addEventListener('submit', function (event) {
            if (pad.isEmpty()) {
                event.preventDefault();
                var note = document.getElementById('av-sign-error');
                if (note) {
                    note.hidden = false;
                    note.textContent = 'Draw your signature before continuing.';
                }
                return;
            }
            var hidden = form.querySelector('input[name="signature"]');
            if (hidden) hidden.value = pad.toDataURL('image/png');
        });
    }
    bindSign(document.getElementById('av-sign-form'));
    bindSign(document.getElementById('av-capture-form'));

    var openCamera = document.getElementById('av-open-camera');
    var modal = document.getElementById('av-camera');
    var video = document.getElementById('av-video');
    var shot = document.getElementById('av-shot');
    var fileInput = document.getElementById('av-file');
    var preview = document.getElementById('av-preview');
    var stream;
    function stopCamera() {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        stream = null;
    }
    if (openCamera && modal && video) {
        openCamera.addEventListener('click', function () {
            modal.hidden = false;
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                .then(function (media) { stream = media; video.srcObject = media; return video.play(); })
                .catch(function () {
                    modal.hidden = true;
                    var fallback = document.getElementById('av-fallback');
                    if (fallback) fallback.click();
                });
        });
        var closeCamera = document.getElementById('av-close-camera');
        if (closeCamera) closeCamera.addEventListener('click', function () { modal.hidden = true; stopCamera(); });
        if (shot) shot.addEventListener('click', function () {
            var canvas = document.createElement('canvas');
            var width = video.videoWidth || 640;
            var height = video.videoHeight || 480;
            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext('2d');
            context.translate(width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, width, height);
            canvas.toBlob(function (blob) {
                if (!blob || !fileInput) return;
                var file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                fileInput.files = transfer.files;
                if (preview) {
                    preview.src = URL.createObjectURL(blob);
                    preview.hidden = false;
                }
                modal.hidden = true;
                stopCamera();
                var selfieForm = document.getElementById('av-selfie-form');
                if (selfieForm) selfieForm.submit();
            }, 'image/jpeg', 0.9);
        });
    }
    var fallback = document.getElementById('av-fallback');
    if (fallback && fileInput) {
        fallback.addEventListener('change', function () {
            if (!fallback.files || !fallback.files[0]) return;
            var transfer = new DataTransfer();
            transfer.items.add(fallback.files[0]);
            fileInput.files = transfer.files;
            if (preview) {
                preview.src = URL.createObjectURL(fallback.files[0]);
                preview.hidden = false;
            }
            var selfieForm = document.getElementById('av-selfie-form');
            if (selfieForm) selfieForm.submit();
        });
    }

    function startPoll(node, focus) {
        if (!node || node.getAttribute('data-polling') === '1') return;
        var url = node.getAttribute('data-url');
        if (!url) return;
        var watch = focus || '';
        var seenSelfie = node.getAttribute('data-selfie') === '1';
        var seenSign = node.getAttribute('data-signature') === '1';
        node.setAttribute('data-polling', '1');
        setInterval(function () {
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (watch === 'selfie' && data.selfie && !seenSelfie) window.location.reload();
                    if (watch === 'sign' && data.signature && !seenSign) window.location.reload();
                })
                .catch(function () {});
        }, 4000);
    }
    function showQr(name) {
        document.querySelectorAll('.av-qr-slot').forEach(function (slot) { slot.classList.remove('is-open'); });
        var chosen = document.querySelector('[data-qr-slot="' + name + '"]');
        if (chosen) chosen.classList.add('is-open');
    }
    function note(name, text) {
        var slot = document.querySelector('[data-qr-slot="' + name + '"]');
        if (!slot) return;
        slot.innerHTML = '';
        var line = document.createElement('p');
        line.className = 'av-note';
        line.textContent = text;
        slot.appendChild(line);
        showQr(name);
    }
    document.querySelectorAll('[data-qr]').forEach(function (button) {
        button.addEventListener('click', function () {
            var name = button.getAttribute('data-qr');
            var slot = document.querySelector('[data-qr-slot="' + name + '"]');
            if (slot && slot.querySelector('svg')) {
                showQr(name);
                return;
            }
            var form = document.getElementById('av-handoff-form');
            if (!form) return;
            var token = form.querySelector('input[name="_token"]');
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: '_token=' + encodeURIComponent(token ? token.value : '') + '&focus=' + encodeURIComponent(name)
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                var data = result.data || {};
                if (!result.ok || !data.qr || data.qr.indexOf('<svg') === -1) {
                    note(name, data.message || 'The QR code could not be created. Try again.');
                    return;
                }
                document.querySelectorAll('[data-qr-slot]').forEach(function (item) {
                    item.innerHTML = '<div class="av-qr">' + data.qr + '</div>';
                });
                showQr(name);
                var poll = document.getElementById('av-poll');
                if (poll) {
                    if (data.status_url) poll.setAttribute('data-url', data.status_url);
                    startPoll(poll, name);
                }
            }).catch(function () {
                note(name, 'The QR code could not be created. Try again.');
            });
        });
    });
    var openSlot = document.querySelector('.av-qr-slot.is-open');
    if (openSlot && openSlot.querySelector('svg')) startPoll(document.getElementById('av-poll'), openSlot.getAttribute('data-qr-slot'));
})();
</script>
@endpush
