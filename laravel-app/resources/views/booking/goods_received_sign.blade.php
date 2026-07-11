<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Goods Received - {{ $general_setting->site_title ?? 'Sign' }}</title>
    <style>
        body { margin: 0; font-family: Nunito, sans-serif; background: #f4f7fb; color: #1f2a44; }
        .wrap { max-width: 900px; margin: 0 auto; padding: 24px 16px 100px; }
        .card { background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px; box-shadow: 0 8px 24px rgba(11,63,144,.08); }
        h1 { margin: 0 0 8px; color: #0b3f90; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e3e9f4; padding: 10px 8px; text-align: left; font-size: 14px; }
        th { color: #0b3f90; }
        .btn { border: 0; border-radius: 10px; padding: 12px 18px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0b3f90; color: #fff; }
        .btn-accent { background: #c6ab47; color: #10213d; }
        .btn-outline { background: #fff; border: 2px solid #0b3f90; color: #0b3f90; }
        .footer-bar { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #e3e9f4; padding: 14px 16px; }
        .footer-inner { max-width: 900px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
        .modal { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; padding: 18px; }
        #signature-pad { width: 100%; height: 180px; border: 2px dashed #c6ab47; border-radius: 10px; touch-action: none; }
        .checkbox-row { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    @if(session()->has('message'))
        <div class="card" style="background:#d4edda;">{{ session()->get('message') }}</div>
    @endif

    @php $isDelivered = ($role ?? 'received') === 'delivered'; @endphp
    <div class="card">
        <h1>{{ $isDelivered ? 'Goods Delivery Confirmation' : 'Goods Received Confirmation' }}</h1>
        <p>Booking: <strong>{{ $booking->reference_no }}</strong> &nbsp;|&nbsp; Delivery Note: <strong>{{ $receipt->reference_no }}</strong></p>
        <p>Customer: <strong>{{ optional($booking->customer)->name }}</strong></p>
        @if($isDelivered)
            <p style="color:#0b3f90;"><strong>You are confirming that you delivered the equipment listed below.</strong></p>
        @endif
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#0b3f90;">Equipment Delivered</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Equipment</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td>{{ $item['start'] ? date('d/m/Y H:i', strtotime($item['start'])) : '-' }}</td>
                        <td>{{ $item['end'] ? date('d/m/Y H:i', strtotime($item['end'])) : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="margin-top:12px;color:#6c757d;"><em>No pricing is shown on this delivery confirmation.</em></p>
    </div>

    <form method="POST" action="{{ route('goods.received.sign', $receipt->signature_token) }}" id="sign-form">
        @csrf
        <input type="hidden" name="signature_image" id="signature_image">
        <input type="hidden" name="role" value="{{ $isDelivered ? 'delivered' : 'received' }}">
        <div class="card">
            @if($isDelivered)
                <div style="margin-bottom:14px;">
                    <label for="signer_name" style="display:block;font-weight:700;margin-bottom:6px;">Your Name (person delivering)</label>
                    <input type="text" name="signer_name" id="signer_name" maxlength="191" style="width:100%;padding:10px 12px;border:1px solid #d7e0ef;border-radius:10px;" placeholder="e.g. John from Beyond Company">
                </div>
            @endif
            <div class="checkbox-row">
                <input type="checkbox" name="receipt_confirmed" id="receipt_confirmed" value="1">
                <label for="receipt_confirmed">
                    @if($isDelivered)
                        I confirm that I have delivered all equipment listed above.
                    @else
                        I confirm that I have received all equipment listed above in good order.
                    @endif
                </label>
            </div>
            <div style="margin-top:16px;">
                <button type="button" class="btn btn-outline" id="open-signature-modal">Draw Signature</button>
                <span id="signature-status" style="margin-left:10px;color:#6c757d;">Signature required</span>
            </div>
        </div>
    </form>
</div>

<div class="footer-bar">
    <div class="footer-inner">
        <div>{{ $isDelivered ? 'Sign to confirm goods delivered' : 'Sign to confirm goods received' }}</div>
        <button type="submit" form="sign-form" class="btn btn-accent" id="submit-receipt" disabled>Submit Confirmation</button>
    </div>
</div>

<div class="modal-backdrop" id="signature-modal">
    <div class="modal">
        <h3 style="margin-top:0;">Sign Goods Received</h3>
        <canvas id="signature-pad"></canvas>
        <div style="display:flex;gap:10px;margin-top:12px;justify-content:flex-end;">
            <button type="button" class="btn btn-outline" id="close-signature-modal">Cancel</button>
            <button type="button" class="btn btn-outline" id="clear-signature">Clear</button>
            <button type="button" class="btn btn-primary" id="confirm-signature">Confirm</button>
        </div>
    </div>
</div>

<script>
(function () {
    var confirmed = document.getElementById('receipt_confirmed');
    var submitBtn = document.getElementById('submit-receipt');
    var signatureField = document.getElementById('signature_image');
    var signatureStatus = document.getElementById('signature-status');
    var signatureSet = false;

    function updateSubmit() {
        submitBtn.disabled = !(confirmed.checked && signatureSet);
    }
    confirmed.addEventListener('change', updateSubmit);

    var modal = document.getElementById('signature-modal');
    var canvas = document.getElementById('signature-pad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasDrawn = false;

    function resizeCanvas() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#10213d';
    }

    function getPos(e) {
        var rect = canvas.getBoundingClientRect();
        var clientX = e.clientX || (e.touches && e.touches[0].clientX);
        var clientY = e.clientY || (e.touches && e.touches[0].clientY);
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function startDraw(e) { drawing = true; hasDrawn = true; ctx.beginPath(); var p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function draw(e) { if (!drawing) return; var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function endDraw() { drawing = false; }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', endDraw);

    document.getElementById('open-signature-modal').addEventListener('click', function () {
        modal.style.display = 'flex';
        resizeCanvas();
    });
    document.getElementById('close-signature-modal').addEventListener('click', function () {
        modal.style.display = 'none';
    });
    document.getElementById('clear-signature').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        resizeCanvas();
        hasDrawn = false;
    });
    document.getElementById('confirm-signature').addEventListener('click', function () {
        if (!hasDrawn) {
            alert('Please draw your signature first.');
            return;
        }
        signatureField.value = canvas.toDataURL('image/png');
        signatureSet = true;
        signatureStatus.textContent = 'Signature captured';
        signatureStatus.style.color = '#28a745';
        modal.style.display = 'none';
        updateSubmit();
    });
})();
</script>
</body>
</html>
