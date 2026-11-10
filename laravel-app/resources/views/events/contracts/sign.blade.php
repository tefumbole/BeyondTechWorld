<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Contract — {{ $contract->reference_no }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body{background:#f4f7fb;color:#0f172a}
        .sheet{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:28px;box-shadow:0 12px 30px rgba(11,63,144,.06)}
        .contract-doc{background:#f8fafc;padding:20px;border-radius:14px;max-height:52vh;overflow:auto}
        .sign-name{margin-top:22px;line-height:1.35}
        h4{color:#0b3f90;font-weight:800}
        .btn-primary{background:#0b3f90;border-color:#0b3f90}
    </style>
</head>
<body>
<div class="container py-4" style="max-width:800px">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
<div class="sheet">
    <h4 class="mb-1">{{ $contract->title }}</h4>
    <p class="text-muted">{{ $contract->event->name }} · {{ $contract->reference_no }}</p>

    <div class="contract-doc mb-3">{!! $contract->rendered_body !!}
        <p class="sign-name"><strong>Sr. Engr. Tefu R. Mbole</strong><br>CEO / Director<br>Beyond Group of Companies</p>
    </div>

    <form method="POST" action="{{ route('event.contract.sign.submit', $contract->signature_token) }}">
        @csrf
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="agree" name="agreement_accepted" value="1" required>
            <label class="form-check-label" for="agree">I have read and agree to this contract</label>
        </div>
        <label class="font-weight-bold">Your signature</label>
        <canvas id="sig" width="500" height="150" style="border:1px solid #ccc;background:#fff;touch-action:none;max-width:100%"></canvas>
        <input type="hidden" name="signature_image" id="signature_image">
        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSig()">Clear</button>
            <button type="submit" class="btn btn-primary" onclick="return captureSig()">Sign contract</button>
        </div>
    </form>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
var canvas = document.getElementById('sig');
var pad = new SignaturePad(canvas);
function clearSig(){ pad.clear(); }
function captureSig(){
    if(pad.isEmpty()){ alert('Please sign first'); return false; }
    document.getElementById('signature_image').value = pad.toDataURL('image/png');
    return true;
}
</script>
</body>
</html>
