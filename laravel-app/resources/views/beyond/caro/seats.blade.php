<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Choose your seat — MSATA 2026</title>
    <style>
        :root { --ink: #1a1408; --gold: #c9a227; --navy: #140b2e; --muted: #6d6458; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: #f6f1e6; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 16px 12px 110px; }
        a.back { color: var(--navy); font-weight: 800; text-decoration: none; font-size: 14px; }
        h1 { margin: 8px 0 4px; font-size: 28px; letter-spacing: -.03em; }
        .lead { margin: 0 0 12px; color: var(--muted); font-size: 14px; }
        .err { background: #fdecec; color: #9b1c1c; border-radius: 12px; padding: 10px 12px; font-weight: 700; }
        .hall { overflow-x: auto; background: #fff; border-radius: 18px; padding: 12px; border: 1px solid #eadfca; }
        .stage { text-align: center; font-size: 12px; font-weight: 800; letter-spacing: .14em; color: var(--gold); margin-bottom: 8px; }
        .row { display: flex; align-items: center; gap: 8px; margin: 4px 0; min-width: max-content; }
        .row-no { width: 28px; text-align: right; font-size: 12px; color: var(--muted); font-weight: 700; }
        .block { display: flex; gap: 3px; }
        .aisle { width: 14px; }
        .passage { margin: 8px 0; text-align: center; font-size: 11px; letter-spacing: .12em; font-weight: 800; color: #8a8172; }
        .seat { width: 34px; height: 34px; border-radius: 8px; border: 1px solid #e4d3a4; background: #fff8e8; font-size: 11px; font-weight: 800; color: var(--navy); cursor: pointer; }
        .seat.is-on { background: var(--navy); color: #f6e3a8; border-color: var(--navy); }
        .seat:disabled { background: #ece7df; color: #a3988c; border-color: #e4ddd2; cursor: not-allowed; }
        .legend { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 0; font-size: 12px; color: var(--muted); }
        .swatch { width: 14px; height: 14px; border-radius: 4px; display: inline-block; vertical-align: -2px; margin-right: 4px; }
        .dock { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,.96); border-top: 1px solid #eadfca; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); }
        .dock-inner { max-width: 860px; margin: 0 auto; display: flex; gap: 10px; align-items: center; }
        .picked { font-weight: 800; min-width: 0; }
        .picked span { display: block; color: var(--muted); font-size: 12px; font-weight: 700; }
        .btn { margin-left: auto; border: 0; border-radius: 999px; background: var(--navy); color: #fff; font-weight: 800; min-height: 46px; padding: 0 16px; cursor: pointer; }
        .heads { display: flex; gap: 8px; margin: 0 0 6px 36px; min-width: max-content; }
        .heads b { width: calc(34px * 8 + 3px * 7); text-align: center; }
        .heads i { width: 14px; }
    </style>
</head>
<body>
@php
    $takenMap = array_fill_keys($taken, true);
    $showAll = ! $focused;
@endphp
<div class="wrap">
    <a class="back" href="{{ $homeUrl }}">Back</a>
    <h1>Choose your seat</h1>
    <p class="lead">{{ $guest['name'] }} · {{ \App\Support\CaroSeats::roleLabel($guest['role']) }}. Taken seats stay unavailable.</p>
    @if($error)<p class="err">{{ $error }}</p>@endif
    <form method="POST" action="{{ $claimUrl }}" id="seatForm">
        @csrf
        <input type="hidden" name="name" value="{{ $guest['name'] }}">
        <input type="hidden" name="country_code" value="{{ $guest['country_code'] }}">
        <input type="hidden" name="phone" value="{{ $guest['local_phone'] }}">
        <input type="hidden" name="role" value="{{ $guest['role'] }}">
        <input type="hidden" name="seat_code" id="seatCode" value="">
        <div class="hall" id="hall">
            <div class="stage">STAGE</div>
            <div class="heads">
                @foreach($columns as $index => $column)
                    @if($showAll && $index > 0)<i></i>@endif
                    <b>{{ $column }}</b>
                @endforeach
            </div>
            @foreach($rows as $row)
                @if($showAll && $row === 11)
                    <div class="passage">PASSAGE</div>
                @endif
                <div class="row" @if($loop->first) id="seat-start" @endif>
                    <span class="row-no">{{ $row }}</span>
                    @foreach($columns as $index => $column)
                        @if($showAll && $index > 0)<span class="aisle"></span>@endif
                        <span class="block">
                            @for($chair = 1; $chair <= 8; $chair++)
                                @php
                                    $code = \App\Support\CaroSeats::code($column, $row, $chair);
                                    $open = \App\Support\CaroSeats::allowed($guest['role'], $code) && empty($takenMap[$code]);
                                @endphp
                                <button class="seat" type="button" data-seat="{{ $code }}" aria-label="Seat {{ $code }}" {{ $open ? '' : 'disabled' }}>{{ $chair }}</button>
                            @endfor
                        </span>
                    @endforeach
                </div>
            @endforeach
        </div>
        <p class="legend"><span><i class="swatch" style="background:#fff8e8;border:1px solid #e4d3a4"></i>Open</span><span><i class="swatch" style="background:#140b2e"></i>Selected</span><span><i class="swatch" style="background:#ece7df"></i>Unavailable</span></p>
    </form>
</div>
<div class="dock">
    <div class="dock-inner">
        <div class="picked"><span>Selected seat</span><strong id="pickedLabel">None yet</strong></div>
        <button class="btn" type="submit" form="seatForm">Get my invitation</button>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('seatCode');
    var label = document.getElementById('pickedLabel');
    var start = document.getElementById('seat-start');
    if (start) start.scrollIntoView({block: 'center'});
    document.querySelectorAll('.seat:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
            button.classList.add('is-on');
            input.value = button.getAttribute('data-seat');
            label.textContent = input.value;
        });
    });
    document.getElementById('seatForm').addEventListener('submit', function (event) {
        if (!input.value) {
            event.preventDefault();
            label.textContent = 'Choose a seat';
        }
    });
})();
</script>
</body>
</html>
