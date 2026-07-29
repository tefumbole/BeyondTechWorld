<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link expired - {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background: linear-gradient(180deg,#041f4a 0%,#0b3f90 100%); color:#fff; min-height:100vh; }
        .wrap { max-width:640px; margin:0 auto; padding:48px 16px; text-align:center; }
        .card { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); border-radius:16px; padding:28px 22px; }
        h1 { margin:0 0 10px; }
        p { color:#d7e3ff; line-height:1.6; }
        .badge { display:inline-block; padding:6px 12px; border-radius:999px; font-weight:800; margin-bottom:12px; background:#f59e0b; color:#111; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="badge">Link expired</div>
        <h1>This delivery signature link is no longer valid</h1>
        <p>The link has expired or has already been used. If you still need to confirm receipt, please contact Beyond Enterprise for a new link.</p>
    </div>
</div>
</body>
</html>
