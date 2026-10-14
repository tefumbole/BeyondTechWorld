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
        .hall { overflow-x: auto; padding-bottom: 6px; }
        .stage {
            width: min(460px, 78%); margin: 4px auto 0; text-align: center;
            background: linear-gradient(#1c2744, #12182c); color: var(--gold);
            border: 2px solid #c9a45a; border-top: 0; border-radius: 0 0 90px 90px;
            padding: 12px 24px 16px; letter-spacing: .28em; font-weight: 800; font-size: 13px;
        }
        .front { display: flex; align-items: center; gap: 10px; justify-content: center; margin: 8px 0 12px; color: #8a8172; font-size: 11px; letter-spacing: .22em; font-weight: 800; }
        .front:before, .front:after { content: ""; width: 72px; height: 1px; background: #d9c48a; }
        .sections { display: flex; gap: 18px; min-width: max-content; justify-content: center; }
        .section { min-width: 0; }
        .section h2 { margin: 0 0 8px; text-align: center; font-size: 12px; letter-spacing: .14em; color: #5c564c; }
        .srow { display: flex; align-items: center; gap: 4px; margin: 3px 0; }
        .rn { width: 22px; text-align: right; font-size: 11px; color: #8a8172; font-weight: 700; }
        .seats { display: flex; gap: 3px; }
        .passage { margin: 8px 0; text-align: center; font-size: 11px; letter-spacing: .16em; font-weight: 800; color: #8a8172; }
        .seat {
            width: 28px; height: 26px; padding: 0; border-radius: 6px; border: 1px solid #e4d8c4;
            background: #fffaf3; color: var(--ink); font: 700 11px/1 "Source Sans Pro", sans-serif; cursor: pointer;
        }
        .seat .check { display: none; }
        .seat.is-reserved { background: #ddd4f3; border-color: #c9bcec; color: #5c4d86; cursor: not-allowed; }
        .seat.is-taken { background: #f3efe8; border-color: #e0d8cc; color: #9a9186; cursor: not-allowed; }
        .seat.is-on { background: #f3d98a; border-color: #c9a227; color: #140b2e; box-shadow: inset 0 0 0 1px #c9a227; }
        .seat.is-on .num { display: none; }
        .seat.is-on .check { display: inline; }
        .legend { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin: 12px 4px 0; color: #6d6458; font-size: 13px; font-weight: 700; }
        .legend i { display: inline-flex; width: 18px; height: 16px; border-radius: 4px; align-items: center; justify-content: center; margin-right: 4px; vertical-align: -3px; font-style: normal; font-size: 11px; border: 1px solid #e4d8c4; background: #fffaf3; }
        .legend .lg-res { background: #ddd4f3; border-color: #c9bcec; color: #5c4d86; }
        .legend .lg-occ { color: #9a9186; }
        .legend .lg-sel { background: #f3d98a; border-color: #c9a227; }
        .panel {
            background: var(--navy); color: #fff; border-radius: 16px; padding: 16px 16px 14px;
            box-shadow: 0 10px 30px rgba(16, 18, 32, .18);
        }
        .panel .k { margin: 0; font-size: 13px; color: #c9c3b6; }
        .pretty { margin: 2px 0 12px; font-family: "Playfair Display", serif; font-size: 40px; line-height: 1; color: var(--gold); min-height: 44px; }
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
            .layout { grid-template-columns: 1fr; padding-bottom: 250px; }
            .panel { position: fixed; left: 8px; right: 8px; bottom: 8px; z-index: 5; }
            .pretty { font-size: 32px; }
        }
    </style>
</head>
<body>
@php
    $takenMap = array_fill_keys($taken, true);
    $roleLabel = \App\Support\CaroSeats::roleLabel($guest['role']);
    $note = $guest['role'] === 'guest'
        ? 'Choose an open seat. Locked seats are reserved.'
        : 'You can only choose the seats reserved for '.$roleLabel.'. Locked seats belong to another group.';
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
                <div class="hall" id="hall">
                    <div class="stage">STAGE</div>
                    <div class="front">FRONT</div>
                    <div class="sections">
                        @foreach($columns as $column)
                            <section class="section">
                                <h2>SECTION {{ $column }}</h2>
                                @foreach($rows as $row)
                                    @if($row === 11)
                                        <div class="passage">PASSAGE</div>
                                    @endif
                                    <div class="srow">
                                        <span class="rn">{{ sprintf('%02d', $row) }}</span>
                                        <span class="seats">
                                            @for($chair = 1; $chair <= 8; $chair++)
                                                @php
                                                    $code = \App\Support\CaroSeats::code($column, $row, $chair);
                                                    $takenSeat = ! empty($takenMap[$code]);
                                                    $open = \App\Support\CaroSeats::allowed($guest['role'], $code) && ! $takenSeat;
                                                    $owner = \App\Support\CaroSeats::ownerRole($code);
                                                    $classes = 'seat';
                                                    if ($takenSeat) {
                                                        $classes .= ' is-taken';
                                                    } elseif (! $open) {
                                                        $classes .= ' is-reserved';
                                                    }
                                                    $pretty = $column.'-'.sprintf('%02d', $row).'-'.sprintf('%02d', $chair);
                                                    if ($takenSeat) {
                                                        $title = $pretty.' is occupied';
                                                    } elseif (! $open) {
                                                        $title = $pretty.' is reserved for '.\App\Support\CaroSeats::roleLabel($owner);
                                                    } else {
                                                        $title = $pretty;
                                                    }
                                                @endphp
                                                <button class="{{ $classes }}" type="button" data-code="{{ $code }}" data-section="{{ $column }}" data-row="{{ sprintf('%02d', $row) }}" data-chair="{{ sprintf('%02d', $chair) }}" data-pretty="{{ $pretty }}" title="{{ $title }}" aria-label="{{ $title }}" {{ $open ? '' : 'disabled' }}>
                                                    @if($takenSeat)
                                                        <span>×</span>
                                                    @elseif(! $open)
                                                        <span aria-hidden="true">🔒</span>
                                                    @else
                                                        <span class="num">{{ $chair }}</span>
                                                        <span class="check" aria-hidden="true">✓</span>
                                                    @endif
                                                </button>
                                            @endfor
                                        </span>
                                    </div>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                </div>
                <p class="legend">
                    <span><i></i>Available</span>
                    <span><i class="lg-res">🔒</i>Reserved</span>
                    <span><i class="lg-occ">×</i>Occupied</span>
                    <span><i class="lg-sel">✓</i>Selected</span>
                </p>
            </div>
            <aside class="panel">
                <p class="k">Selected seat</p>
                <div class="pretty" id="pretty">—</div>
                <div class="facts">
                    <div><span>Section</span><b id="sec">—</b></div>
                    <div><span>Row</span><b id="rowN">—</b></div>
                    <div><span>Seat</span><b id="chairN">—</b></div>
                    <div><span class="status-label">Status</span><span class="status" id="status">Waiting</span></div>
                </div>
                <span class="name-label">Guest name</span>
                <div class="name-box">{{ $guest['name'] }}</div>
                <p class="hint">{{ $note }}</p>
                <button class="go" type="submit" id="goBtn" disabled>Get my invitation</button>
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
    function clearSeat() {
        document.querySelectorAll('button.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
        input.value = '';
        pretty.textContent = '—';
        sec.textContent = '—';
        rowN.textContent = '—';
        chairN.textContent = '—';
        status.textContent = 'Waiting';
        go.disabled = true;
    }
    document.querySelectorAll('button.seat:not(:disabled)').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('button.seat.is-on').forEach(function (item) { item.classList.remove('is-on'); });
            button.classList.add('is-on');
            input.value = button.getAttribute('data-code');
            pretty.textContent = button.getAttribute('data-pretty');
            sec.textContent = button.getAttribute('data-section');
            rowN.textContent = button.getAttribute('data-row');
            chairN.textContent = button.getAttribute('data-chair');
            status.textContent = 'Selected';
            go.disabled = false;
        });
    });
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
