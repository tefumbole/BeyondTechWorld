{{-- Live camera modal + helpers for internship document snaps --}}
<div id="apply-camera-modal" class="fixed inset-0 z-[80] hidden items-end sm:items-center justify-center bg-black/75 p-0 sm:p-4" aria-hidden="true">
    <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[95vh] flex flex-col">
        <div class="bg-brand-blue text-white px-4 py-3.5 flex items-center justify-between shrink-0">
            <h3 class="font-bold text-sm" id="apply-camera-title">Take photo</h3>
            <button type="button" id="apply-camera-close" class="text-white/90 hover:text-white text-sm font-semibold min-h-[2.5rem] px-2">Close</button>
        </div>
        <div class="p-3 sm:p-4 space-y-3 overflow-auto">
            <p class="text-xs text-gray-500 mb-0" id="apply-camera-hint">Allow camera access, then tap Capture.</p>
            <div class="relative bg-black rounded-xl overflow-hidden aspect-[3/4] sm:aspect-[4/3]">
                <video id="apply-camera-video" class="w-full h-full object-cover" playsinline autoplay muted></video>
                <canvas id="apply-camera-canvas" class="hidden"></canvas>
            </div>
            <div class="flex gap-2 pb-[env(safe-area-inset-bottom,0px)]">
                <button type="button" id="apply-camera-capture" class="flex-1 bg-brand-gold text-brand-blue font-extrabold py-3.5 rounded-xl min-h-[3rem]">
                    Capture photo
                </button>
                <button type="button" id="apply-camera-switch" class="px-4 py-3.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 min-h-[3rem]">
                    Flip
                </button>
            </div>
            <p class="text-xs text-red-600 hidden mb-0" id="apply-camera-error"></p>
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

    function markDocReady(wrap, statusEl, msg) {
        if (statusEl) statusEl.textContent = msg || 'Ready ✓';
        if (wrap) wrap.classList.add('has-file');
    }

    function setFileOnInput(input, file, previewImg, statusEl, wrap) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (e) {
            showError('Could not save the photo. Please try Attach file.');
            return;
        }
        markDocReady(wrap || (input && input.closest('[data-apply-doc]')), statusEl, 'Photo captured ✓');
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
            setFileOnInput(activeTarget, file, activePreview, activeStatus, activeTarget.closest('[data-apply-doc]'));
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
                markDocReady(wrap, status, 'File attached ✓');
                if (preview && file.type && file.type.indexOf('image/') === 0) {
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                } else if (preview) {
                    preview.classList.add('hidden');
                }
                target.dispatchEvent(new Event('change', { bubbles: true }));
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
