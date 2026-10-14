<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>MSATA 2026 — Special Invitation</title>
    <style>
        :root { --ink: #1a1408; --gold: #e6c56a; --navy: #140b2e; --muted: #6d6458; --line: #eadfca; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: #f6f1e6; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 18px 14px 40px; }
        .hero { border-radius: 22px; overflow: hidden; background: #140b2e; color: #fff; padding: 22px 18px; }
        .kicker { margin: 0; letter-spacing: .16em; font-size: 12px; font-weight: 700; color: var(--gold); }
        h1 { margin: 8px 0 0; font-size: 34px; line-height: .95; letter-spacing: -.03em; }
        .sub { margin: 8px 0 0; color: #f3e6c0; font-size: 16px; }
        .facts { margin: 14px 0 0; padding: 0; list-style: none; font-size: 14px; line-height: 1.5; }
        .card { background: #fff; border-radius: 20px; padding: 16px; margin-top: 14px; border: 1px solid var(--line); }
        label { display: block; font-size: 13px; font-weight: 700; margin: 12px 0 6px; }
        input, select { width: 100%; min-height: 48px; border: 1px solid #e4d8c4; border-radius: 12px; padding: 0 12px; font-size: 16px; }
        .phone { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        .roles { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .role { appearance: none; border: 1px solid #e4d8c4; background: #fff; border-radius: 14px; min-height: 52px; font-weight: 700; font-size: 14px; cursor: pointer; }
        .role.is-on { background: #140b2e; color: var(--gold); border-color: #140b2e; }
        .btn { width: 100%; margin-top: 16px; min-height: 50px; border: 0; border-radius: 14px; background: #140b2e; color: #fff; font-size: 16px; font-weight: 800; cursor: pointer; }
        .err { color: #9b1c1c; font-size: 14px; font-weight: 700; margin: 8px 0 0; }
        .hint { color: var(--muted); font-size: 13px; margin: 8px 0 0; }
    </style>
</head>
<body>
<div class="wrap">
    <section class="hero">
        <p class="kicker">SPECIAL INVITATION</p>
        <h1>MSATA 2026</h1>
        <p class="sub">Spirit Waves Music cordially invites you.</p>
        <ul class="facts">
            <li>Sunday, 8 November 2026 · 1:00 PM prompt</li>
            <li>Presbyterian Hall, Ntamilung</li>
        </ul>
    </section>
    <form class="card" method="POST" action="{{ $startUrl }}">
        @csrf
        <h2 style="margin:0">Your invitation</h2>
        <p class="hint">Enter your name and WhatsApp number, then choose your seat.</p>
        @if(session('not_permitted'))<p class="err">{{ session('not_permitted') }}</p>@endif
        @if($errors->any())<p class="err">{{ $errors->first() }}</p>@endif
        <label for="name">Guest name</label>
        <input id="name" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="Name on the invitation">
        <label for="phone">WhatsApp number</label>
        <div class="phone">
            <select name="country_code" aria-label="Country code">
                @foreach($countries as $country)
                    <option value="{{ $country['code'] }}" {{ old('country_code', '+237') === $country['code'] ? 'selected' : '' }}>{{ $country['code'] }}</option>
                @endforeach
            </select>
            <input id="phone" name="phone" type="tel" inputmode="tel" required maxlength="40" value="{{ old('phone') }}" placeholder="6xxxxxxxx">
        </div>
        <label>You are attending as</label>
        <input type="hidden" name="role" id="role" value="{{ old('role', 'guest') }}">
        <div class="roles">
            @foreach($roles as $key => $label)
                <button class="role {{ old('role', 'guest') === $key ? 'is-on' : '' }}" type="button" data-role="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
        <button class="btn" type="submit">Choose a seat</button>
    </form>
</div>
<script>
(function () {
    var role = document.getElementById('role');
    document.querySelectorAll('.role').forEach(function (button) {
        button.addEventListener('click', function () {
            role.value = button.getAttribute('data-role');
            document.querySelectorAll('.role').forEach(function (item) { item.classList.remove('is-on'); });
            button.classList.add('is-on');
        });
    });
})();
</script>
</body>
</html>
