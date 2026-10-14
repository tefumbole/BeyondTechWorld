<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>How to book a seat — MSATA 2026</title>
    <style>
        :root { --navy: #140b2e; --gold: #e4c16a; --ink: #1d2433; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Source Sans Pro", "Segoe UI", sans-serif; color: var(--ink); background: #f6f3ec; }
        .wrap { width: min(760px, calc(100% - 28px)); margin: 0 auto; padding: 22px 0 40px; }
        a { color: var(--navy); font-weight: 800; }
        h1 { margin: 8px 0 6px; font-size: clamp(28px, 6vw, 40px); line-height: 1.1; color: var(--navy); }
        .lead { margin: 0 0 18px; font-size: 17px; line-height: 1.45; }
        .step { background: #fff; border-radius: 18px; padding: 16px 16px 14px; margin: 0 0 16px; box-shadow: 0 10px 28px rgba(20, 16, 40, .08); }
        .step h2 { margin: 0 0 8px; font-size: 20px; color: var(--navy); }
        .step p { margin: 0 0 12px; font-size: 16px; line-height: 1.45; }
        .step img { display: block; width: 100%; height: auto; border-radius: 12px; background: #f6f3ec; }
        .go { display: block; text-align: center; text-decoration: none; background: var(--navy); color: #fff; border-radius: 12px; padding: 14px 16px; font-size: 17px; margin-top: 8px; }
        .site-foot { margin-top: 28px; padding: 16px 14px calc(16px + env(safe-area-inset-bottom)); text-align: center; background: linear-gradient(90deg, #082a72, #12408f 42%, #0b327c); color: #f6e3a8; font-weight: 700; font-size: 14px; line-height: 1.5; }
        .site-foot a { color: #fff; }
        .ver { color: #e4c16a; }
    </style>
</head>
<body>
<div class="wrap">
    <a href="{{ $homeUrl }}">← Back to the invitation</a>
    <h1>How to book your seat</h1>
    <p class="lead">Four steps. Your first open seat is already chosen. Keep it, or pick another seat that belongs to your role, then assign it.</p>

    <article class="step">
        <h2>1. Enter your WhatsApp number</h2>
        <p>Start with the number that should receive the invitation. After 8 digits, the name linked to that number appears. You can edit it.</p>
        <img src="{{ asset('public/caro/help/1-details.jpg') }}" alt="Invitation form with a WhatsApp number, the name Amina N., and Guest selected" width="840" height="1030" loading="lazy" decoding="async">
    </article>

    <article class="step">
        <h2>2. Choose how you are attending</h2>
        <p>Tap Guest, Rev. / Pastor, Guest Minister, or Gospel Minister. Each role can sit only in its own chairs. Guest Ministers are the wine chairs in section B row 1. Pastors are the blue chairs in section B rows 2 and 3. Gospel Ministers are the purple chairs in section C rows 1 to 3. Everyone else uses the cream chairs. Then tap <strong>Choose a seat</strong>.</p>
    </article>

    <article class="step">
        <h2>3. Look at the hall</h2>
        <p>The stage is at the front. On a phone, swipe sideways to move from section A to section D. A lock means that chair is reserved for another role. A gold chair with a tick is the one selected for you.</p>
        <img src="{{ asset('public/caro/help/2-seats.jpg') }}" alt="Seat map with section A guests, section B guest ministers and pastors, and section C gospel ministers" width="900" height="495" loading="lazy" decoding="async">
    </article>

    <article class="step">
        <h2>4. Keep the seat or change it, then assign</h2>
        <p>The first open seat for your role is already filled in. Tap another open chair if you want a different one, or tap Clear selection. When the seat looks right, tap <strong>Assign seat</strong>. The invitation is sent to your WhatsApp number.</p>
        <img src="{{ asset('public/caro/help/3-assign.jpg') }}" alt="Selected seat panel showing A-01-01, the guest name, and the Assign seat button" width="840" height="980" loading="lazy" decoding="async">
    </article>

    <a class="go" href="{{ $homeUrl }}">Book my seat</a>
</div>
<footer class="site-foot">
    © {{ date('Y') }} Beyond Enterprise. All rights reserved. | Developed By | Sr. Engr. Tefu R. Mbole (A.K.A Papa Rolly) |
    <a href="{{ url('/#mbole-ai') }}">+237 675 321 739</a> |
    <span class="ver">{{ \App\Support\AppVersion::bcl() }}</span>
</footer>
@include('beyond.partials.mbole_ai_widget')
</body>
</html>
