<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Wish Amah Willort a Happy Birthday</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: "Source Sans Pro", sans-serif;
            color: #fffaf0;
            background:
                radial-gradient(60vw 40vw at 12% -10%, rgba(243,221,138,.22), transparent 55%),
                linear-gradient(180deg, #09070e 0%, #14101d 42%, #0b2a5c 100%);
        }
        .page { width: min(720px, calc(100% - 24px)); margin: 0 auto; padding: clamp(24px, 4vw, 56px) 0 48px; text-align: center; }
        h1 { font-family: Cinzel, serif; font-size: clamp(26px, 4.6vw, 42px); color: #f3dd8a; margin: 0 0 12px; font-weight: 600; line-height: 1.2; text-wrap: balance; }
        p { color: #f3e6c4; font-size: clamp(14px, 1.8vw, 17px); }
        img { width: 100%; border-radius: clamp(16px, 2vw, 24px); border: 2px solid rgba(212,175,55,.5); box-shadow: 0 18px 40px rgba(0,0,0,.45); }
        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 18px; }
        a { display: block; text-decoration: none; border-radius: 999px; padding: 16px; font-weight: 700; }
        .gold { background: linear-gradient(180deg, #f0d57a, #c9a227); color: #1c160e; }
        .ghost { border: 1px solid #d4af37; color: #f0d57a; }
    </style>
</head>
<body>
<div class="page">
    <h1>Wish Amah Willort a Happy Birthday</h1>
    @if(!empty($sent))
        <p>It is on your WhatsApp as a photo with no caption — forward it to your status.</p>
    @else
        <p>Download the flyer below. WhatsApp delivery did not go through this time — you can still save the image and share it yourself.</p>
    @endif
    <img src="{{ $imageUrl }}" alt="Wish Amah Willort a Happy Birthday from {{ $flyer->display_name }}">
    <div class="actions">
        <a class="gold" href="{{ $imageUrl }}" download="wish-amah-willort-a-happy-birthday.jpg">Download flyer</a>
        <a class="ghost" href="{{ url('/mambole') }}">Make another</a>
    </div>
</div>
</body>
</html>
