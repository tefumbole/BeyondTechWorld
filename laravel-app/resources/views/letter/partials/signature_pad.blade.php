@php
    $fieldName = $fieldName ?? 'signature_image';
    $submitLabel = $submitLabel ?? 'Submit';
    $padId = $padId ?? 'letter-signature-pad';
    $clearId = $clearId ?? 'clear-letter-signature';
    $hiddenId = $hiddenId ?? 'letter_signature_image';
    $formId = $formId ?? null;
    $signatureKind = $signatureKind ?? 'sign'; // edit | approve | sign
    $columnMap = ['edit' => 'stemp', 'approve' => 'approve', 'sign' => 'sign'];
    $accountColumn = $columnMap[$signatureKind] ?? 'sign';
    $accountFile = optional(Auth::user())->{$accountColumn} ?? null;
    $accountUrl = $accountFile ? url('public/images/user/'.$accountFile) : null;
    $hasAccount = !empty($accountUrl);
    $wrapId = ($padId).'-wrap';
    $toggleId = ($padId).'-toggle-new';
    $useAccountId = ($padId).'-use-account';
@endphp
<div class="letter-signature-block mb-3">
    <label class="font-weight-bold" style="color:#0b3f90;">Your Signature <strong>*</strong></label>

    @if($hasAccount)
        <div class="letter-account-signature mb-2" id="{{ $padId }}-account-preview">
            <p class="text-muted small mb-2">Using the signature saved on your account. A small date stamp is added automatically.</p>
            <div style="border:1px solid #d0d7e2;border-radius:12px;background:#fff;padding:12px;max-width:520px;display:inline-block;">
                <img src="{{ $accountUrl }}" alt="Saved signature" style="max-height:70px;width:auto;display:block;">
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="{{ $toggleId }}">
                    Optional: draw a new signature
                </button>
            </div>
        </div>
        <input type="hidden" name="use_account_signature" id="{{ $useAccountId }}" value="1">
    @else
        <p class="text-muted small mb-2">No signature on your account yet. Sign below — the date is added automatically under your signature.</p>
        <input type="hidden" name="use_account_signature" id="{{ $useAccountId }}" value="0">
    @endif

    <div id="{{ $wrapId }}" class="letter-new-signature-wrap" @if($hasAccount) style="display:none;" @endif>
        @if($hasAccount)
            <p class="text-muted small mb-2">Optional new signature. Leave blank to keep your saved account signature.</p>
        @endif
        <div class="signature-pad-wrap mb-2" style="border:2px dashed #0b3f90;border-radius:12px;background:#f8fbff;padding:12px;max-width:520px;">
            <canvas id="{{ $padId }}" width="500" height="140" style="width:100%;max-width:500px;background:transparent;border-radius:8px;touch-action:none;"></canvas>
        </div>
        <button type="button" class="btn btn-secondary btn-sm mr-2" id="{{ $clearId }}">Clear</button>
        @if($hasAccount)
            <button type="button" class="btn btn-link btn-sm" id="{{ $padId }}-use-saved">Use saved signature instead</button>
        @endif
    </div>

    <input type="hidden" name="{{ $fieldName }}" id="{{ $hiddenId }}" value="">
    <div class="mt-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="dripicons-checkmark"></i> {{ $submitLabel }}</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var hasAccount = @json($hasAccount);
    var canvas = document.getElementById(@json($padId));
    var wrap = document.getElementById(@json($wrapId));
    var useAccountInput = document.getElementById(@json($useAccountId));
    var hidden = document.getElementById(@json($hiddenId));
    var toggleBtn = document.getElementById(@json($toggleId));
    var useSavedBtn = document.getElementById(@json($padId.'-use-saved'));
    var signaturePad = null;

    function showPad(show) {
        if (!wrap) return;
        wrap.style.display = show ? '' : 'none';
        if (useAccountInput) {
            useAccountInput.value = (!show && hasAccount) ? '1' : '0';
        }
        if (!show && signaturePad) {
            signaturePad.clear();
            if (hidden) hidden.value = '';
        }
    }

    if (canvas && typeof SignaturePad !== 'undefined') {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(0, 0, 0, 0)',
            penColor: 'rgb(11, 63, 144)'
        });
        var clearBtn = document.getElementById(@json($clearId));
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                signaturePad.clear();
                if (hidden) hidden.value = '';
            });
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            showPad(true);
        });
    }
    if (useSavedBtn) {
        useSavedBtn.addEventListener('click', function () {
            showPad(false);
        });
    }

    var form = @json($formId) ? document.getElementById(@json($formId)) : (canvas ? canvas.closest('form') : null);
    if (form) {
        form.addEventListener('submit', function (e) {
            var drawing = signaturePad && !signaturePad.isEmpty() && wrap && wrap.style.display !== 'none';
            if (drawing) {
                if (hidden) hidden.value = signaturePad.toDataURL('image/png');
                if (useAccountInput) useAccountInput.value = '0';
                return true;
            }
            if (hasAccount) {
                if (hidden) hidden.value = '';
                if (useAccountInput) useAccountInput.value = '1';
                return true;
            }
            e.preventDefault();
            alert('Please provide your signature before continuing.');
            showPad(true);
            return false;
        });
    }
})();
</script>
