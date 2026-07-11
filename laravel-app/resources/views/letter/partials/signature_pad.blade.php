@php
    $fieldName = $fieldName ?? 'signature_image';
    $submitLabel = $submitLabel ?? 'Submit';
    $padId = $padId ?? 'letter-signature-pad';
    $clearId = $clearId ?? 'clear-letter-signature';
    $hiddenId = $hiddenId ?? 'letter_signature_image';
    $formId = $formId ?? null;
@endphp
<div class="letter-signature-block mb-3">
    <label class="font-weight-bold" style="color:#0b3f90;">Your Signature <strong>*</strong></label>
    <p class="text-muted small mb-2">Sign below. The date will be added automatically in small text under your signature.</p>
    <div class="signature-pad-wrap mb-2" style="border:2px dashed #0b3f90;border-radius:12px;background:#f8fbff;padding:12px;max-width:520px;">
        <canvas id="{{ $padId }}" width="500" height="140" style="width:100%;max-width:500px;background:transparent;border-radius:8px;touch-action:none;"></canvas>
    </div>
    <input type="hidden" name="{{ $fieldName }}" id="{{ $hiddenId }}">
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary btn-sm mr-2" id="{{ $clearId }}">Clear</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="dripicons-checkmark"></i> {{ $submitLabel }}</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById(@json($padId));
    if (!canvas || typeof SignaturePad === 'undefined') {
        return;
    }
    var signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(0, 0, 0, 0)',
        penColor: 'rgb(11, 63, 144)'
    });
    var clearBtn = document.getElementById(@json($clearId));
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            signaturePad.clear();
        });
    }
    var form = @json($formId) ? document.getElementById(@json($formId)) : canvas.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (signaturePad.isEmpty()) {
                e.preventDefault();
                alert('Please provide your signature before continuing.');
                return false;
            }
            document.getElementById(@json($hiddenId)).value = signaturePad.toDataURL('image/png');
        });
    }
})();
</script>
