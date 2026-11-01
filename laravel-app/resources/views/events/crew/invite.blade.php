<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }}</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f4f7fb; color: #0f172a; }
        .wrap { max-width: 520px; margin: 32px auto; padding: 0 16px 40px; }
        .card { background: #fff; border-radius: 18px; padding: 28px 24px; box-shadow: 0 12px 32px rgba(11,63,144,.08); }
        .kicker { color: #0b3f90; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 28px; margin: 8px 0 6px; }
        p { color: #475569; line-height: 1.5; }
        .meta { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin: 16px 0; }
        .meta div { margin: 4px 0; }
        button, .btn { display: block; width: 100%; border: 0; border-radius: 12px; background: #0b3f90; color: #fff; font-weight: 700; font-size: 16px; padding: 14px; cursor: pointer; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #d6deea; border-radius: 12px; padding: 14px; font-size: 22px; letter-spacing: .2em; text-align: center; margin: 8px 0 14px; }
        .err { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .ok { background: #dcfce7; color: #166534; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="kicker">BeyondTechWorld</div>
        <h1>{{ $event->name }}</h1>
        <p>You have been selected for this event.</p>
        <div class="meta">
            <div><strong>Dates.</strong> {{ $event->event_start_at ? $event->event_start_at->format('d M Y') : '—' }} to {{ $event->event_end_at ? $event->event_end_at->format('d M Y') : '—' }}</div>
            <div><strong>Role.</strong> {{ $assignment->assignment_role ?: 'Crew' }}</div>
            <div><strong>Daily pay.</strong> {{ number_format($dailyRate) }} XAF</div>
            <div><strong>Food money.</strong> {{ number_format($foodAmount) }} XAF</div>
            <div><strong>Place.</strong> {{ $event->venue ?: 'As arranged' }}</div>
        </div>
        @if(session('message'))<div class="ok">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
        @if($step === 'otp')
            <p>Enter the code sent to {{ $masked }}.</p>
            <form method="POST" action="{{ route('events.crew.verify', $assignment->invite_token) }}">
                @csrf
                <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required>
                <button type="submit">Log in</button>
            </form>
        @else
            <p>Accept to receive a login code on {{ $masked }}.</p>
            <form method="POST" action="{{ route('events.crew.accept', $assignment->invite_token) }}">
                @csrf
                <button type="submit">Accept</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
