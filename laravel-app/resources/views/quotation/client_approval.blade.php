<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation Approval - {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        :root { --primary:#0b3f90; --accent:#c6ab47; --text:#fff; --muted:#b8c7e6; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background: linear-gradient(180deg,#041f4a 0%,#0b3f90 100%); color:var(--text); min-height:100vh; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px 16px 140px; }
        .hero { text-align:center; margin-bottom:20px; }
        .hero h1 { margin:0 0 8px; font-size:28px; }
        .hero p { color:var(--muted); margin:0; }
        .card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:16px; padding:18px 20px; margin-bottom:14px; }
        .card h3 { margin:0 0 10px; color:var(--accent); }
        .card p, .card li { color:#e8efff; line-height:1.6; font-size:15px; }
        .note-content { color:#e8efff; line-height:1.65; font-size:15px; }
        .note-content ul, .note-content ol { margin:8px 0 8px 1.25rem; padding:0; }
        .note-content li { margin:4px 0; }
        .note-content p { margin:0 0 10px; }
        .note-content strong, .note-content b { color:#fff; }
        table.items { width:100%; border-collapse:collapse; margin-top:8px; }
        table.items th, table.items td { border-bottom:1px solid rgba(255,255,255,.12); padding:10px 8px; text-align:left; font-size:14px; }
        table.items th { color:var(--accent); }
        .totals { text-align:right; margin-top:12px; color:#fff; }
        .totals-table { width:100%; max-width:320px; margin-left:auto; border-collapse:collapse; }
        .totals-table td { padding:6px 0; font-size:14px; color:#e8efff; }
        .totals-table td:last-child { text-align:right; font-variant-numeric:tabular-nums; padding-left:16px; }
        .totals-table .discount td { color:#fbbf24; }
        .totals-table .grand td { font-weight:800; color:#fff; font-size:16px; padding-top:10px; border-top:1px solid rgba(255,255,255,.18); }
        .checkbox-row { display:flex; gap:10px; align-items:flex-start; margin:12px 0; }
        textarea, input[type=text] { width:100%; border-radius:10px; border:1px solid #d7deea; padding:12px; font-size:15px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:10px; padding:12px 18px; font-weight:700; cursor:pointer; border:0; text-decoration:none; }
        .btn-accent { background:var(--accent); color:#10213d; }
        .btn-danger { background:#ef4444; color:#fff; }
        .btn-outline { background:#fff; color:#0b3f90; }
        .footer-bar { position:fixed; left:0; right:0; bottom:0; background:rgba(4,31,74,.96); border-top:1px solid rgba(255,255,255,.12); padding:14px 16px; }
        .footer-inner { max-width:920px; margin:0 auto; display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; }
        .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal { background:#fff; color:#1f2a44; border-radius:16px; width:100%; max-width:720px; overflow:hidden; }
        .modal-header, .modal-body, .modal-footer { padding:16px 20px; }
        .modal-header { border-bottom:1px solid #e5eaf3; }
        .modal-footer { border-top:1px solid #e5eaf3; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
        #signature-pad { width:100%; height:220px; border:2px solid #d7deea; border-radius:12px; touch-action:none; background:#fff; }
        .alert { padding:12px 14px; border-radius:10px; margin-bottom:14px; }
        .alert-danger { background:#ffe5e5; color:#842029; }
        .preview-signature { max-width:100%; border:1px dashed #c6ab47; border-radius:8px; display:none; margin-top:10px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>Quotation for Approval</h1>
        <p>{{ $general_setting->site_title ?? 'Beyond Enterprise' }} · Ref {{ $quotation->reference_no }}</p>
    </div>

    @if(session('not_permitted'))
        <div class="alert alert-danger">{{ session('not_permitted') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="card">
        <h3>Client</h3>
        <p>
            <strong>{{ optional($quotation->customer)->name }}</strong><br>
            {{ optional($quotation->customer)->phone_number }}
            @if(optional($quotation->customer)->email)<br>{{ $quotation->customer->email }}@endif
        </p>
    </div>

    @php
        $subtotal = (float) ($quotation->total_price ?? 0);
        $orderTax = (float) ($quotation->order_tax ?? 0);
        $shipping = (float) ($quotation->shipping_cost ?? 0);
        $grandTotal = (float) ($quotation->grand_total ?? 0);
        $orderDiscount = (float) ($quotation->order_discount ?? 0);
        // Always surface a discount when totals don't match (never leave clients guessing).
        if ($orderDiscount <= 0 && $subtotal > 0) {
            $inferred = round($subtotal + $orderTax + $shipping - $grandTotal, 2);
            if ($inferred > 0.009) {
                $orderDiscount = $inferred;
            }
        }
        $showDiscount = $orderDiscount > 0.009;
    @endphp
    <div class="card">
        <h3>Quoted items</h3>
        <table class="items">
            <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit price</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td>{{ $line['qty'] }} {{ $line['unit'] }}</td>
                    <td>{{ number_format((float)$line['net_unit_price'], 2) }}</td>
                    <td>{{ number_format((float)$line['total'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="totals">
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td>{{ number_format($subtotal, 2) }}</td>
                </tr>
                @if($showDiscount)
                    <tr class="discount">
                        <td>Discount</td>
                        <td>-{{ number_format($orderDiscount, 2) }}</td>
                    </tr>
                @endif
                @if($orderTax > 0)
                    <tr>
                        <td>Tax</td>
                        <td>{{ number_format($orderTax, 2) }}</td>
                    </tr>
                @endif
                @if($shipping > 0)
                    <tr>
                        <td>Shipping</td>
                        <td>{{ number_format($shipping, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td>Total due</td>
                    <td>{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    @if(trim((string) ($quotation->note ?? '')) !== '')
    <div class="card">
        <h3>Note</h3>
        <div class="note-content">{!! \App\Support\BookingNoteFormatter::forDisplay($quotation->note) !!}</div>
    </div>
    @endif

    <div class="card">
        <h3>Quotation agreement</h3>
        <p>Please read carefully before approving or rejecting:</p>
        <ol>
            <li><strong>This document is a quotation, not a receipt or invoice.</strong> It is an offer of goods/services and pricing for your consideration only. No payment obligation arises until a sale or booking is confirmed after your approval.</li>
            <li><strong>Suppliers / fulfilment will be arranged upon cleared payments.</strong> Procurement, reservation, or delivery of items proceeds only after payment has been received and cleared as agreed with {{ $general_setting->site_title ?? 'our company' }}.</li>
            <li><strong>You reserve the right to request modifications.</strong> You may request changes to quantities, items, or terms. Revised quotations may be issued for your review before final acceptance.</li>
            <li>By signing and approving, you confirm that you have reviewed the quoted items and totals, and you authorise {{ $general_setting->site_title ?? 'our company' }} to proceed toward order processing subject to payment and availability.</li>
        </ol>
    </div>

    <div class="card">
        <h3>Your comment</h3>
        <p style="color:var(--muted);font-size:13px;margin-top:0;">Required if you reject. Optional if you approve.</p>
        <textarea id="client_comment_shared" rows="3" placeholder="Add a comment for our team…">{{ old('client_comment') }}</textarea>
        <img id="sig-preview" class="preview-signature" alt="Signature preview">
    </div>
</div>

<div class="footer-bar">
    <div class="footer-inner">
        <div style="color:var(--muted);font-size:13px;">Approve with signature, or reject with a comment.</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" class="btn btn-danger" id="btn-reject">Reject</button>
            <button type="button" class="btn btn-accent" id="btn-approve">Sign &amp; Approve</button>
        </div>
    </div>
</div>

<form id="approve-form" method="POST" action="{{ route('quotation.client.approve', $quotation->client_approval_token) }}" style="display:none;">
    @csrf
    <input type="hidden" name="accept_agreement" value="1" id="accept_agreement">
    <input type="hidden" name="client_comment" id="approve_comment">
    <input type="hidden" name="signature_data" id="signature_data">
</form>

<form id="reject-form" method="POST" action="{{ route('quotation.client.reject', $quotation->client_approval_token) }}" style="display:none;">
    @csrf
    <input type="hidden" name="client_comment" id="reject_comment">
</form>

<div class="modal-backdrop" id="sig-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="margin:0;">Sign to approve</h3>
            <p style="margin:6px 0 0;color:#64748b;font-size:14px;">Draw your signature below, then confirm approval.</p>
        </div>
        <div class="modal-body">
            <label class="checkbox-row" style="color:#1f2a44;">
                <input type="checkbox" id="agree_box">
                <span>I have read the quotation agreement. I understand this is a quotation (not a receipt), suppliers are arranged upon cleared payment, and I may request modifications.</span>
            </label>
            <canvas id="signature-pad"></canvas>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" id="sig-clear">Clear</button>
            <button type="button" class="btn btn-outline" id="sig-cancel">Cancel</button>
            <button type="button" class="btn btn-accent" id="sig-confirm">Confirm approval</button>
        </div>
    </div>
</div>

<script>
(function () {
    var canvas = document.getElementById('signature-pad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasInk = false;

    function resize() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var w = canvas.clientWidth;
        canvas.width = w * ratio;
        canvas.height = 220 * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0b3f90';
        hasInk = false;
    }
    resize();
    window.addEventListener('resize', resize);

    function pos(e) {
        var r = canvas.getBoundingClientRect();
        var t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    function start(e) { e.preventDefault(); drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
    function move(e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); hasInk = true; }
    function end() { drawing = false; }
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false});
    canvas.addEventListener('touchend', end);

    var modal = document.getElementById('sig-modal');
    document.getElementById('btn-approve').addEventListener('click', function () {
        modal.classList.add('open');
        // Canvas is display:none until open — size it after layout so drawing works
        setTimeout(function () { resize(); }, 50);
    });
    document.getElementById('sig-cancel').addEventListener('click', function () { modal.classList.remove('open'); });
    document.getElementById('sig-clear').addEventListener('click', function () { resize(); });

    document.getElementById('sig-confirm').addEventListener('click', function () {
        if (!document.getElementById('agree_box').checked) {
            alert('Please accept the quotation agreement before approving.');
            return;
        }
        if (!hasInk) {
            alert('Please draw your signature.');
            return;
        }
        var dataUrl = canvas.toDataURL('image/png');
        if (!dataUrl || dataUrl.length < 200) {
            alert('Could not capture signature. Please clear and sign again.');
            return;
        }
        document.getElementById('approve_comment').value = document.getElementById('client_comment_shared').value;
        document.getElementById('signature_data').value = dataUrl;
        document.getElementById('sig-preview').src = dataUrl;
        document.getElementById('sig-preview').style.display = 'block';
        document.getElementById('approve-form').submit();
    });

    document.getElementById('btn-reject').addEventListener('click', function () {
        var comment = (document.getElementById('client_comment_shared').value || '').trim();
        if (!comment) {
            alert('Please enter a comment explaining why you reject this quotation.');
            document.getElementById('client_comment_shared').focus();
            return;
        }
        if (!confirm('Reject this quotation?')) return;
        document.getElementById('reject_comment').value = comment;
        document.getElementById('reject-form').submit();
    });
})();
</script>
</body>
</html>
