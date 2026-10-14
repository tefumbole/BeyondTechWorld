<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Choose your seat — MSATA 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #14182b; --gold: #e4c16a; --ink: #1d2433; --muted: #6d6458; --cream: #f7f3ea; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Source Sans Pro", sans-serif; color: var(--ink); background: #ece6da; }
        .sheet { max-width: 1240px; margin: 12px auto; background: #fffdf8; border-radius: 22px; overflow: hidden; box-shadow: 0 16px 50px rgba(20, 16, 40, .12); }
        .top {
            display: flex; align-items: center; gap: 18px;
            padding: 16px 20px; color: #fff;
            background: radial-gradient(120% 140% at 0% 0%, #243056 0%, #14182b 55%, #10131f 100%);
        }
        .brand { flex: none; padding-right: 16px; border-right: 1px solid rgba(228, 193, 106, .45); }
        .brand strong { display: block; font-family: "Playfair Display", serif; font-size: 22px; color: var(--gold); letter-spacing: .02em; line-height: 1; }
        .brand span { display: block; margin-top: 4px; font-size: 10px; letter-spacing: .16em; color: #d9d3c4; }
        .top h1 { margin: 0; font-family: "Playfair Display", serif; font-size: 28px; font-weight: 600; }
        .top p { margin: 2px 0 0; color: #d7d1c4; font-size: 14px; }
        .view {
            margin-left: auto; flex: none; text-decoration: none; color: var(--gold);
            border: 1px solid var(--gold); border-radius: 999px; padding: 8px 14px; font-weight: 700; font-size: 14px;
        }
        .err { margin: 12px 16px 0; background: #fdecec; color: #9b1c1c; border-radius: 12px; padding: 10px 12px; font-weight: 700; }
        .layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 14px; padding: 14px; align-items: start; }
        .map { min-width: 0; background: var(--cream); border-radius: 16px; padding: 8px 10px 12px; }
        .hall { position: relative; overflow-x: auto; padding-bottom: 6px; -webkit-overflow-scrolling: touch; touch-action: pan-x; }
        .swipe { display: none; margin: 0 0 8px; text-align: center; font-size: 14px; font-weight: 800; color: #8a6a24; }
        .hall-inner { position: relative; width: max-content; min-width: 100%; }
        .stage {
            position: relative; width: 100%; height: 46px; margin: 0 0 4px;
            background: linear-gradient(#1c2744, #12182c); color: var(--gold);
            border: 2px solid #c9a45a; border-top: 0; border-radius: 0 0 90px 90px;
            letter-spacing: .28em; font-weight: 800; font-size: 16px;
        }
        .front { position: relative; height: 32px; margin: 4px 0 8px; color: #8a8172; font-size: 14px; letter-spacing: .22em; font-weight: 800; }
        .pin { position: absolute; top: 0; bottom: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .front .pin { gap: 10px; }
        .front .pin:before, .front .pin:after { content: ""; width: 72px; height: 1px; background: #d9c48a; }
        .sections { display: flex; gap: 18px; min-width: max-content; justify-content: flex-start; }
        .section { min-width: 0; }
        .section h2 { margin: 0 0 6px; text-align: center; font-size: 16px; letter-spacing: .12em; color: #5c564c; }
        .role-head { height: 44px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 2px; margin: 0 0 4px; }
        .role-head .tag { font-size: 14px; letter-spacing: .04em; font-weight: 800; line-height: 1.2; color: transparent; white-space: nowrap; }
        .role-head .tag-gm { color: #9a3058; }
        .role-head .tag-pastor { color: #1f4f96; }
        .role-head .tag-gospel { color: #5b349e; }
        .srow.tint-gm, .srow.tint-pastor, .srow.tint-gospel { border-radius: 8px; }
        .srow.tint-gm { background: #f8eef2; }
        .srow.tint-pastor { background: #eef3fb; }
        .srow.tint-gospel { background: #f4eefb; }
        .srow { display: flex; align-items: flex-end; gap: 4px; margin: 4px 0; }
        .rn { width: 26px; text-align: right; font-size: 13px; color: #8a8172; font-weight: 700; padding-bottom: 8px; }
        .seats { display: flex; gap: 4px; align-items: flex-end; }
        .passage { margin: 8px 0; text-align: center; font-size: 13px; letter-spacing: .16em; font-weight: 800; color: #8a8172; }
        .passage.is-quiet { visibility: hidden; }
        .premium-box { background: #fbf6e6; border: 1px solid #e4d3a2; border-radius: 12px; padding: 6px 4px 2px; margin-bottom: 6px; }
        .premium-tag { display: flex; align-items: center; justify-content: center; gap: 4px; margin: 0 0 4px; font-size: 9px; letter-spacing: .08em; font-weight: 800; color: #6b5420; }
        .crown-mini { width: 12px; height: 8px; background: #e4c16a; clip-path: polygon(0 100%, 16% 28%, 32% 100%, 50% 0, 68% 100%, 84% 28%, 100% 100%); }
        .seat {
            position: relative; width: 34px; height: 46px; padding: 0; border: 0; background: transparent;
            cursor: pointer; flex: none; font-family: "Source Sans Pro", sans-serif;
        }
        .seat .crown { display: none; position: absolute; top: 0; left: 50%; width: 14px; height: 8px; transform: translateX(-50%); background: #e4c16a; clip-path: polygon(0 100%, 16% 28%, 32% 100%, 50% 0, 68% 100%, 84% 28%, 100% 100%); }
        .seat.is-premium .crown { display: block; }
        .seat .back {
            position: absolute; left: 5px; right: 5px; top: 8px; height: 14px;
            background: #f7f1e6; border: 2px solid #c9a45a; border-bottom: 0; border-radius: 8px 8px 2px 2px;
        }
        .seat .cushion {
            position: absolute; left: 1px; right: 1px; top: 20px; height: 16px; z-index: 1;
            display: flex; align-items: center; justify-content: center;
            background: #fffaf3; border: 2px solid #c9a45a; border-radius: 3px 3px 6px 6px;
            color: #1d2433; font-size: 12px; font-weight: 800;
        }
        .seat .legs {
            position: absolute; left: 6px; right: 6px; bottom: 2px; height: 7px;
            background: linear-gradient(#c9a45a, #c9a45a) left / 3px 100% no-repeat, linear-gradient(#c9a45a, #c9a45a) right / 3px 100% no-repeat;
        }
        .seat.is-premium .back { top: 7px; height: 16px; background: linear-gradient(#6d42b8, #4a2888); border-color: #e4c16a; }
        .seat.is-premium .cushion { background: linear-gradient(#5b349e, #3e2174); border-color: #e4c16a; color: #fff; }
        .seat.is-gm .back, .seat.is-gm .cushion { background: linear-gradient(#9a3058, #6d1d3c); border-color: #e4c16a; color: #fff; }
        .seat.is-pastor .back, .seat.is-pastor .cushion { background: linear-gradient(#1f4f96, #16386c); border-color: #e4c16a; color: #fff; }
        .foh-slot { width: 34px; height: 46px; flex: none; }
        .foh {
            position: absolute; z-index: 4; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: #14182b; color: #e4c16a; border: 2px solid #c9a45a; border-radius: 14px;
            font-weight: 800; letter-spacing: .18em; font-size: 18px; pointer-events: none;
        }
        .foh span { display: block; margin-top: 4px; letter-spacing: .06em; font-size: 11px; color: #fff; font-weight: 700; }
        .band-gm { background: #f8eef2; border-color: #e4c0ce; }
        .band-pastor { background: #eef3fb; border-color: #c5d4ee; }
        .band-gospel { background: #f4eefb; border-color: #d4c6ee; }
        .seat .check, .seat .lock, .seat .x { display: none; }
        .seat.is-on .num, .seat.is-reserved .num, .seat.is-taken .num { display: none; }
        .seat.is-on .check, .seat.is-reserved .lock, .seat.is-taken .x { display: inline; }
        .seat.is-on .back, .seat.is-on .cushion { background: linear-gradient(#f8e7b0, #e4c16a); border-color: #a8842a; color: #241c08; }
        .seat.is-on .crown { background: #8a6a24; }
        .seat.is-taken .back, .seat.is-taken .cushion { background: #d9d3cb; border-color: #b7b0a6; color: #6e655c; }
        .seat.is-taken .crown, .seat.is-taken .legs { filter: grayscale(1); opacity: .55; }
        .seat:disabled { cursor: not-allowed; }
        .legend { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: center; margin: 12px 4px 0; color: #6d6458; font-size: 15px; font-weight: 700; }
        .legend .key { display: inline-flex; align-items: center; gap: 2px; }
        .legend .seat { transform: scale(.62); transform-origin: left center; margin-right: -8px; pointer-events: none; }
        .id-note { margin-right: auto; font-weight: 700; color: #8a8172; }
        .panel {
            background: var(--navy); color: #fff; border-radius: 16px; padding: 16px 16px 14px;
            box-shadow: 0 10px 30px rgba(16, 18, 32, .18);
        }
        .panel .k { margin: 0; font-size: 13px; color: #c9c3b6; }
        .pretty { margin: 2px 0 8px; font-family: "Playfair Display", serif; font-size: 40px; line-height: 1; color: var(--gold); min-height: 44px; }
        .kind { display: inline-flex; align-items: center; gap: 6px; margin: 0 0 12px; border: 1px solid var(--gold); color: var(--gold); border-radius: 999px; padding: 3px 10px; font-size: 11px; letter-spacing: .14em; font-weight: 800; }
        .kind[hidden] { display: none; }
        .kind.is-standard .crown-mini, .kind.is-guest .crown-mini { display: none; }
        .facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin-bottom: 12px; }
        .facts b { display: block; font-size: 18px; }
        .facts span, .status-label, .name-label { display: block; font-size: 12px; color: #b7b1a4; }
        .status { display: inline-block; margin-top: 4px; background: #f3d98a; color: #3a2e08; border-radius: 999px; padding: 4px 10px; font-weight: 800; font-size: 13px; }
        .name-box { margin-top: 6px; border: 1px solid rgba(255,255,255,.2); border-radius: 10px; padding: 10px 12px; font-weight: 700; }
        .hint { margin: 10px 0 0; color: #b7b1a4; font-size: 13px; line-height: 1.35; }
        .go, .clear {
            width: 100%; border-radius: 10px; min-height: 44px; font: 700 15px "Source Sans Pro", sans-serif; cursor: pointer;
        }
        .go { margin-top: 14px; border: 0; background: linear-gradient(#f0d48a, #d7b15a); color: #241c08; }
        .go:disabled { opacity: .45; cursor: not-allowed; }
        .clear { margin-top: 8px; background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.35); }
        @media (max-width: 900px) {
            .sheet { margin: 0; border-radius: 0; }
            .top { flex-wrap: wrap; gap: 8px; padding: 14px; }
            .brand { border-right: 0; padding-right: 0; }
            .top h1 { font-size: 24px; }
            .view { margin-left: 0; }
            .swipe { display: block; }
            .layout { grid-template-columns: 1fr; padding-bottom: 230px; }
            .panel { position: fixed; left: 8px; right: 8px; bottom: 8px; z-index: 5; max-height: 38vh; overflow: auto; padding: 12px; }
            .pretty { font-size: 28px; min-height: 32px; }
            .facts { margin-bottom: 8px; }
            .hint { margin-top: 6px; }
        }
    </style>
</head>
<body>
@php
    $takenMap = array_fill_keys($taken, true);
    $roleLabel = \App\Support\CaroSeats::roleLabel($guest['role']);
    $autoCode = \App\Support\CaroSeats::firstOpen($guest['role'], $taken);
    $note = 'Your first open '.$roleLabel.' seat is already selected. Keep it, or choose another.';
@endphp
<div class="sheet">
    <header class="top">
        <div class="brand">
            <strong>MSATA 2026</strong>
            <span>MY SACRIFICE AT THE ALTAR</span>
        </div>
        <div>
            <h1>Choose your seat</h1>
            <p>8 November 2026 · Presbyterian Hall Ntamulung</p>
        </div>
        <a class="view" href="{{ $helpUrl }}">How to book</a>
        <a class="view" href="{{ $homeUrl }}">View invitation</a>
    </header>
    @if($error)<p class="err">{{ $error }}</p>@endif
    <form method="POST" action="{{ $claimUrl }}" id="seatForm">
        @csrf
        <input type="hidden" name="name" value="{{ $guest['name'] }}">
        <input type="hidden" name="country_code" value="{{ $guest['country_code'] }}">
        <input type="hidden" name="phone" value="{{ $guest['local_phone'] }}">
        <input type="hidden" name="role" value="{{ $guest['role'] }}">
        <input type="hidden" name="seat_code" id="seatCode" value="">
        <div class="layout">
            <div class="map">
                <p class="swipe">Swipe sideways to see sections A to D</p>
                <div class="hall" id="hall">
                    <div class="hall-inner">
                    <div class="stage"><span class="pin">STAGE</span></div>
                    <div class="front"><span class="pin">FRONT</span></div>
                    <div class="foh" id="foh">FOH<span>Sound engineer</span></div>
                    <div class="sections">
                        @foreach($columns as $column)
                            <section class="section">
                                <h2>SECTION {{ $column }}</h2>
                                <div class="role-head">
                                    @if($column === 'B')
                                        <span class="tag tag-gm">GUEST MINISTERS · ROW 01</span>
                                        <span class="tag tag-pastor">PASTORS · ROWS 02–03</span>
                                    @elseif($column === 'C')
                                        <span class="tag tag-gospel">GOSPEL MINISTERS</span>
                                        <span class="tag tag-gospel">ROWS 01–03</span>
                                    @else
                                        <span class="tag">.</span>
                                        <span class="tag">.</span>
                                    @endif
                                </div>
                                @foreach($rows as $row)
                                    @if($row === 11)
                                        <div class="passage {{ ($column === 'B' || $column === 'C') ? 'is-quiet' : '' }}">PASSAGE</div>
                                    @endif
                                    @php
                                        $tint = '';
                                        if ($column === 'B' && $row === 1) {
                                            $tint = ' tint-gm';
                                        } elseif ($column === 'B' && ($row === 2 || $row === 3)) {
                                            $tint = ' tint-pastor';
                                        } elseif ($column === 'C' && $row <= 3) {
                                            $tint = ' tint-gospel';
                                        }
                                    @endphp
                                    <div class="srow{{ $tint }}">
                                        <span class="rn">{{ sprintf('%02d', $row) }}</span>
                                        <span class="seats">
                                            @for($chair = 1; $chair <= 8; $chair++)
                                                @php
                                                    $code = \App\Support\CaroSeats::code($column, $row, $chair);
                                                    $removed = \App\Support\CaroSeats::removed($code);
                                                    $takenSeat = ! empty($takenMap[$code]);
                                                    $owner = \App\Support\CaroSeats::ownerRole($code);
                                                    $open = ! $removed && \App\Support\CaroSeats::allowed($guest['role'], $code) && ! $takenSeat;
                                                    $tone = $owner === 'guest_minister' ? 'gm' : ($owner === 'pastor' ? 'pastor' : ($owner === 'gospel_minister' ? 'gospel' : 'guest'));
                                                    $classes = 'seat'.($tone === 'guest' ? '' : ' is-premium is-'.$tone);
                                                    if ($takenSeat) {
                                                        $classes .= ' is-taken';
                                                    } elseif (! $open && ! $removed) {
                                                        $classes .= ' is-reserved';
                                                    }
                                                    $pretty = $column.'-'.sprintf('%02d', $row).'-'.sprintf('%02d', $chair);
                                                    $fohStart = $column === 'B' && $row === 10 && $chair === 4;
                                                    $fohEnd = $column === 'C' && $row === 11 && $chair === 5;
                                                    if ($takenSeat) {
                                                        $title = $pretty.' is occupied';
                                                    } elseif (! $open) {
                                                        $title = $pretty.' is reserved for '.\App\Support\CaroSeats::roleLabel($owner);
                                                    } else {
                                                        $title = $pretty;
                                                    }
                                                @endphp
                                                @if($removed)
                                                    <span class="foh-slot{{ $fohStart ? ' foh-start' : '' }}{{ $fohEnd ? ' foh-end' : '' }}"></span>
                                                @else
                                                <button class="{{ $classes }}" type="button" data-code="{{ $code }}" data-kind="{{ $tone }}" data-section="{{ $column }}" data-row="{{ sprintf('%02d', $row) }}" data-chair="{{ sprintf('%02d', $chair) }}" data-pretty="{{ $pretty }}" data-auto="{{ $code === $autoCode ? '1' : '0' }}" title="{{ $title }}" aria-label="{{ $title }}" {{ $open ? '' : 'disabled' }}>
                                                    <span class="crown" aria-hidden="true"></span>
                                                    <span class="back"></span>
                                                    <span class="cushion">
                                                        <span class="num">{{ $chair }}</span>
                                                        <span class="check" aria-hidden="true">✓</span>
                                                        <span class="lock" aria-hidden="true">🔒</span>
                                                        <span class="x" aria-hidden="true">×</span>
                                                    </span>
                                                    <span class="legs"></span>
                                                </button>
                                                @endif
                                            @endfor
                                        </span>
                                    </div>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                    </div>
                </div>
                <p class="legend">
                    <span class="id-note">Seat ID = section · row · seat</span>
                    <span class="key"><span class="seat demo is-premium is-gm" aria-hidden="true"><span class="crown"></span><span class="back"></span><span class="cushion"><span class="num">1</span></span><span class="legs"></span></span> Guest Minister</span>
                    <span class="key"><span class="seat demo is-premium is-pastor" aria-hidden="true"><span class="crown"></span><span class="back"></span><span class="cushion"><span class="num">1</span></span><span class="legs"></span></span> Pastor</span>
                    <span class="key"><span class="seat demo is-premium is-gospel" aria-hidden="true"><span class="crown"></span><span class="back"></span><span class="cushion"><span class="num">1</span></span><span class="legs"></span></span> Gospel Minister</span>
                    <span class="key"><span class="seat demo" aria-hidden="true"><span class="back"></span><span class="cushion"><span class="num">1</span></span><span class="legs"></span></span> Standard</span>
                    <span class="key"><span class="seat demo is-reserved" aria-hidden="true"><span class="back"></span><span class="cushion"><span class="lock">🔒</span></span><span class="legs"></span></span> Reserved</span>
                    <span class="key"><span class="seat demo is-taken" aria-hidden="true"><span class="back"></span><span class="cushion"><span class="x">×</span></span><span class="legs"></span></span> Occupied</span>
                    <span class="key"><span class="seat demo is-on" aria-hidden="true"><span class="back"></span><span class="cushion"><span class="check">✓</span></span><span class="legs"></span></span> Selected</span>
                </p>
            </div>
            <aside class="panel">
                <p class="k">Selected seat</p>
                <div class="pretty" id="pretty">—</div>
                <div class="kind" id="kind" hidden><span class="crown-mini" aria-hidden="true"></span><b id="kindLabel">PREMIUM</b></div>
                <div class="facts">
                    <div><span>Section</span><b id="sec">—</b></div>
                    <div><span>Row</span><b id="rowN">—</b></div>
                    <div><span>Seat</span><b id="chairN">—</b></div>
                    <div><span class="status-label">Status</span><span class="status" id="status">Waiting</span></div>
                </div>
                <span class="name-label">Guest name</span>
                <div class="name-box">{{ $guest['name'] }}</div>
                <p class="hint">{{ $note }}</p>
                <button class="go" type="submit" id="goBtn" disabled>Assign seat</button>
                <button class="clear" type="button" id="clearBtn">Clear selection</button>
            </aside>
        </div>
    </form>
</div>
<script>
(function () {
    var input = document.getElementById('seatCode');
    var pretty = document.getElementById('pretty');
    var sec = document.getElementById('sec');
    var rowN = document.getElementById('rowN');
    var chairN = document.getElementById('chairN');
    var status = document.getElementById('status');
    var go = document.getElementById('goBtn');
    var kind = document.getElementById('kind');
    var kindLabel = document.getElementById('kindLabel');
    function clearSeat() {
        document.querySelectorAll('button.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
        input.value = '';
        pretty.textContent = '—';
        sec.textContent = '—';
        rowN.textContent = '—';
        chairN.textContent = '—';
        status.textContent = 'Waiting';
        kind.hidden = true;
        go.disabled = true;
    }
    document.querySelectorAll('button.seat:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            var tone = button.getAttribute('data-kind') || 'guest';
            var labels = { gm: 'GUEST MINISTER', pastor: 'PASTOR', gospel: 'GOSPEL MINISTER', guest: 'STANDARD' };
            document.querySelectorAll('button.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
            button.classList.add('is-on');
            input.value = button.getAttribute('data-code');
            pretty.textContent = button.getAttribute('data-pretty');
            sec.textContent = button.getAttribute('data-section');
            rowN.textContent = button.getAttribute('data-row');
            chairN.textContent = button.getAttribute('data-chair');
            status.textContent = 'Available';
            kind.hidden = false;
            kind.className = 'kind is-' + tone;
            kindLabel.textContent = labels[tone] || 'STANDARD';
            go.disabled = false;
        });
    });
    var autoSeat = document.querySelector('button.seat[data-auto="1"]');
    if (autoSeat) autoSeat.click();
    function placeFoh() {
        var hall = document.getElementById('hall');
        var inner = hall.querySelector('.hall-inner');
        var start = hall.querySelector('.foh-start');
        var end = hall.querySelector('.foh-end');
        var box = document.getElementById('foh');
        if (!inner || !start || !end) return;
        var innerBox = inner.getBoundingClientRect();
        var a = start.getBoundingClientRect();
        var b = end.getBoundingClientRect();
        box.style.left = (a.left - innerBox.left) + 'px';
        box.style.top = (a.top - innerBox.top) + 'px';
        box.style.width = Math.max(40, b.right - a.left) + 'px';
        box.style.height = Math.max(40, b.bottom - a.top) + 'px';
    }
    function pinLabels() {
        var hall = document.getElementById('hall');
        hall.querySelectorAll('.pin').forEach(function (pin) {
            pin.style.left = hall.scrollLeft + 'px';
            pin.style.width = hall.clientWidth + 'px';
        });
    }
    placeFoh();
    pinLabels();
    window.addEventListener('resize', function () { placeFoh(); pinLabels(); });
    window.addEventListener('load', function () { placeFoh(); pinLabels(); });
    document.getElementById('hall').addEventListener('scroll', pinLabels);
    document.getElementById('clearBtn').addEventListener('click', clearSeat);
    document.getElementById('seatForm').addEventListener('submit', function (event) {
        if (!input.value) {
            event.preventDefault();
            status.textContent = 'Choose a seat';
        }
    });
})();
</script>
</body>
</html>
