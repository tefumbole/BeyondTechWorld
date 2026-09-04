@extends('beyond.memorial.remember-layout')

@section('title', 'Funeral program · Pa Ngwayu Francis')

@section('styles')
        .program-hero { margin: 0 0 28px; }
        .program-hero h1 { margin-bottom: 10px; }
        .program-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 8px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            border: 1px solid #5a4a22;
            background: rgba(212,175,55,.08);
            color: var(--gold-2);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .timeline {
            position: relative;
            display: grid;
            gap: 22px;
            padding-left: 8px;
        }
        .timeline:before {
            content: "";
            position: absolute;
            left: 27px;
            top: 28px;
            bottom: 28px;
            width: 2px;
            background: linear-gradient(180deg, #d4af37, rgba(212,175,55,.12));
        }
        .part {
            position: relative;
            display: grid;
            grid-template-columns: 56px 1fr;
            gap: 16px;
            align-items: start;
        }
        .part-num {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
            font-weight: 700;
            color: #1a1408;
            background: linear-gradient(180deg, #f0d57a, #d4af37);
            box-shadow: 0 0 0 6px rgba(212,175,55,.12);
            z-index: 1;
        }
        .part-card {
            background: linear-gradient(180deg, #221a12, #16110c);
            border: 1px solid #4a3b1c;
            border-radius: 22px;
            padding: 22px 22px 20px;
            box-shadow: 0 18px 40px rgba(0,0,0,.28);
        }
        .part-card h2 {
            font-size: clamp(26px, 4vw, 34px);
            margin: 0 0 8px;
        }
        .part-time {
            display: inline-block;
            margin: 0 0 10px;
            color: var(--gold);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .part-card p {
            margin: 0;
            color: #e8dcc0;
            font-size: 18px;
            line-height: 1.55;
        }
        .venue {
            margin-top: 12px;
            color: var(--gold-2);
            font-weight: 700;
            font-size: 15px;
        }
        .service-list {
            margin: 18px 0 0;
            display: grid;
            gap: 8px;
        }
        .service-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            border-radius: 14px;
            background: rgba(255,248,232,.03);
            border: 1px solid rgba(212,175,55,.12);
            color: #f3ead6;
            font-size: 16px;
        }
        .service-row b {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(212,175,55,.14);
            color: var(--gold-2);
            font-size: 12px;
        }
        .service-row span {
            flex: 1;
            min-width: 0;
            line-height: 1.45;
        }
        .service-row a { color: var(--gold-2); }
        @media (max-width: 520px) {
            .timeline:before { left: 23px; }
            .part { grid-template-columns: 48px 1fr; gap: 12px; }
            .part-num { width: 48px; height: 48px; font-size: 18px; }
            .part-card { padding: 16px 14px 14px; border-radius: 18px; }
        }
@endsection

@section('content')
    <header class="program-hero">
        <p class="kicker">Saturday 26 September 2026</p>
        <h1>Funeral program</h1>
        <div class="program-meta">
            <span class="chip">Nkwen Baptist Church</span>
            <span class="chip">Pa Ngwayu Francis</span>
            <span class="chip">1953 — 2026</span>
        </div>
    </header>

    <div class="timeline">
        <article class="part">
            <div class="part-num">01</div>
            <div class="part-card">
                <span class="part-time">7:00 AM — 8:00 AM</span>
                <h2>Part 1</h2>
                <p>Removal of mortal remains and transportation to Nkwen Baptist Church.</p>
            </div>
        </article>

        <article class="part">
            <div class="part-num">02</div>
            <div class="part-card">
                <span class="part-time">Church service</span>
                <h2>Part 2</h2>
                <p>Church service at Nkwen Baptist Church.</p>
                <div class="venue">Nkwen Baptist Church</div>
                <div class="service-list">
                    <div class="service-row"><b>01</b><span>Prelude and processional</span></div>
                    <div class="service-row"><b>02</b><span>Opening prayer</span></div>
                    <div class="service-row"><b>03</b><span>Opening hymn — <a href="{{ route('funeral.pangwayu.hymns') }}">When the Trumpet of the Lord Shall Sound</a></span></div>
                    <div class="service-row"><b>04</b><span>Scripture reading</span></div>
                    <div class="service-row"><b>05</b><span>Biography and tributes</span></div>
                    <div class="service-row"><b>06</b><span>Eulogies — <a href="{{ route('funeral.pangwayu.remember') }}#eulogies">write one here</a></span></div>
                    <div class="service-row"><b>07</b><span>Sermon</span></div>
                    <div class="service-row"><b>08</b><span>Closing hymn — <a href="{{ route('funeral.pangwayu.hymns') }}">It Is Well With My Soul</a></span></div>
                    <div class="service-row"><b>09</b><span>Closing prayer and benediction</span></div>
                    <div class="service-row"><b>10</b><span>Recessional</span></div>
                </div>
            </div>
        </article>
    </div>
@endsection
