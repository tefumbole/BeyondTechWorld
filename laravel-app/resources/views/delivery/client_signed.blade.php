<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery signed - {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background: linear-gradient(180deg,#041f4a 0%,#0b3f90 100%); color:#fff; min-height:100vh; }
        .wrap { max-width:640px; margin:0 auto; padding:48px 16px; text-align:center; }
        .card { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); border-radius:16px; padding:28px 22px; }
        h1 { margin:0 0 10px; }
        p { color:#d7e3ff; line-height:1.6; }
        .badge { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:800; margin-bottom:12px; background:#10b981; color:#fff; }
        img { max-width:100%; background:#fff; border-radius:10px; padding:8px; margin-top:12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="badge">Signed</div>
        <h1>Thank you</h1>
        <p>You confirmed receipt for delivery <strong>{{ $delivery->reference_no }}</strong>. This signature link is now closed.</p>
        @if($delivery->clientSignatureUrl())
            <p><strong>Your signature</strong></p>
            <img src="{{ $delivery->clientSignatureUrl() }}" alt="Signature">
        @endif
    </div>
</div>
</body>
</html>
