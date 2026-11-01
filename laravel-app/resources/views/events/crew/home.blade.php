<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }}</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f4f7fb; color: #0f172a; }
        .wrap { max-width: 720px; margin: 16px auto; padding: 0 16px calc(32px + env(safe-area-inset-bottom)); }
        input, textarea, button { font-size: 16px; }
        button { width: 100%; min-height: 48px; }
        .card { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 12px 32px rgba(11,63,144,.08); margin-bottom: 16px; }
        .kicker { color: #0b3f90; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 28px; margin: 6px 0; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        label { display: block; font-weight: 700; margin: 12px 0 6px; }
        input, textarea { width: 100%; box-sizing: border-box; border: 1px solid #d6deea; border-radius: 12px; padding: 12px; font-size: 16px; font-family: inherit; }
        button { margin-top: 16px; border: 0; border-radius: 12px; background: #0b3f90; color: #fff; font-weight: 700; font-size: 16px; padding: 14px 18px; cursor: pointer; }
        .pay { font-size: 28px; font-weight: 800; color: #0b3f90; }
        .err { background: #fee2e2; color: #991b1b; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .ok { background: #dcfce7; color: #166534; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #e8eef6; }
        .hint { color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    @if(session('message'))<div class="ok">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <div class="card">
        <div class="kicker">Your event</div>
        <h1>{{ $event->name }}</h1>
        <p>{{ $event->event_start_at ? $event->event_start_at->format('d M Y') : '' }} to {{ $event->event_end_at ? $event->event_end_at->format('d M Y') : '' }}</p>
        <div>{{ $assignment->assignment_role ?: 'Crew' }}</div>
        <div><strong>Daily pay.</strong> {{ number_format($dailyRate) }} XAF</div>
        <div><strong>Food money.</strong> {{ number_format($foodAmount) }} XAF</div>
        <div class="pay">{{ number_format($dayAmount) }} XAF <span style="font-size:14px;font-weight:600;color:#64748b;">total for the day</span></div>
    </div>
    <div class="card">
        <h2>Timesheet</h2>
        <form method="POST" action="{{ route('events.crew.timesheet') }}">
            @csrf
            <label for="work_date">Day</label>
            <input id="work_date" type="date" name="work_date" required
                value="{{ old('work_date', now()->toDateString()) }}"
                @if($event->event_start_at) min="{{ $event->event_start_at->toDateString() }}" @endif
                @if($event->event_end_at) max="{{ $event->event_end_at->toDateString() }}" @endif>
            <label for="activity">What you did</label>
            <textarea id="activity" name="activity" rows="4" required placeholder="Describe the work for this day">{{ old('activity') }}</textarea>
            <label for="hours">Hours</label>
            <input id="hours" type="number" name="hours" min="0.5" max="24" step="0.5" value="{{ old('hours', 8) }}">
            <label for="phone">Mobile money number</label>
            <input id="phone" name="phone" value="{{ old('phone', $phone) }}" required>
            <div class="hint">This is the number on the account. Change it if the money should go to another MTN or Orange number. A new number is checked with Campay before it is sent.</div>
            <button type="submit">Submit for payment</button>
        </form>
    </div>
    <div class="card">
        <h2>Submitted days</h2>
        <table>
            <thead><tr><th>Day</th><th>Work</th><th>Hours</th></tr></thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->work_date ? $entry->work_date->format('d M Y') : '' }}</td>
                        <td>{{ $entry->notes }}</td>
                        <td>{{ $entry->hours }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nothing submitted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
