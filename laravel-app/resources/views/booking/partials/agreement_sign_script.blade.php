{{-- Shared client signing UX for rental / accommodation / software / studio agreements --}}
@include('booking.partials.id_card_compress_script')

<script>
(function () {
    var agreementRead = false;
    var signatureSet = false;
    var idSet = false;
    var submitting = false;

    var form = document.getElementById('sign-form');
    var readField = document.getElementById('agreement_read_confirmed');
    var acceptBox = document.getElementById('agreement_accepted');
    var openSigBtn = document.getElementById('open-signature-modal');
    var submitBtn = document.getElementById('submit-agreement');
    var idInput = document.getElementById('id_card_file');
    var idFront = document.getElementById('id_card_front');
    var idBack = document.getElementById('id_card_back');
    var snapBtn = document.getElementById('snap-id-card-btn');
    var idName = document.getElementById('id-file-name');
    var sigField = document.getElementById('signature_image');
    var agreementBox = document.getElementById('agreement-content');
    var idMode = null; // 'attach' | 'snap'
    var frontReady = false;
    var backReady = false;
    var snapStep = 'front';

    if (!form || !submitBtn) {
        return;
    }

    function clearFileInput(input) {
        if (!input) return;
        try { input.value = ''; } catch (e) {}
    }

    function refreshIdStatus() {
        if (!idName) return;
        if (idMode === 'attach' && idInput && idInput.files && idInput.files.length) {
            idSet = true;
            idName.textContent = 'Attached: ' + idInput.files[0].name;
            return;
        }
        if (idMode === 'snap') {
            var parts = [];
            if (frontReady) parts.push('Front ✓');
            else parts.push('Front pending');
            if (backReady) parts.push('Back ✓');
            else parts.push('Back pending');
            idSet = !!(frontReady && backReady);
            idName.textContent = 'Snapped ID — ' + parts.join(' · ');
            return;
        }
        idSet = false;
        idName.textContent = '';
    }

    function hasIdReady() {
        if (idMode === 'attach') {
            return !!(idInput && idInput.files && idInput.files.length);
        }
        if (idMode === 'snap') {
            return !!(frontReady && backReady
                && idFront && idFront.files && idFront.files.length
                && idBack && idBack.files && idBack.files.length);
        }
        return false;
    }

    function markAgreementRead() {
        agreementRead = true;
        if (readField) {
            readField.value = '1';
        }
        if (acceptBox) {
            acceptBox.disabled = false;
        }
        if (openSigBtn) {
            openSigBtn.disabled = false;
        }
        updateSubmitState();
    }

    function checkAgreementRead() {
        var doc = document.documentElement;
        var scrollTop = window.pageYOffset || doc.scrollTop || document.body.scrollTop || 0;
        var viewport = window.innerHeight || doc.clientHeight || 0;
        var fullHeight = Math.max(
            doc.scrollHeight || 0,
            document.body.scrollHeight || 0,
            doc.offsetHeight || 0,
            document.body.offsetHeight || 0
        );
        // Fixed footer + mobile browser chrome often block "true" bottom; use a generous threshold.
        if (scrollTop + viewport >= fullHeight - 160 || fullHeight <= viewport + 40) {
            markAgreementRead();
        }
    }

    window.addEventListener('scroll', checkAgreementRead, { passive: true });
    window.addEventListener('resize', checkAgreementRead);
    setTimeout(checkAgreementRead, 200);
    setTimeout(checkAgreementRead, 800);

    // Mark read when the last agreement block is visible (more reliable than scroll math in WebViews).
    if (agreementBox && 'IntersectionObserver' in window) {
        var cards = agreementBox.querySelectorAll('.card');
        var lastCard = cards.length ? cards[cards.length - 1] : agreementBox;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    markAgreementRead();
                }
            });
        }, { threshold: 0.2 });
        observer.observe(lastCard);
    }

    if (acceptBox) {
        acceptBox.addEventListener('change', updateSubmitState);
        // Tapping the label on some mobiles does not fire change reliably until blur.
        acceptBox.addEventListener('click', function () {
            setTimeout(updateSubmitState, 0);
        });
    }

    function compressInto(input, onReady) {
        if (!input) return;
        if (typeof window.bindCompressedIdCardInput === 'function') {
            window.bindCompressedIdCardInput(input, input, function (name, ok) {
                if (ok === false) {
                    clearFileInput(input);
                    refreshIdStatus();
                    updateSubmitState();
                    return;
                }
                onReady(name);
            });
            return;
        }
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                onReady(input.files[0].name);
            }
        });
    }

    if (idInput) {
        compressInto(idInput, function (name) {
            idMode = 'attach';
            frontReady = false;
            backReady = false;
            clearFileInput(idFront);
            clearFileInput(idBack);
            // Keep only attach field named for server
            if (idFront) idFront.removeAttribute('name');
            if (idBack) idBack.removeAttribute('name');
            idInput.setAttribute('name', 'id_card');
            refreshIdStatus();
            updateSubmitState();
        });
    }

    function prepareSnapNames() {
        if (idInput) {
            idInput.removeAttribute('name');
            clearFileInput(idInput);
        }
        if (idFront) idFront.setAttribute('name', 'id_card_front');
        if (idBack) idBack.setAttribute('name', 'id_card_back');
    }

    if (idFront) {
        compressInto(idFront, function () {
            idMode = 'snap';
            prepareSnapNames();
            frontReady = !!(idFront.files && idFront.files.length);
            refreshIdStatus();
            updateSubmitState();
            if (frontReady && !backReady) {
                snapStep = 'back';
                setTimeout(function () {
                    alert('Front captured. Now snap the BACK of the ID card.');
                    if (idBack) idBack.click();
                }, 250);
            }
        });
    }

    if (idBack) {
        compressInto(idBack, function () {
            idMode = 'snap';
            prepareSnapNames();
            backReady = !!(idBack.files && idBack.files.length);
            refreshIdStatus();
            updateSubmitState();
            if (backReady && !frontReady) {
                snapStep = 'front';
                setTimeout(function () {
                    alert('Back captured. Now snap the FRONT of the ID card.');
                    if (idFront) idFront.click();
                }, 250);
            }
        });
    }

    if (snapBtn) {
        snapBtn.addEventListener('click', function () {
            idMode = 'snap';
            prepareSnapNames();
            frontReady = !!(idFront && idFront.files && idFront.files.length);
            backReady = !!(idBack && idBack.files && idBack.files.length);
            refreshIdStatus();
            if (!frontReady) {
                snapStep = 'front';
                alert('Step 1 of 2: Snap the FRONT of the ID card.');
                if (idFront) idFront.click();
            } else if (!backReady) {
                snapStep = 'back';
                alert('Step 2 of 2: Snap the BACK of the ID card.');
                if (idBack) idBack.click();
            } else if (confirm('Front and back are already captured. Retake from the front?')) {
                frontReady = false;
                backReady = false;
                clearFileInput(idFront);
                clearFileInput(idBack);
                refreshIdStatus();
                snapStep = 'front';
                if (idFront) idFront.click();
            }
        });
    }

    var modal = document.getElementById('signature-modal');
    var canvas = document.getElementById('signature-pad');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var drawing = false;
    var hasDrawn = false;

    function resizeCanvas() {
        if (!canvas || !ctx) {
            return;
        }
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#10213d';
    }

    function getPos(e) {
        var rect = canvas.getBoundingClientRect();
        var clientX = e.clientX || (e.touches && e.touches[0] && e.touches[0].clientX);
        var clientY = e.clientY || (e.touches && e.touches[0] && e.touches[0].clientY);
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function startDraw(e) {
        drawing = true;
        hasDrawn = true;
        ctx.beginPath();
        var p = getPos(e);
        ctx.moveTo(p.x, p.y);
        e.preventDefault();
    }
    function draw(e) {
        if (!drawing) return;
        var p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        e.preventDefault();
    }
    function endDraw() {
        drawing = false;
    }

    if (canvas && ctx) {
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', endDraw);
    }

    if (openSigBtn) {
        openSigBtn.addEventListener('click', function () {
            markAgreementRead();
            if (modal) {
                modal.classList.add('open');
            }
            setTimeout(resizeCanvas, 50);
        });
    }

    var closeSig = document.getElementById('close-signature-modal');
    if (closeSig) {
        closeSig.addEventListener('click', function () {
            if (modal) {
                modal.classList.remove('open');
            }
        });
    }

    var clearSig = document.getElementById('clear-signature');
    if (clearSig) {
        clearSig.addEventListener('click', function () {
            if (!canvas || !ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            resizeCanvas();
            hasDrawn = false;
        });
    }

    var confirmSig = document.getElementById('confirm-signature');
    if (confirmSig) {
        confirmSig.addEventListener('click', function () {
            if (!hasDrawn) {
                alert('Please draw your signature first.');
                return;
            }
            markAgreementRead();
            // Composite onto a white background: JPEG has no transparency, so exporting the
            // transparent signature pad directly turns the whole image into a black box.
            var dataUrl;
            try {
                var flat = document.createElement('canvas');
                flat.width = canvas.width;
                flat.height = canvas.height;
                var fctx = flat.getContext('2d');
                fctx.fillStyle = '#ffffff';
                fctx.fillRect(0, 0, flat.width, flat.height);
                fctx.drawImage(canvas, 0, 0);
                dataUrl = flat.toDataURL('image/jpeg', 0.8);
            } catch (err) {
                dataUrl = canvas.toDataURL('image/png');
            }
            if (sigField) {
                sigField.value = dataUrl;
            }
            var preview = document.getElementById('signature-preview');
            if (preview) {
                preview.src = dataUrl;
                preview.style.display = 'block';
            }
            signatureSet = true;
            if (acceptBox) {
                acceptBox.disabled = false;
                acceptBox.checked = true;
            }
            if (modal) {
                modal.classList.remove('open');
            }
            updateSubmitState();
        });
    }

    function missingRequirements() {
        var missing = [];
        if (!agreementRead || (readField && readField.value !== '1')) {
            missing.push('scroll through / read the agreement');
        }
        if (!acceptBox || !acceptBox.checked) {
            missing.push('tick the acceptance checkbox');
        }
        if (!signatureSet || !sigField || !sigField.value) {
            missing.push('add your signature');
        }
        if (!hasIdReady()) {
            if (idMode === 'snap') {
                if (!frontReady) missing.push('snap the FRONT of your ID card');
                if (!backReady) missing.push('snap the BACK of your ID card');
            } else {
                missing.push('attach one ID file, or snap front + back');
            }
        }
        return missing;
    }

    function updateSubmitState() {
        var ready = missingRequirements().length === 0;
        submitBtn.classList.toggle('is-ready', ready);
        submitBtn.setAttribute('aria-disabled', ready ? 'false' : 'true');
        if (ready) {
            submitBtn.style.opacity = '1';
        } else {
            submitBtn.style.opacity = '0.85';
        }
    }

    function submitAgreement(e) {
        if (e) {
            e.preventDefault();
        }
        if (submitting) {
            return false;
        }

        var missing = missingRequirements();
        if (missing.length) {
            alert('Before submitting, please:\n• ' + missing.join('\n• '));
            if (acceptBox && !acceptBox.checked && !acceptBox.disabled) {
                acceptBox.focus();
                try {
                    acceptBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (err) {}
            }
            return false;
        }

        submitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting…';

        // Prefer requestSubmit (runs HTML5 validation); fall back for older WebViews.
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } catch (err) {
            form.submit();
        }
        return false;
    }

    // Always use JS submit — footer button is outside the form; form="" is unreliable in WhatsApp WebView.
    submitBtn.setAttribute('type', 'button');
    submitBtn.removeAttribute('form');
    submitBtn.removeAttribute('disabled');
    submitBtn.addEventListener('click', submitAgreement);

    form.addEventListener('submit', function () {
        if (!submitting) {
            var missing = missingRequirements();
            if (missing.length) {
                alert('Before submitting, please:\n• ' + missing.join('\n• '));
                return false;
            }
            submitting = true;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }
    });

    // Surface server validation errors (often above the fold after a failed post).
    var alertEl = document.querySelector('.alert-danger');
    if (alertEl) {
        try {
            alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (err) {}
        setTimeout(function () {
            alert(alertEl.textContent.replace(/\s+/g, ' ').trim());
        }, 300);
    }

    updateSubmitState();
})();
</script>
