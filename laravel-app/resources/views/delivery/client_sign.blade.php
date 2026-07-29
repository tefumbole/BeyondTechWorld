<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Delivery - {{ $general_setting->site_title ?? 'Beyond' }}</title>
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
        table.items { width:100%; border-collapse:collapse; margin-top:8px; }
        table.items th, table.items td { border-bottom:1px solid rgba(255,255,255,.12); padding:10px 8px; text-align:left; font-size:14px; }
        table.items th { color:var(--accent); }
        .checkbox-row { display:flex; gap:10px; align-items:flex-start; margin:12px 0; }
        input[type=text] { width:100%; border-radius:10px; border:1px solid #d7deea; padding:12px; font-size:15px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:10px; padding:12px 18px; font-weight:700; cursor:pointer; border:0; }
        .btn-accent { background:var(--accent); color:#10213d; }
        .btn-outline { background:#fff; color:#0b3f90; }
        .footer-bar { position:fixed; left:0; right:0; bottom:0; background:rgba(4,31,74,.96); border-top:1px solid rgba(255,255,255,.12); padding:14px 16px; }
        .footer-inner { max-width:920px; margin:0 auto; display:flex; gap:12px; justify-content:flex-end; }
        .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal { background:#fff; color:#1f2a44; border-radius:16px; width:100%; max-width:720px; overflow:hidden; }
        .modal-header, .modal-body, .modal-footer { padding:16px 20px; }
        .modal-header { border-bottom:1px solid #e5eaf3; }
        .modal-footer { border-top:1px solid #e5eaf3; display:flex; gap:10px; justify-content:flex-end; }
        #signature-pad { width:100%; height:220px; border:2px solid #d7deea; border-radius:12px; touch-action:none; background:#fff; }
        .alert { padding:12px 14px; border-radius:10px; margin-bottom:14px; background:#ffe5e5; color:#842029; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>Confirm Goods Received</h1>
        <p>{{ $general_setting->site_title ?? 'Beyond Enterprise' }} · {{ $delivery->reference_no }}</p>
    </div>

    @if(session('not_permitted'))
        <div class="alert">{{ session('not_permitted') }}</div>
    @endif
    @if($errors->any())
        <div class="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="card">
        <h3>Delivery</h3>
        <p>
            <strong>Sale:</strong> {{ optional($sale)->reference_no }}<br>
            <strong>Customer:</strong> {{ optional(optional($sale)->customer)->name }}<br>
            <strong>Address:</strong> {{ $delivery->address }}<br>
            <strong>Delivered By:</strong> {{ $delivery->delivered_by ?: '—' }}<br>
            <strong>Received By:</strong> {{ $delivery->recieved_by ?: '—' }}
        </p>
    </div>

    <div class="card">
        <h3>Items</h3>
        <table class="items">
            <thead><tr><th>#</th><th>Code</th><th>Description</th><th>Qty</th></tr></thead>
            <tbody>
            @foreach($lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line['code'] }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td>{{ $line['qty'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <form id="sign-form" method="POST" action="{{ route('delivery.client.sign', $delivery->client_signature_token) }}">
        @csrf
        <div class="card">
            <h3>Confirmation</h3>
            <div class="checkbox-row">
                <input type="checkbox" name="confirm_receipt" id="confirm_receipt" value="1" required>
                <label for="confirm_receipt">I confirm that I have received the goods listed above in good condition.</label>
            </div>
            <label for="signer_name">Full name</label>
            <input type="text" name="signer_name" id="signer_name" value="{{ old('signer_name', $delivery->recieved_by) }}" placeholder="Your full name">
            <input type="hidden" name="signature_data" id="signature_data">
        </div>
    </form>
</div>

<div class="footer-bar">
    <div class="footer-inner">
        <button type="button" class="btn btn-accent" id="open-sign">Sign &amp; Confirm Receipt</button>
    </div>
</div>

<div class="modal-backdrop" id="sign-modal">
    <div class="modal">
        <div class="modal-header"><strong>Draw your signature</strong></div>
        <div class="modal-body">
            <canvas id="signature-pad"></canvas>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" id="clear-pad">Clear</button>
            <button type="button" class="btn btn-accent" id="confirm-sign">Confirm Signature</button>
        </div>
    </div>
</div>

<script>
(function () {
    var canvas = document.getElementById('signature-pad');
    var ctx = canvas.getContext('2d');
    var drawing = false, hasInk = false;
    function resize() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = 220 * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
    }
    function pos(e) {
        var r = canvas.getBoundingClientRect();
        var t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    function start(e) { drawing = true; hasInk = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function end() { drawing = false; }
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false});
    canvas.addEventListener('touchend', end);
    document.getElementById('open-sign').addEventListener('click', function () {
        if (!document.getElementById('confirm_receipt').checked) {
            alert('Please confirm that you received the goods.');
            return;
        }
        document.getElementById('sign-modal').classList.add('open');
        setTimeout(resize, 50);
    });
    document.getElementById('clear-pad').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height); hasInk = false;
    });
    document.getElementById('confirm-sign').addEventListener('click', function () {
        if (!hasInk) { alert('Please draw your signature.'); return; }
        document.getElementById('signature_data').value = canvas.toDataURL('image/png');
        document.getElementById('sign-form').submit();
    });
    window.addEventListener('resize', function () {
        if (document.getElementById('sign-modal').classList.contains('open')) resize();
    });
})();
</script>
</body>
</html>
