<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signature saved — {{ $general_setting->site_title ?? 'Beyond' }}</title>
    <style>
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background:#f4f8ff; color:#1f2a44; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { background:#fff; border:1px solid #d7e4fb; border-radius:16px; padding:28px; max-width:480px; text-align:center; box-shadow:0 8px 24px rgba(11,63,144,.08); }
        h1 { color:#0b3f90; margin:0 0 10px; font-size:22px; }
        p { color:#64748b; margin:0; line-height:1.5; }
    </style>
</head>
<body>
<div class="card">
    <h1>Signature saved</h1>
    <p>Thank you, {{ $user->name }}. Your signature is now attached to your account. You can close this page.</p>
</div>
</body>
</html>
