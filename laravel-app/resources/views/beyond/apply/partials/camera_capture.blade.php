{{-- Live camera modal + helpers for internship document snaps --}}
<div id="apply-camera-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/70 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="bg-brand-blue text-white px-4 py-3 flex items-center justify-between">
            <h3 class="font-bold text-sm" id="apply-camera-title">Take photo</h3>
            <button type="button" id="apply-camera-close" class="text-white/90 hover:text-white text-sm font-semibold">Close</button>
        </div>
        <div class="p-4 space-y-3">
            <p class="text-xs text-gray-500" id="apply-camera-hint">Allow camera access, then tap Capture.</p>
            <div class="relative bg-black rounded-lg overflow-hidden aspect-[4/3]">
                <video id="apply-camera-video" class="w-full h-full object-cover" playsinline autoplay muted></video>
                <canvas id="apply-camera-canvas" class="hidden"></canvas>
            </div>
            <div class="flex gap-2">
                <button type="button" id="apply-camera-capture" class="flex-1 bg-brand-gold text-brand-blue font-bold py-2.5 rounded-md">
                    Capture photo
                </button>
                <button type="button" id="apply-camera-switch" class="px-3 py-2.5 rounded-md border border-gray-200 text-sm font-semibold text-gray-700">
                    Flip
                </button>
            </div>
            <p class="text-xs text-red-600 hidden" id="apply-camera-error"></p>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('apply-camera-modal');
    var video = document.getElementById('apply-camera-video');
    var canvas = document.getElementById('apply-camera-canvas');
    var titleEl = document.getElementById('apply-camera-title');
    var hintEl = document.getElementById('apply-camera-hint');
    var errEl = document.getElementById('apply-camera-error');
    var stream = null;
    var facingMode = 'environment';
    var activeTarget = null;
    var activePreview = null;
    var activeStatus = null;

    function showError(msg) {
        if (!errEl) return;
        errEl.textContent = msg || '';
        errEl.classList.toggle('hidden', !msg);
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    function closeModal() {
        stopStream();
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        activeTarget = null;
        activePreview = null;
        activeStatus = null;
        showError('');
    }

    function startStream() {
        showError('');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Camera is not supported in this browser. Use Attach file instead.');
            return;
        }
        stopStream();
        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        }).then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play();
        }).catch(function (err) {
            showError('Could not open camera. Allow camera permission, or use Attach file. (' + (err && err.message ? err.message : 'denied') + ')');
        });
    }

    function openCamera(opts) {
        activeTarget = opts.targetInput;
        activePreview = opts.previewImg;
        activeStatus = opts.statusEl;
        facingMode = opts.facingMode || 'environment';
        if (titleEl) titleEl.textContent = opts.title || 'Take photo';
        if (hintEl) hintEl.textContent = opts.hint || 'Allow camera access, then tap Capture.';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        startStream();
    }

    function setFileOnInput(input, file, previewImg, statusEl) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (e) {
            showError('Could not save the photo. Please try Attach file.');
            return;
        }
        if (statusEl) statusEl.textContent = 'Photo captured ✓';
        if (previewImg) {
            previewImg.src = URL.createObjectURL(file);
            previewImg.classList.remove('hidden');
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    document.getElementById('apply-camera-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.getElementById('apply-camera-switch').addEventListener('click', function () {
        facingMode = facingMode === 'user' ? 'environment' : 'user';
        startStream();
    });
    document.getElementById('apply-camera-capture').addEventListener('click', function () {
        if (!stream || !activeTarget) return;
        var w = video.videoWidth || 1280;
        var h = video.videoHeight || 720;
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        canvas.toBlob(function (blob) {
            if (!blob) {
                showError('Could not capture photo. Try again.');
                return;
            }
            var name = (activeTarget.name || 'photo') + '_' + Date.now() + '.jpg';
            var file = new File([blob], name, { type: 'image/jpeg' });
            setFileOnInput(activeTarget, file, activePreview, activeStatus);
            closeModal();
        }, 'image/jpeg', 0.85);
    });

    window.BeyondApplyCamera = {
        open: openCamera,
        close: closeModal
    };

    // Wire Attach / Snap buttons
    document.querySelectorAll('[data-apply-doc]').forEach(function (wrap) {
        var target = wrap.querySelector('input[data-doc-target]');
        var attach = wrap.querySelector('input[data-doc-attach]');
        var preview = wrap.querySelector('[data-doc-preview]');
        var status = wrap.querySelector('[data-doc-status]');
        var snapBtn = wrap.querySelector('[data-doc-snap]');
        var facing = wrap.getAttribute('data-facing') || 'environment';
        var title = wrap.getAttribute('data-title') || 'Take photo';

        if (attach && target) {
            attach.addEventListener('change', function () {
                if (!attach.files || !attach.files[0]) return;
                var file = attach.files[0];
                try {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    target.files = dt.files;
                } catch (e) {
                    // Fallback: clone via name if DataTransfer unsupported — rare
                    target.files = attach.files;
                }
                if (status) status.textContent = 'File attached ✓';
                if (preview && file.type && file.type.indexOf('image/') === 0) {
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                } else if (preview) {
                    preview.classList.add('hidden');
                }
            });
        }

        if (snapBtn && target) {
            snapBtn.addEventListener('click', function () {
                openCamera({
                    targetInput: target,
                    previewImg: preview,
                    statusEl: status,
                    facingMode: facing,
                    title: title,
                    hint: facing === 'user'
                        ? 'Use the front camera for your selfie, then tap Capture.'
                        : 'Point at the document, then tap Capture.'
                });
            });
        }
    });
})();
</script>
