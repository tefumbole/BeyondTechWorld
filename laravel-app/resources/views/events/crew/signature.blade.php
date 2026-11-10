@if(!empty($contract) && ! in_array($contract->status, [\App\EventContract::STATUS_WORKER_SIGNED, \App\EventContract::STATUS_APPROVED], true))
    <div class="meta" style="max-height:220px;overflow:auto;">{!! $contract->rendered_body !!}</div>
    <p><strong>Sign the contract</strong></p>
    <canvas id="sig" width="460" height="140"></canvas>
    <input type="hidden" name="signature_image" id="signature_image">
    <button type="button" class="soft" id="sig-clear">Clear signature</button>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        (function () {
            var canvas = document.getElementById('sig');
            var form = canvas ? canvas.closest('form') : null;
            if (!canvas || !form || !window.SignaturePad) return;
            var pad = new SignaturePad(canvas);
            var clear = document.getElementById('sig-clear');
            if (clear) clear.addEventListener('click', function () { pad.clear(); });
            form.addEventListener('submit', function () {
                if (!pad.isEmpty()) document.getElementById('signature_image').value = pad.toDataURL('image/png');
            });
        })();
    </script>
@endif
