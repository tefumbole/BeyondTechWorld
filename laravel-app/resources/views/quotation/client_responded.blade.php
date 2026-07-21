<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation response - {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background: linear-gradient(180deg,#041f4a 0%,#0b3f90 100%); color:#fff; min-height:100vh; }
        .wrap { max-width:640px; margin:0 auto; padding:48px 16px; text-align:center; }
        .card { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); border-radius:16px; padding:28px 22px; }
        h1 { margin:0 0 10px; }
        p { color:#d7e3ff; line-height:1.6; }
        .badge { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:800; margin-bottom:12px; }
        .ok { background:#10b981; color:#fff; }
        .no { background:#ef4444; color:#fff; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        @if((int)$quotation->quotation_status === \App\Quotation::STATUS_APPROVED)
            <div class="badge ok">Approved</div>
            <h1>Thank you</h1>
            <p>You approved quotation <strong>{{ $quotation->reference_no }}</strong>. Our team will follow up. This was a quotation (not a receipt); suppliers are arranged upon cleared payment.</p>
        @else
            <div class="badge no">Rejected</div>
            <h1>Response received</h1>
            <p>You rejected quotation <strong>{{ $quotation->reference_no }}</strong>. Our team may contact you about modifications.</p>
        @endif
        @if($quotation->client_comment)
            <p><strong>Your comment:</strong><br>{{ $quotation->client_comment }}</p>
        @endif
    </div>
</div>
</body>
</html>
