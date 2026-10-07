<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invitation->name }} — {{ $invitation->typeLabel() }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: Georgia, serif; background: #071433; color: #fffaf1; }
        .card { width: min(420px, calc(100% - 28px)); text-align: center; border: 1px solid #d4af37; border-radius: 18px; padding: 28px 20px; background: #0b245c; }
        .kicker { letter-spacing: .18em; text-transform: uppercase; color: #d4af37; font-size: 12px; font-family: sans-serif; }
        h1 { margin: 10px 0 6px; font-size: 28px; }
        .type { font-size: 20px; color: #f3dd8a; margin: 0; }
        a { color: #f3dd8a; }
        .note { font-family: sans-serif; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <p class="kicker">Induction Service</p>
    <h1>{{ $invitation->name }}</h1>
    <p class="type">{{ $invitation->typeLabel() }}</p>
    @if($pay === 'ok')
        <p class="note">Thank you. Your pledge has been received.</p>
    @elseif($pay === 'pending')
        <p class="note">Your payment is still being confirmed.</p>
    @elseif($invitation->isPremium() && $invitation->payment_status !== 'paid')
        <p class="note"><a href="{{ url('/yong/donate/'.$invitation->id) }}">Donate {{ $invitation->pledgeLabel() }}</a></p>
    @elseif($invitation->payment_status === 'paid')
        <p class="note">Pledge received.</p>
    @endif
</div>
</body>
</html>
