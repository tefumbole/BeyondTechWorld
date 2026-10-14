<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Choose your seat — MSATA 2026</title>
    <style>
        :root { --ink: #1a1408; --gold: #c9a227; --navy: #140b2e; --muted: #6d6458; --seat: 36px; --seat-gap: 3px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--ink); background: #f6f1e6; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 16px 12px 110px; }
        a.back { color: var(--navy); font-weight: 800; text-decoration: none; font-size: 14px; }
        h1 { margin: 8px 0 4px; font-size: 28px; letter-spacing: -.03em; }
        .lead { margin: 0 0 12px; color: var(--muted); font-size: 14px; }
        .err { background: #fdecec; color: #9b1c1c; border-radius: 12px; padding: 10px 12px; font-weight: 700; }
        .hall { overflow-x: auto; background: #fff; border-radius: 18px; padding: 12px; border: 1px solid #eadfca; }
        .stage { text-align: center; font-size: 12px; font-weight: 800; letter-spacing: .14em; color: var(--gold); margin-bottom: 8px; }
        .row { display: flex; align-items: center; gap: 8px; margin: 4px 0; min-width: max-content; }
        .row-no { width: 28px; text-align: right; font-size: 12px; color: var(--muted); font-weight: 700; }
        .block { display: flex; gap: var(--seat-gap); align-items: flex-end; }
        .aisle { width: 14px; }
        .passage { margin: 8px 0; text-align: center; font-size: 11px; letter-spacing: .12em; font-weight: 800; color: #8a8172; }
        .seat {
            position: relative; width: var(--seat); height: 50px; padding: 0; border: 0; background: transparent;
            cursor: pointer; flex: none; color: var(--navy);
        }
        .seat .back, .seat .pad, .seat .legs { pointer-events: none; }
        .seat .back {
            position: absolute; left: 6px; right: 6px; top: 2px; height: 15px;
            border: 2px solid #c9a45a; border-bottom: 0; border-radius: 9px 9px 0 0; background: #fff6df;
        }
        .seat .pad {
            position: absolute; left: 2px; right: 2px; top: 15px; height: 22px; z-index: 1;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #c9a45a; border-radius: 4px;
            background: linear-gradient(#fffaf0, #f4ddaa);
            font-size: 11px; font-weight: 800; font-style: normal; color: var(--navy);
        }
        .seat .legs {
            position: absolute; left: 7px; right: 7px; bottom: 3px; height: 8px;
            background:
                linear-gradient(#c9a45a, #c9a45a) left / 3px 100% no-repeat,
                linear-gradient(#c9a45a, #c9a45a) right / 3px 100% no-repeat;
        }
        .seat.is-exec .back {
            left: 2px; right: 2px; top: 0; height: 18px;
            background: linear-gradient(#2a1c48, #140b2e);
            border-color: #e4c16a; border-left-width: 4px; border-right-width: 4px;
            border-radius: 10px 10px 1px 1px;
        }
        .seat.is-exec .pad {
            top: 16px; background: linear-gradient(#4a3818, #1c1408);
            border-color: #e4c16a; color: #f8e7b0;
        }
        .seat.is-exec .legs {
            background:
                linear-gradient(#e4c16a, #e4c16a) left / 3px 100% no-repeat,
                linear-gradient(#e4c16a, #e4c16a) right / 3px 100% no-repeat;
        }
        .seat.is-on .back { background: var(--navy); border-color: #e4c16a; }
        .seat.is-on .pad { background: var(--navy); color: #f6e3a8; border-color: #e4c16a; box-shadow: 0 0 0 2px #e4c16a; }
        .seat.is-taken .back, .seat.is-taken .pad { background: #ece7df; border-color: #d9d1c6; color: #a3988c; box-shadow: none; }
        .seat.is-taken .legs { opacity: .35; }
        .seat:disabled { cursor: not-allowed; }
        .seat:disabled:not(.is-taken) { opacity: .4; }
        .legend { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin: 12px 0 0; font-size: 13px; color: var(--muted); font-weight: 700; }
        .legend .key { display: inline-flex; align-items: center; gap: 4px; }
        .legend .seat { transform: scale(.72); transform-origin: left center; margin-right: -6px; }
        .dock { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,.96); border-top: 1px solid #eadfca; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); }
        .dock-inner { max-width: 1180px; margin: 0 auto; display: flex; gap: 10px; align-items: center; }
        .picked { font-weight: 800; min-width: 0; }
        .picked span { display: block; color: var(--muted); font-size: 12px; font-weight: 700; }
        .btn { margin-left: auto; border: 0; border-radius: 999px; background: var(--navy); color: #fff; font-weight: 800; min-height: 46px; padding: 0 16px; cursor: pointer; }
        .heads { display: flex; gap: 8px; margin: 0 0 6px 36px; min-width: max-content; }
        .heads b { width: calc(var(--seat) * 8 + var(--seat-gap) * 7); text-align: center; flex: none; }
        .heads i { width: 14px; }
    </style>
</head>
<body>
@php
    $takenMap = array_fill_keys($taken, true);
    $roleLabel = \App\Support\CaroSeats::roleLabel($guest['role']);
    $note = $guest['role'] === 'guest'
        ? 'Every seat is shown. Choose an open chair. Executive seats are reserved.'
        : 'Every seat is shown. You can only choose the executive seats reserved for '.$roleLabel.'.';
@endphp
<div class="wrap">
    <a class="back" href="{{ $homeUrl }}">Back</a>
    <h1>Choose your seat</h1>
    <p class="lead">{{ $guest['name'] }} · {{ $roleLabel }}. {{ $note }}</p>
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
                    @if($index > 0)<i></i>@endif
                    <b>{{ $column }}</b>
                @endforeach
            </div>
            @foreach($rows as $row)
                @if($row === 11)
                    <div class="passage">PASSAGE</div>
                @endif
                <div class="row">
                    <span class="row-no">{{ $row }}</span>
                    @foreach($columns as $index => $column)
                        @if($index > 0)<span class="aisle"></span>@endif
                        <span class="block">
                            @for($chair = 1; $chair <= 8; $chair++)
                                @php
                                    $code = \App\Support\CaroSeats::code($column, $row, $chair);
                                    $takenSeat = ! empty($takenMap[$code]);
                                    $open = \App\Support\CaroSeats::allowed($guest['role'], $code) && ! $takenSeat;
                                    $owner = \App\Support\CaroSeats::ownerRole($code);
                                    $classes = 'seat';
                                    if (\App\Support\CaroSeats::executive($code)) {
                                        $classes .= ' is-exec';
                                    }
                                    if ($takenSeat) {
                                        $classes .= ' is-taken';
                                    }
                                    $title = $code;
                                    if ($takenSeat) {
                                        $title = $code.' is taken';
                                    } elseif (! $open) {
                                        $title = $code.' is reserved for '.\App\Support\CaroSeats::roleLabel($owner);
                                    }
                                @endphp
                                <button class="{{ $classes }}" type="button" data-seat="{{ $code }}" data-mine="{{ $open ? '1' : '0' }}" title="{{ $title }}" aria-label="{{ $title }}" {{ $open ? '' : 'disabled' }}>
                                    <span class="back"></span>
                                    <span class="pad">{{ $chair }}</span>
                                    <span class="legs"></span>
                                </button>
                            @endfor
                        </span>
                    @endforeach
                </div>
            @endforeach
        </div>
        <p class="legend">
            <span class="key"><span class="seat demo" aria-hidden="true"><span class="back"></span><span class="pad"></span><span class="legs"></span></span> Chair</span>
            <span class="key"><span class="seat demo is-exec" aria-hidden="true"><span class="back"></span><span class="pad"></span><span class="legs"></span></span> Executive</span>
            <span class="key"><span class="seat demo is-on" aria-hidden="true"><span class="back"></span><span class="pad"></span><span class="legs"></span></span> Selected</span>
            <span class="key"><span class="seat demo is-taken" aria-hidden="true"><span class="back"></span><span class="pad"></span><span class="legs"></span></span> Unavailable</span>
        </p>
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
    document.querySelectorAll('button.seat:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('button.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
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
