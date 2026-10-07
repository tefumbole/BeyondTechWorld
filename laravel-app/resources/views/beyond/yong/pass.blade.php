<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $invitation->name }} — {{ $invitation->typeLabel() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #10284f; --gold: #e4c16a; --ink: #1d2433; --muted: #6d7380; --cream: #f6f3ec; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: "Source Sans Pro", sans-serif; color: var(--ink); background: var(--cream); }
        .wrap { width: min(1180px, calc(100% - 28px)); margin: 0 auto; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0 14px; }
        .brand { display: flex; align-items: center; gap: 10px; color: var(--navy); text-decoration: none; }
        .brand svg { width: 36px; height: 36px; flex: none; }
        .brand strong { display: block; font-size: 18px; line-height: 1.1; }
        .brand span { display: block; font-size: 13px; color: #5c6574; }
        .pill { background: var(--navy); color: #fff; border-radius: 999px; padding: 8px 16px; font-weight: 700; font-size: 14px; }
        .stage {
            min-height: 640px;
            border-radius: 28px;
            overflow: hidden;
            background: #f7f4ee url('{{ asset('public/yong/landing-bg.jpg') }}') left center / cover no-repeat;
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(300px, 430px);
            align-items: center;
            gap: 18px;
            padding: 28px 28px 28px 36px;
        }
        .copy { color: #fff; max-width: 430px; text-shadow: 0 2px 16px rgba(8, 20, 48, .35); }
        .kicker { margin: 0 0 8px; letter-spacing: .22em; font-size: 12px; font-weight: 700; color: var(--gold); }
        .kicker:before { content: ""; display: inline-block; width: 28px; height: 1px; background: var(--gold); vertical-align: middle; margin-right: 8px; }
        h1 { font-family: "Playfair Display", serif; font-weight: 700; font-size: clamp(42px, 5vw, 64px); line-height: .95; margin: 0; color: var(--gold); }
        .who { font-family: "Playfair Display", serif; font-size: clamp(26px, 3vw, 34px); line-height: 1.15; margin: 12px 0 18px; }
        .facts { list-style: none; margin: 0; padding: 0; }
        .facts li { display: flex; align-items: center; gap: 10px; margin: 8px 0; font-size: 16px; font-weight: 600; }
        .dot { width: 28px; height: 28px; border: 1.4px solid var(--gold); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex: none; font-size: 13px; }
        .join { margin: 22px 0 0; max-width: 280px; font-size: 16px; line-height: 1.45; }
        .card { background: #fff; border-radius: 22px; padding: 28px 22px 22px; box-shadow: 0 18px 50px rgba(16, 40, 79, .12); text-align: center; }
        .card h2 { font-family: "Playfair Display", serif; font-size: 32px; line-height: 1.15; margin: 8px 0 0; color: var(--navy); }
        .lead { margin: 8px 0 0; color: #5d6572; }
        .badge { display: inline-block; margin-top: 16px; background: #f6e3a8; color: var(--navy); border-radius: 999px; padding: 8px 16px; font-weight: 700; }
        .meta { margin: 14px 0 0; color: var(--muted); font-size: 14px; }
        .btn { display: block; margin-top: 20px; background: var(--navy); color: #fff; text-decoration: none; border-radius: 12px; min-height: 50px; line-height: 50px; font-weight: 700; }
        .ok { margin-top: 18px; color: #166534; font-weight: 700; }
        .pending { margin-top: 18px; color: #92400e; font-weight: 700; }
        footer { text-align: center; color: #8d93a0; font-size: 14px; padding: 22px 0 28px; }
        @media (max-width: 900px) {
            .stage { display: block; min-height: 0; padding: 0; background: #fff; }
            .copy { min-height: 420px; padding: 28px 22px 24px; background: #10284f url('{{ asset('public/yong/landing-bg.jpg') }}') left center / cover no-repeat; border-radius: 24px; }
            .card { margin-top: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="{{ url('/yong') }}">
            <svg viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#10284f" d="M8 14c6 0 10 2 16 6 6-4 10-6 16-6v22c-6 0-10 2-16 6-6-4-10-6-16-6V14z"/>
                <path fill="#e4c16a" d="M24 18v22"/>
                <path fill="none" stroke="#fff" stroke-width="1.4" d="M14 20h6M14 24h6M28 20h6M28 24h6"/>
            </svg>
            <div>
                <strong>The Apostolic Church Cameroon</strong>
                <span>Obili District</span>
            </div>
        </a>
        <span class="pill">{{ $invitation->typeLabel() }}</span>
    </header>
    <section class="stage">
        <div class="copy">
            <p class="kicker">YOU ARE CORDIALLY INVITED</p>
            <h1>Induction<br>Service</h1>
            <p class="who">Rev. Yong Nkiase<br>and Family</p>
            <ul class="facts">
                <li><span class="dot">◷</span> Sunday, 11 October 2026</li>
                <li><span class="dot">◔</span> 10:00am</li>
                <li><span class="dot">⌖</span> The Apostolic Church Obili</li>
            </ul>
            <p class="join">Join us for a joyful service of worship and celebration.</p>
        </div>
        <div class="card">
            <p class="kicker" style="text-shadow:none;">Invitation</p>
            <h2>{{ $invitation->name }}</h2>
            <p class="lead">{{ $invitation->positionLabel() }}</p>
            <div class="badge">{{ $invitation->typeLabel() }} invitation</div>
            @if($invitation->ticket_code)
                <p class="meta">Ticket {{ $invitation->ticket_code }}</p>
            @endif
            @if($pay === 'ok' || $invitation->payment_status === 'paid')
                <p class="ok">Pledge received. Thank you.</p>
            @elseif($pay === 'pending')
                <p class="pending">Your payment is still being confirmed.</p>
            @elseif($invitation->isPremium())
                <a class="btn" href="{{ url('/yong/donate/'.$invitation->id) }}">Donate {{ $invitation->pledgeLabel() }}</a>
            @endif
        </div>
    </section>
    <footer>The Apostolic Church Cameroon · Obili District</footer>
</div>
</body>
</html>
