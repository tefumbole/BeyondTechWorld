<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }}</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f4f7fb; color: #0f172a; }
        .wrap { max-width: 520px; margin: 16px auto; padding: 0 16px calc(32px + env(safe-area-inset-bottom)); }
        button, input { font-size: 16px; min-height: 48px; }
        .card { background: #fff; border-radius: 18px; padding: 28px 24px; box-shadow: 0 12px 32px rgba(11,63,144,.08); }
        .kicker { color: #0b3f90; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 28px; margin: 8px 0 6px; }
        p { color: #475569; line-height: 1.5; }
        .meta { background: #f8fafc; border-radius: 12px; padding: 12px 14px; margin: 16px 0; }
        .meta div { margin: 4px 0; }
        button, .btn { display: block; width: 100%; border: 0; border-radius: 12px; background: #0b3f90; color: #fff; font-weight: 700; font-size: 16px; padding: 14px; cursor: pointer; }
        .ghost { background: #fff; color: #b91c1c; border: 1px solid #fecaca; margin-top: 10px; }
        .btn.soft, button.soft { background: #fff; color: #0b3f90; border: 1px solid #bfdbfe; margin-top: 10px; text-align: center; text-decoration: none; box-sizing: border-box; }
        .day { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #f8fafc; border-radius: 12px; padding: 10px 12px; margin: 8px 0; }
        .day label { display: flex; align-items: center; gap: 10px; font-weight: 700; color: #0f172a; }
        .payline { font-weight: 800; color: #0b3f90; margin: 12px 0; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #d6deea; border-radius: 12px; padding: 14px; font-size: 22px; letter-spacing: .2em; text-align: center; margin: 8px 0 14px; }
        .day input[type="checkbox"] { width: 22px; height: 22px; min-height: 22px; margin: 0; padding: 0; letter-spacing: 0; border-radius: 6px; }
        .err { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .ok { background: #dcfce7; color: #166534; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .who { margin: 0; font-size: 28px; line-height: 1.15; }
        .role { margin: 4px 0 0; color: #0b3f90; font-weight: 800; }
        .event-name { margin: 14px 0 0; font-size: 16px; color: #334155; }
        canvas { width: 100%; height: 140px; border: 1px solid #d6deea; border-radius: 12px; background: #fff; touch-action: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="kicker">BeyondTechWorld</div>
        <h1 class="who">{{ $engineerName ?? 'Engineer' }}</h1>
        <p class="role">{{ $assignment->assignment_role ?: 'Crew' }}</p>
        <p class="event-name">{{ $event->name }}</p>
        <p>You have been selected for this event.</p>
        <div class="meta">
            <div><strong>Dates.</strong> {{ $event->event_start_at ? $event->event_start_at->format('d M Y') : '—' }} to {{ $event->event_end_at ? $event->event_end_at->format('d M Y') : '—' }}</div>
            <div><strong>Role.</strong> {{ $assignment->assignment_role ?: 'Crew' }}</div>
            <div><strong>Daily pay.</strong> {{ number_format($dailyRate) }} XAF</div>
            <div><strong>Food money.</strong> {{ number_format($foodAmount) }} XAF</div>
            <div><strong>Place.</strong> {{ $event->venue ?: 'As arranged' }}</div>
            @if(!empty($workDates))
                <div><strong>Your working days.</strong> {{ collect($workDates)->map(function ($date) { return date('d M Y', strtotime($date)); })->implode(', ') }}</div>
            @endif
            @if(!empty($setupText))
                <div><strong>Setup dates.</strong><br>{!! nl2br(e(trim($setupText))) !!}</div>
            @endif
        </div>
        @if(session('message'))<div class="ok">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
        @if($step === 'declined' || $assignment->acceptance_status === 'rejected')
            <p>You rejected this job. Your name is no longer on the event, and payments that had not been sent for you are cancelled.</p>
        @elseif($step === 'expired' || $assignment->acceptance_status === 'accepted')
            <p>This link has expired. You already accepted this event, so it cannot be accepted again.</p>
        @elseif($step === 'otp')
            <p>Enter the code sent to {{ $masked }}.</p>
            <form method="POST" action="{{ route('events.crew.verify', $assignment->invite_token) }}">
                @csrf
                <input name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required>
                <button type="submit">Log in</button>
            </form>
            <a class="btn soft" href="{{ route('events.crew.invite', ['token' => $assignment->invite_token, 'choose' => 1]) }}">Change the days</a>
            <form method="POST" action="{{ route('events.crew.reject', $assignment->invite_token) }}">
                @csrf
                <button type="submit" class="ghost">Reject this job</button>
            </form>
        @else
            <p>Keep the days you can work, or accept every day. Pay follows the days you accept. A login code then goes to {{ $masked }}.</p>
            @if(count($dayChoices))
                <form method="POST" action="{{ route('events.crew.accept', $assignment->invite_token) }}" id="day-form">
                    @csrf
                    @foreach($dayChoices as $day)
                        <div class="day">
                            <label>
                                <input type="checkbox" name="days[]" value="{{ $day['date'] }}" checked data-amount="{{ $day['amount'] }}">
                                {{ $day['label'] }}
                            </label>
                            <strong>{{ number_format($day['amount']) }}</strong>
                        </div>
                    @endforeach
                    <div class="payline" id="day-pay">Pay {{ number_format(collect($dayChoices)->sum('amount')) }} XAF</div>
                    @include('events.crew.signature', ['contract' => $contract ?? null])
                    <button type="submit">Accept these days</button>
                    <button type="submit" class="soft" name="accept_all" value="1">Accept the entire event</button>
                </form>
            @else
                <form method="POST" action="{{ route('events.crew.accept', $assignment->invite_token) }}" id="day-form">
                    @csrf
                    <input type="hidden" name="accept_all" value="1">
                    @include('events.crew.signature', ['contract' => $contract ?? null])
                    <button type="submit">Accept the entire event</button>
                </form>
            @endif
            <form method="POST" action="{{ route('events.crew.reject', $assignment->invite_token) }}">
                @csrf
                <button type="submit" class="ghost">Reject this job</button>
            </form>
            <script>
                var form = document.getElementById('day-form');
                if (form) {
                    var line = document.getElementById('day-pay');
                    function paintPay() {
                        var total = 0;
                        form.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
                            if (box.checked) total += parseInt(box.getAttribute('data-amount'), 10) || 0;
                        });
                        line.textContent = 'Pay ' + total.toLocaleString('en-US') + ' XAF';
                    }
                    form.addEventListener('change', paintPay);
                }
            </script>
        @endif
    </div>
</div>
</body>
</html>
