@extends('beyond.memorial.remember-layout')

@section('title', 'Eulogies · Late Pa Ngwayu Nchinda Francis')

@section('styles')
        body.is-eulogies .main {
            background: #14100c;
            color: #f7f1e4;
            padding: 0 0 12px;
        }
        body.is-eulogies .foot { color: #8a7b62; padding: 8px 20px 28px; }
        body.is-eulogies .foot a { color: #f0d57a; }
        .eu-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #221a12, #16110c);
            color: #fff8e8;
            border-bottom: 1px solid rgba(212,175,55,.22);
        }
        .eu-hero:after {
            content: "“";
            position: absolute;
            right: 6%;
            top: -90px;
            font-family: "Cormorant Garamond", serif;
            font-size: 420px;
            line-height: 1;
            color: rgba(212,175,55,.06);
            pointer-events: none;
        }
        .eu-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 72px 28px 80px;
        }
        .eu-eyebrow {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .26em;
            text-transform: uppercase;
            color: #d4af37;
        }
        .eu-hero h1 {
            margin: 14px 0 16px;
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: clamp(52px, 7vw, 92px);
            letter-spacing: -.04em;
            line-height: 1.05;
            color: #fff8e8;
        }
        .eu-hero p {
            max-width: 560px;
            margin: 0;
            color: #e8dcc0;
            font-size: 18px;
            line-height: 1.75;
        }
        .eu-stats {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-top: 32px;
        }
        .eu-stats strong {
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: 40px;
            line-height: 1;
            color: #f0d57a;
        }
        .eu-stats span {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #c4b498;
            line-height: 1.45;
        }
        .eu-note {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 28px 18px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .eu-note span {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #d4af37;
        }
        .eu-note .line { height: 1px; flex: 1; background: rgba(212,175,55,.35); }
        .eu-collection {
            max-width: 1100px;
            margin: 0 auto;
            padding: 8px 28px 64px;
        }
        .eu-featured {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 48px;
            margin-bottom: 22px;
            padding: 8px 8px 28px;
        }
        .eu-aside {
            border-right: 1px solid rgba(212,175,55,.28);
            padding: 18px 28px 0 0;
        }
        .eu-aside span {
            display: block;
            color: #d4af37;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .eu-aside p {
            margin: 18px 0 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            line-height: 1.35;
            color: #fff8e8;
        }
        .eu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .eu-card {
            background: linear-gradient(180deg, rgba(32,24,16,.92), rgba(16,12,10,.88));
            border: 1px solid rgba(212,175,55,.22);
            border-radius: 20px;
            box-shadow: 0 16px 40px rgba(0,0,0,.28);
            margin: 0;
            padding: 32px 36px 36px;
        }
        .eu-featured .eu-card {
            background: linear-gradient(180deg, rgba(32,24,16,.92), rgba(16,12,10,.88));
        }
        .eu-top {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(212,175,55,.18);
        }
        .eu-avatar {
            width: 49px;
            height: 49px;
            flex: none;
            border: 2px solid #d4af37;
            border-radius: 50%;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: rgba(212,175,55,.12);
            color: #f0d57a;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
        }
        .eu-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .eu-byline { flex: 1; min-width: 0; }
        .eu-byline h2 {
            margin: 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 20px;
            font-weight: 600;
            line-height: 1.3;
            color: #fff6dc;
        }
        .eu-byline time {
            display: block;
            margin-top: 2px;
            color: #c4b498;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .eu-num {
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            color: rgba(212,175,55,.55);
        }
        .eu-body { max-width: 820px; margin: 22px auto 0; }
        .eu-grid .eu-body { margin-left: 0; }
        .eu-body p {
            margin: 0 0 16px;
            font-size: 16px;
            line-height: 1.95;
            color: #f6edd8;
        }
        .eu-grid .eu-body p { font-size: 15px; }
        .eu-body p:last-child { margin-bottom: 0; }
        .eu-sign {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid rgba(212,175,55,.18);
        }
        .eu-sign cite {
            font-family: "Cormorant Garamond", serif;
            font-style: italic;
            font-size: 18px;
            color: #fff8e8;
        }
        .eu-sign img {
            height: 52px;
            max-width: 180px;
            object-fit: contain;
            filter: invert(1) sepia(.28) saturate(1.8) hue-rotate(6deg) brightness(1.2);
        }
        .eu-more {
            margin: 28px 0 0;
            text-align: center;
        }
        .eu-more a {
            color: #f0d57a;
            font-weight: 700;
            text-underline-offset: 4px;
        }
        .eu-empty {
            background: #1c160f;
            border: 1px dashed #6a5420;
            border-radius: 16px;
            padding: 28px 22px;
            color: #c4b498;
            font-size: 17px;
        }
        @media (max-width: 900px) {
            .eu-featured { grid-template-columns: 1fr; gap: 8px; }
            .eu-aside { border-right: 0; border-bottom: 1px solid rgba(212,175,55,.28); padding: 0 0 14px; }
            .eu-aside p { margin: 10px 0 0; font-size: 24px; }
            .eu-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .eu-hero-inner { padding: 48px 20px 56px; }
            .eu-hero p { font-size: 16px; }
            .eu-note, .eu-collection { padding-left: 18px; padding-right: 18px; }
            .eu-card { padding: 22px 18px 24px; }
            .eu-num { font-size: 22px; }
        }
@endsection

@section('content')
    @php
        $euCount = count($eulogies);
        $pull = '';
        if ($euCount) {
            $flat = trim(preg_replace('/\s+/', ' ', implode(' ', $eulogies[0]['paragraphs'] ?? [])));
            if ($flat !== '' && preg_match('/^(.+?[.!?])(?:\s|$)/u', $flat, $m)) {
                $len = function_exists('mb_strlen') ? mb_strlen($m[1]) : strlen($m[1]);
                if ($len >= 18 && $len <= 180) {
                    $pull = $m[1];
                }
            }
        }
    @endphp

    <section class="eu-hero">
        <div class="eu-hero-inner">
            <p class="eu-eyebrow">Words that remain with us</p>
            <h1>Eulogies</h1>
            <p>Memories, gratitude and farewell words from the people whose lives Pa Ngwayu Nchinda Francis touched.</p>
            <div class="eu-stats">
                <strong>{{ $euCount }}</strong>
                <span>{{ $euCount === 1 ? 'Eulogy written' : 'Eulogies written' }}<br>Submissions closed</span>
            </div>
        </div>
    </section>

    <div class="eu-note">
        <span>Tributes written for Pa Ngwayu Francis</span>
        <div class="line"></div>
    </div>

    <div class="eu-collection">
        @if($euCount)
            <div class="eu-featured">
                <aside class="eu-aside">
                    <span>Featured tribute
                        @if(!empty($eulogies[0]['when']))
                            · {{ $eulogies[0]['when'] }}
                        @endif
                    </span>
                    @if($pull !== '')
                        <p>“{{ $pull }}”</p>
                    @endif
                </aside>
                @include('beyond.memorial.eulogy-card', ['eu' => $eulogies[0], 'num' => 1])
            </div>
            @if($euCount > 1)
                <div class="eu-grid">
                    @foreach(array_slice($eulogies, 1) as $offset => $eu)
                        @include('beyond.memorial.eulogy-card', ['eu' => $eu, 'num' => $offset + 2])
                    @endforeach
                </div>
            @endif
        @else
            <p class="eu-empty">The eulogies written for Pa Ngwayu Francis are kept here.</p>
        @endif
        <p class="eu-more"><a href="{{ route('funeral.pangwayu.biography') }}">Read his biography</a></p>
    </div>
@endsection
