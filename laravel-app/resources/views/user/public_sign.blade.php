<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Signature — {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        :root { --primary:#0b3f90; --accent:#c6ab47; --text:#1f2a44; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background: linear-gradient(180deg,#f4f8ff 0%,#e8efff 100%); color:var(--text); min-height:100vh; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 28px 16px 40px; }
        .card { background:#fff; border:1px solid #d7e4fb; border-radius:16px; padding:22px; box-shadow:0 8px 24px rgba(11,63,144,.08); }
        h1 { margin:0 0 8px; font-size:22px; color:var(--primary); }
        p { color:#64748b; margin:0 0 16px; line-height:1.5; }
        #signature-pad { width:100%; height:200px; border:2px dashed #0b3f90; border-radius:12px; touch-action:none; background:#f8fbff; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .btn { border:0; border-radius:10px; padding:12px 18px; font-weight:700; cursor:pointer; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-secondary { background:#e2e8f0; color:#334155; }
        .alert { padding:12px 14px; border-radius:10px; margin-bottom:14px; background:#ffe5e5; color:#842029; }
        .name { font-weight:700; color:var(--primary); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Add your {{ $label ?? 'signature' }}</h1>
        <p>Hi <span class="name">{{ $user->name }}</span> — please draw your <strong>{{ strtolower($label ?? 'signature') }}</strong> below. It will be saved to your Beyond account.</p>

        @if(session('not_permitted'))
            <div class="alert">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <form method="POST" action="{{ route('user.public.sign.store', $token) }}" id="public-sign-form">
            @csrf
            <canvas id="signature-pad" width="600" height="200"></canvas>
            <input type="hidden" name="signature_image" id="signature_image">
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="clear-pad">Clear</button>
                <button type="submit" class="btn btn-primary">Save {{ $label ?? 'signature' }}</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('signature-pad');
    var pad = new SignaturePad(canvas, { backgroundColor: 'rgba(0,0,0,0)', penColor: 'rgb(11,63,144)' });
    function resize() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var data = pad.toData();
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
        if (data.length) pad.fromData(data);
    }
    window.addEventListener('resize', resize);
    resize();
    document.getElementById('clear-pad').addEventListener('click', function () { pad.clear(); });
    document.getElementById('public-sign-form').addEventListener('submit', function (e) {
        if (pad.isEmpty()) {
            e.preventDefault();
            alert('Please draw your signature first.');
            return false;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
    });
})();
</script>
</body>
</html>
