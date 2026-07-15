<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Software License Subscription Agreement - {{ $general_setting->site_title ?? 'Agreement' }}</title>
    <link rel="icon" type="image/png" href="{{ url('public/logo', $general_setting->site_logo) }}" />
    <style>
        :root {
            --primary: #0b3f90;
            --primary-dark: #072f6b;
            --accent: #c6ab47;
            --text: #ffffff;
            --muted: #b8c7e6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Nunito", sans-serif;
            background: linear-gradient(180deg, #041f4a 0%, #0b3f90 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px 16px 120px; }
        .hero { text-align: center; margin-bottom: 24px; }
        .hero img { width: 72px; height: 72px; object-fit: contain; margin-bottom: 10px; }
        .hero h1 { margin: 0 0 8px; font-size: 28px; }
        .hero p { color: var(--muted); margin: 0; }
        .card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
        }
        .card-head { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
        .num {
            width: 34px; height: 34px; border-radius: 8px;
            border: 2px solid var(--accent); display: flex; align-items: center; justify-content: center;
            color: var(--accent); font-weight: 800;
        }
        .card h3 { margin: 0; color: var(--accent); font-size: 18px; }
        .card p, .card li { color: #e8efff; line-height: 1.6; font-size: 15px; }
        table.equipment { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.equipment th, table.equipment td {
            border-bottom: 1px solid rgba(255,255,255,0.12);
            padding: 10px 8px; text-align: left; font-size: 14px;
        }
        table.equipment th { color: var(--accent); }
        .signature-box {
            background: #fff8dc;
            border: 2px solid var(--accent);
            border-radius: 14px;
            padding: 18px;
            color: #5c4a12;
            margin-top: 24px;
        }
        .signature-box h4 { margin: 0 0 8px; display: flex; align-items: center; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            border-radius: 10px; padding: 12px 18px; font-weight: 700; cursor: pointer; border: 0;
        }
        .btn-outline { background: #fff; border: 2px solid #9a7b1f; color: #6b5612; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-accent { background: var(--accent); color: #10213d; }
        .btn-danger-outline { background: #fff; border: 2px solid #dc3545; color: #dc3545; }
        .checkbox-row { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; color: #e8efff; }
        .checkbox-row input { margin-top: 4px; }
        .footer-bar {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: rgba(4, 31, 74, 0.96); border-top: 1px solid rgba(255,255,255,0.12);
            padding: 14px 16px;
        }
        .footer-inner {
            max-width: 920px; margin: 0 auto; display: flex; flex-wrap: wrap;
            gap: 12px; align-items: center; justify-content: space-between;
        }
        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000;
            align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            background: #fff; color: #1f2a44; border-radius: 16px; width: 100%; max-width: 720px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35); overflow: hidden;
        }
        .modal-header { padding: 18px 20px; border-bottom: 1px solid #e5eaf3; }
        .modal-header h3 { margin: 0 0 6px; }
        .modal-body { padding: 18px 20px; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid #e5eaf3; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        #signature-pad {
            width: 100%; height: 220px; border: 2px solid #d7deea; border-radius: 12px;
            touch-action: none; background: #fff;
        }
        .preview-signature { max-width: 100%; border: 1px dashed #c6ab47; border-radius: 8px; display: none; margin-top: 10px; }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 14px; }
        .alert-danger { background: #ffe5e5; color: #842029; }
        .id-options { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .hidden-input { display: none; }
        @media (max-width: 640px) {
            .footer-inner { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        @if(!empty($general_setting->site_logo))
            <img src="{{ url('public/logo', $general_setting->site_logo) }}" alt="{{ $general_setting->site_title }}">
        @endif
        <h1>Software License Subscription Agreement</h1>
        <p>Booking Ref: <strong>{{ $booking->reference_no }}</strong> | Client: <strong>{{ $booking->customer->name ?? '' }}</strong></p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div id="agreement-content">
        <div class="card">
            <div class="card-head"><div class="num">1</div><h3>Subscription Summary</h3></div>
            <p>You have subscribed for the product(s) / service(s) listed below. Your subscription period runs from the start date to the end (expiry) date shown for each item. This covers software licenses and digital services such as IPTV, antivirus, and related subscriptions.</p>
            <table class="equipment">
                <thead>
                    <tr>
                        <th>Product / Service</th>
                        <th>Code</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>From</th>
                        <th>To (Expires)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td>{{ $item['code'] }}</td>
                            <td>{{ $item['qty'] }}</td>
                            <td>{{ number_format($item['unit_price'], 2) }}</td>
                            <td>{{ number_format($item['total'], 2) }}</td>
                            <td>{{ $item['start'] ? date('d M Y', strtotime($item['start'])) : 'As agreed' }}</td>
                            <td>{{ $item['end'] ? date('d M Y', strtotime($item['end'])) : 'As agreed' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><div class="num">2</div><h3>Access &amp; Credentials</h3></div>
            <p>After you sign this agreement and our team approves it, you will receive access to your <strong>client portal</strong> (login details via WhatsApp). Use the portal to view your subscription, signed contract, and related documents. Keep your login credentials confidential.</p>
        </div>
        <div class="card">
            <div class="card-head"><div class="num">3</div><h3>Fair Use &amp; License Scope</h3></div>
            <p>The subscription is for your personal or organizational use as registered under this booking. Sharing credentials beyond the agreed seats/qty, reselling access, or using the service for unlawful purposes is prohibited and may result in immediate suspension without refund.</p>
        </div>
        <div class="card">
            <div class="card-head"><div class="num">4</div><h3>Renewal &amp; Expiry</h3></div>
            <p>Service access continues through the expiry date listed above. Renewal is not automatic unless separately agreed. After expiry, access may be suspended until a new subscription is purchased or renewed.</p>
        </div>
        <div class="card">
            <div class="card-head"><div class="num">5</div><h3>Support &amp; Service Changes</h3></div>
            <p>Support is provided for the subscribed products during the active period. Third-party platforms (e.g. IPTV providers, antivirus vendors) may change features or availability; we will notify you of material changes where practical.</p>
        </div>
        @if(!empty($booking->booking_note))
        <div class="card">
            <div class="card-head"><div class="num">6</div><h3>Additional Notes</h3></div>
            <div class="booking-note-content">{!! \App\Support\BookingNoteFormatter::forDisplay($booking->booking_note) !!}</div>
        </div>
        @endif
        <div class="card">
            <div class="card-head"><div class="num">{{ !empty($booking->booking_note) ? '7' : '6' }}</div><h3>Payment Information</h3></div>
            <p>Grand Total: <strong>{{ number_format($booking->grand_total, 2) }}</strong></p>
            <p>Amount Paid: <strong>{{ number_format($booking->paid_amount, 2) }}</strong></p>
            <p>Balance Due: <strong>{{ number_format($booking->grand_total - $booking->paid_amount, 2) }}</strong></p>
            @if(isset($payments) && $payments->count())
                <ul>
                    @foreach($payments as $payment)
                        <li>{{ $payment->paying_method }} — {{ number_format($payment->amount, 2) }} @if($payment->change > 0)(Change: {{ number_format($payment->change, 2) }})@endif</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="card">
            <div class="card-head"><div class="num">{{ !empty($booking->booking_note) ? '8' : '7' }}</div><h3>Acceptance</h3></div>
            <p>By signing below, the client confirms they have subscribed for the product(s) listed, accept the subscription period (From–To), and agree to the terms of this Software License Subscription Agreement. Identity verification via ID card upload is required.</p>
        </div>
    </div>

    <form id="sign-form" method="POST" action="{{ route('rental.agreement.sign', $contract->signature_token) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="signature_image" id="signature_image">
        <input type="hidden" name="agreement_read_confirmed" id="agreement_read_confirmed" value="0">

        <div class="signature-box">
            <h4>⚠ Signature Required</h4>
            <p>A digital signature and valid ID card are required to complete this software license subscription agreement.</p>
            <button type="button" class="btn btn-outline" id="open-signature-modal" disabled>Add Signature</button>
            <img id="signature-preview" class="preview-signature" alt="Signature preview">

            <div class="id-options">
                <label class="btn btn-outline" for="id_card_file">Attach ID Card</label>
                <label class="btn btn-outline" for="id_card_camera">Snap ID Card</label>
                <input class="hidden-input" type="file" name="id_card" id="id_card_file" accept="image/*" required>
                <input class="hidden-input" type="file" id="id_card_camera" accept="image/*" capture="environment">
            </div>
            <div id="id-file-name" style="margin-top:8px;font-size:13px;"></div>
        </div>

        <div class="checkbox-row">
            <input type="checkbox" name="agreement_accepted" id="agreement_accepted" value="1" disabled>
            <label for="agreement_accepted">I have read and agree to the Software License Subscription Agreement and confirm that all information provided is accurate.</label>
        </div>
    </form>
</div>

<div class="footer-bar">
    <div class="footer-inner">
        <div>Do you accept the terms of this software license subscription?</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('login') }}" class="btn btn-danger-outline">I Disagree</a>
            <button type="submit" form="sign-form" class="btn btn-accent" id="submit-agreement" disabled>✓ Submit Agreement</button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="signature-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Sign Your Agreement</h3>
            <p style="margin:0;color:#6f7b91;">Draw your signature above using your mouse, trackpad, or touchscreen.</p>
        </div>
        <div class="modal-body">
            <canvas id="signature-pad"></canvas>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger-outline" id="close-signature-modal">✕ Cancel</button>
            <button type="button" class="btn btn-outline" id="clear-signature">Clear</button>
            <button type="button" class="btn btn-primary" id="confirm-signature">✓ Confirm Signature</button>
        </div>
    </div>
</div>

@include('booking.partials.id_card_compress_script')

<script>
(function () {
    var agreementRead = false;
    var signatureSet = false;
    var idSet = false;
    var readField = document.getElementById('agreement_read_confirmed');
    var acceptBox = document.getElementById('agreement_accepted');
    var openSigBtn = document.getElementById('open-signature-modal');
    var submitBtn = document.getElementById('submit-agreement');
    var idInput = document.getElementById('id_card_file');
    var cameraInput = document.getElementById('id_card_camera');
    var idName = document.getElementById('id-file-name');

    function checkAgreementRead() {
        var scrolledToBottom = window.scrollY + window.innerHeight >= document.body.scrollHeight - 40;
        if (scrolledToBottom) {
            agreementRead = true;
            readField.value = '1';
            acceptBox.disabled = false;
            openSigBtn.disabled = false;
        }
    }
    window.addEventListener('scroll', checkAgreementRead);
    checkAgreementRead();

    acceptBox.addEventListener('change', updateSubmitState);

    function bindIdInput(input) {
        if (typeof window.bindCompressedIdCardInput === 'function') {
            window.bindCompressedIdCardInput(input, idInput, function (name) {
                idSet = true;
                idName.textContent = 'Selected: ' + name;
                updateSubmitState();
            });
            return;
        }
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                var dt = new DataTransfer();
                dt.items.add(input.files[0]);
                idInput.files = dt.files;
                idSet = true;
                idName.textContent = 'Selected: ' + input.files[0].name;
                updateSubmitState();
            }
        });
    }
    bindIdInput(idInput);
    bindIdInput(cameraInput);

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
    function draw(e) {
        if (!drawing) return;
        var p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        e.preventDefault();
    }
    function endDraw() { drawing = false; }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', endDraw);

    document.getElementById('open-signature-modal').addEventListener('click', function () {
        modal.classList.add('open');
        setTimeout(resizeCanvas, 50);
    });
    document.getElementById('close-signature-modal').addEventListener('click', function () {
        modal.classList.remove('open');
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
        var dataUrl = canvas.toDataURL('image/png');
        document.getElementById('signature_image').value = dataUrl;
        var preview = document.getElementById('signature-preview');
        preview.src = dataUrl;
        preview.style.display = 'block';
        signatureSet = true;
        modal.classList.remove('open');
        updateSubmitState();
    });

    function updateSubmitState() {
        submitBtn.disabled = !(agreementRead && acceptBox.checked && signatureSet && idSet);
    }
})();
</script>
</body>
</html>
