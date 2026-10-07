<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invitation->ticket_code }} — Food ticket</title>
    <style>
        body { margin: 0; min-height: 100vh; font-family: "Source Sans Pro", sans-serif; background: #071433; color: #fffaf1; }
        .wrap { width: min(460px, calc(100% - 24px)); margin: 0 auto; padding: 28px 0 40px; text-align: center; }
        h1 { font-family: Georgia, serif; color: #f3dd8a; font-size: 28px; margin: 0 0 8px; }
        .meta { color: #e8eef8; }
        .state { margin: 18px 0; padding: 16px; border-radius: 16px; border: 1px solid rgba(212,175,55,.5); font-size: 28px; font-weight: 700; }
        .eaten { background: #14532d; }
        .waiting { background: rgba(255,255,255,.06); }
        button, a.btn { display: block; width: 100%; margin-top: 12px; min-height: 48px; border: 0; border-radius: 999px; background: #d4af37; color: #0b245c; font-size: 18px; font-weight: 700; cursor: pointer; text-decoration: none; line-height: 48px; }
        button.ghost { background: transparent; color: #f3dd8a; border: 1px solid rgba(212,175,55,.6); }
        p.note { color: #d5deee; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $invitation->name }}</h1>
    <p class="meta">{{ $invitation->typeLabel() }} · {{ $invitation->ticket_code }}</p>
    @if($invitation->eaten_at)
        <div class="state eaten">Eaten</div>
        <p class="note">Meal recorded {{ $invitation->eaten_at->timezone('Africa/Douala')->format('H:i') }}.</p>
    @else
        <div class="state waiting">Not eaten yet</div>
        <form method="POST" action="{{ url('/yong/ticket/'.$invitation->ticket_code.'/eat') }}">
            @csrf
            <button type="submit">Eat</button>
        </form>
    @endif
    @if($invitation->attended_at)
        <p class="note">Attendance already recorded. A thank-you was sent.</p>
    @else
        <form method="POST" action="{{ url('/yong/ticket/'.$invitation->ticket_code.'/attend') }}">
            @csrf
            <button type="submit" class="ghost">Attend</button>
        </form>
    @endif
    @if(request('welcomed'))
        <p class="note">Thank you message sent for coming to celebrate with Rev. Yong Nkiase and Family.</p>
    @endif
    <a class="btn" href="{{ url('/yong/meals') }}">Who has eaten</a>
</div>
</body>
</html>
