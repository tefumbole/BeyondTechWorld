<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1408; font-size: 13px; line-height: 1.55; }
        .kicker { letter-spacing: 2px; text-transform: uppercase; color: #8a6d1d; font-size: 10px; margin: 0 0 6px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .sub { color: #5a4a2a; margin: 0 0 18px; }
        .body { white-space: pre-wrap; font-size: 14px; line-height: 1.7; }
        .foot { margin-top: 28px; font-size: 11px; color: #6a5a40; border-top: 1px solid #d4c4a0; padding-top: 10px; }
    </style>
</head>
<body>
    <p class="kicker">In loving memory</p>
    <h1>Your eulogy for Pa Ngwayu Francis</h1>
    <p class="sub">{{ $eulogy->name }} · {{ optional($eulogy->created_at)->format('d M Y H:i') }}</p>
    <div class="body">{{ $eulogy->body }}</div>
    <p class="foot">A copy of the eulogy you submitted at beyondtechworld.com/pangwayu/remember</p>
</body>
</html>
