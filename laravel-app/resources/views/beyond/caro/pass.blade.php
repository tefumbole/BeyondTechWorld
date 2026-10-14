<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Invitation verification</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 14px; background: #f4f7fb; color: #12203a; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .card { width: min(420px, 100%); background: #fff; border-radius: 22px; border: 1px solid #e6ebf4; box-shadow: 0 12px 30px rgba(16, 33, 61, .08); padding: 28px 22px; }
        .mark { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 10px; display: grid; place-items: center; font-size: 32px; font-weight: 800; }
        .ok { background: #e7f6ee; color: #146c43; }
        .bad { background: #fdecec; color: #9b1c1c; }
        h1 { text-align: center; margin: 0; color: #0b3f90; font-size: 22px; }
        .pill { display: table; margin: 10px auto 18px; background: #e7f6ee; color: #146c43; border: 1px solid #b7e4c7; border-radius: 999px; padding: 6px 12px; font-weight: 800; font-size: 14px; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e6ebf4; font-size: 14px; }
        .row span { color: #66748c; }
        .row strong { text-align: right; }
        .seat { font-size: 22px; }
        .foot { text-align: center; color: #98a2b3; font-size: 12px; margin: 16px 0 0; }
        .miss { text-align: center; color: #66748c; }
    </style>
</head>
<body>
<div class="card">
    @if($error)
        <div class="mark bad">!</div>
        <h1>Verification failed</h1>
        <p class="miss">{{ $error }}</p>
    @else
        <div class="mark ok">✓</div>
        <h1>Invitation</h1>
        <div class="pill">Status: Valid</div>
        <div class="row"><span>Name</span><strong>{{ $invitation->name }}</strong></div>
        <div class="row"><span>Attending as</span><strong>{{ $invitation->roleLabel() }}</strong></div>
        <div class="row"><span>Seat</span><strong class="seat">{{ $invitation->seat_code }}</strong></div>
        <div class="row"><span>Event</span><strong>MSATA 2026</strong></div>
        <div class="row"><span>When</span><strong>8 Nov 2026, 1:00 PM</strong></div>
        <div class="row"><span>Where</span><strong>Presbyterian Hall, Ntamilung</strong></div>
    @endif
    <p class="foot">Spirit Waves Music · Invitation verification</p>
</div>
</body>
</html>
