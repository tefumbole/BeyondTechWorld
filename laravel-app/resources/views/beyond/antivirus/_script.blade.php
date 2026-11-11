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

    document.querySelectorAll('[data-lookup]').forEach(function (button) {
        button.addEventListener('click', function () {
            var slot = button.getAttribute('data-lookup');
            var phone = document.getElementById('approver' + slot + '-phone');
            var box = document.getElementById('approver' + slot + '-found');
            var body = new FormData();
            body.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            body.append('phone', phone ? phone.value : '');
            box.textContent = 'Looking up the registered name…';
            fetch(button.getAttribute('data-url'), { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok && result.data.name) {
                        box.textContent = 'Registered name: ' + result.data.name + '. Leave the name box empty to use it, or type a different full name.';
                    } else {
                        box.textContent = 'No registered name was found. Type the full name. They will confirm it on their link.';
                    }
                })
                .catch(function () { box.textContent = 'The lookup did not answer. Type the full name.'; });
        });
    });

    var addApprover = document.getElementById('add-approver');
    var secondApprover = document.getElementById('approver-2');
    if (addApprover && secondApprover) {
        addApprover.addEventListener('click', function () {
            secondApprover.hidden = false;
            addApprover.hidden = true;
        });
    }

    var padCanvas = document.getElementById('av-pad');
    var pad;
    var cleared = null;
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
        var redoButton = document.getElementById('av-redo');
        if (clearButton) clearButton.addEventListener('click', function () {
            if (!pad.isEmpty()) cleared = pad.toData();
            pad.clear();
        });
        if (redoButton) redoButton.addEventListener('click', function () {
            if (cleared) {
                pad.fromData(cleared);
                cleared = null;
                return;
            }
            var data = pad.toData();
            if (!data.length) return;
            data.pop();
            pad.fromData(data);
        });
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
                if (preview) preview.src = URL.createObjectURL(blob);
                modal.hidden = true;
                stopCamera();
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
            if (preview) preview.src = URL.createObjectURL(fallback.files[0]);
        });
    }

    var poll = document.getElementById('av-poll');
    if (poll) {
        setInterval(function () {
            fetch(poll.getAttribute('data-url'), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) { if (data.ready) window.location.reload(); });
        }, 4000);
    }
})();
</script>
@endpush
