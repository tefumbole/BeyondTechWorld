<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Your birthday flyer</title>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Cinzel:wght@700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; font-family: "Source Sans Pro", sans-serif; background: #120e18; color: #fffaf0; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 22px 14px 40px; text-align: center; }
        h1 { font-family: "Great Vibes", cursive; font-size: 40px; color: #f0d57a; margin: 0 0 8px; font-weight: 400; }
        p { color: #f3e6c4; }
        img { width: 100%; border-radius: 16px; border: 2px solid rgba(212,175,55,.5); box-shadow: 0 18px 40px rgba(0,0,0,.45); }
        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
        a { display: block; text-decoration: none; border-radius: 999px; padding: 14px; font-weight: 700; }
        .gold { background: linear-gradient(180deg, #f0d57a, #c9a227); color: #1c160e; }
        .ghost { border: 1px solid #d4af37; color: #f0d57a; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Your flyer is ready</h1>
    @if(!empty($sent))
        <p>It is on your WhatsApp as a photo with no caption — forward it to your status.</p>
    @else
        <p>Download the flyer below. WhatsApp delivery did not go through this time — you can still save the image and share it yourself.</p>
    @endif
    <img src="{{ $imageUrl }}" alt="Happy Birthday {{ $flyer->call_name }} from {{ $flyer->display_name }}">
    <div class="actions">
        <a class="gold" href="{{ $imageUrl }}" download="happy-birthday-{{ $flyer->call_name }}.jpg">Download flyer</a>
        <a class="ghost" href="{{ url('/mambole') }}">Make another</a>
    </div>
</div>
</body>
</html>
