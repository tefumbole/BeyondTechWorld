<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $invitation->name }} — {{ $invitation->seatLabel() }}</title>
    <style>
        body { margin: 0; background: #140b2e; color: #fff; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 18px 14px 32px; }
        h1 { font-size: 28px; margin: 0 0 6px; }
        p { color: #f3e6c0; }
        img { width: 100%; border-radius: 16px; display: block; }
        .btn { display: block; text-align: center; margin-top: 12px; background: #e6c56a; color: #140b2e; text-decoration: none; font-weight: 800; border-radius: 999px; min-height: 48px; line-height: 48px; }
        .ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.35); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $existing ? 'Your seat is saved' : 'Your invitation' }}</h1>
    <p>{{ $invitation->name }} · {{ $invitation->roleLabel() }} · Seat {{ $invitation->seatLabel() }}. {{ $existing ? 'This WhatsApp number already has one invitation, so another is not sent.' : 'A copy is on its way to your WhatsApp.' }}</p>
    <img src="{{ $invitation->imageUrl() }}" alt="Invitation for {{ $invitation->name }}, seat {{ $invitation->seatLabel() }}">
    <a class="btn" href="{{ $downloadUrl }}">Download invitation</a>
    <a class="btn ghost" href="{{ $homeUrl }}">Back</a>
</div>
@include('beyond.partials.mbole_ai_widget')
</body>
</html>
