@extends('beyond.memorial.remember-layout')

@section('title', 'Eulogies · Late Pa Ngwayu Nchinda Francis')

@section('styles')
        body.is-eulogies .main {
            background: #f3f2eb;
            color: #29352d;
            padding: 0 0 12px;
        }
        body.is-eulogies .foot {
            background: #263c31;
            color: #eaece3;
            margin: 0;
            padding: 35px 20px;
        }
        body.is-eulogies .foot a { color: #e5c385; }
        .eu-hero {
            position: relative;
            overflow: hidden;
            background: #263c31;
            color: #fff;
        }
        .eu-hero:after {
            content: "“";
            position: absolute;
            right: 7%;
            top: -125px;
            font-family: "Cormorant Garamond", serif;
            font-size: 470px;
            line-height: 1;
            color: rgba(255,255,255,.04);
            pointer-events: none;
        }
        .eu-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 1280px;
            margin: 0 auto;
            padding: 92px 36px 100px;
        }
        .eu-eyebrow {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .26em;
            text-transform: uppercase;
            color: #d8b777;
        }
        .eu-hero h1 {
            margin: 17px 0 20px;
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: clamp(57px, 7vw, 100px);
            letter-spacing: -.05em;
            line-height: 1.08;
            color: #fff;
        }
        .eu-hero p {
            max-width: 600px;
            margin: 0;
            color: #e0e5dc;
            font-size: 18px;
            line-height: 1.75;
        }
        .eu-stats {
            display: flex;
            align-items: center;
            gap: 22px;
            margin-top: 38px;
        }
        .eu-stats strong {
            font-family: "Cormorant Garamond", serif;
            font-weight: 500;
            font-size: 38px;
            line-height: 1;
            color: #e6c78a;
        }
        .eu-stats span {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #e1e5db;
            line-height: 1.45;
        }
        .eu-note {
            max-width: 1280px;
            margin: 0 auto;
            padding: 45px 36px 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .eu-note span {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: #8e7b59;
        }
        .eu-note .line { height: 1px; flex: 1; background: #d7d4c8; }
        .eu-collection {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 36px 80px;
        }
        .eu-featured {
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            gap: 60px;
            margin-bottom: 20px;
            padding: 42px 54px 53px;
        }
        .eu-aside {
            border-right: 1px solid #e6e0d1;
            padding: 0 40px 0 0;
        }
        .eu-aside span {
            display: block;
            color: #b88f4b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .eu-aside p {
            margin: 24px 0 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            line-height: 1.35;
            color: #3c5141;
        }
        .eu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .eu-card {
            background: #fbfaf6;
            border: 1px solid #e9e5d9;
            box-shadow: 0 9px 29px rgba(49, 58, 46, .03);
            margin: 0;
            padding: 36px 40px 40px;
        }
        .eu-featured .eu-card {
            border: 0;
            box-shadow: none;
            background: transparent;
            padding: 0;
        }
        .eu-grid .eu-card { height: 100%; }
        .eu-top {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e8e3d8;
        }
        .eu-avatar {
            width: 49px;
            height: 49px;
            flex: none;
            border: 1px solid #c8ad76;
            border-radius: 50%;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #eee9db;
            color: #6d613f;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
        }
        .eu-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .eu-byline { flex: 1; min-width: 0; }
        .eu-byline h2 {
            margin: 0;
            font-family: "Cormorant Garamond", serif;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.3;
            color: #29352d;
        }
        .eu-byline time {
            display: block;
            margin-top: 2px;
            color: #8c8e83;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .eu-num {
            font-family: "Cormorant Garamond", serif;
            font-size: 28px;
            color: #cabda0;
        }
        .eu-body { max-width: 820px; margin: 27px auto 0; }
        .eu-grid .eu-body { margin-left: 0; }
        .eu-body p {
            margin: 0 0 20px;
            font-size: 16px;
            line-height: 1.95;
            color: #49544b;
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
            border-top: 1px solid #e7e3d8;
        }
        .eu-sign cite {
            font-family: "Cormorant Garamond", serif;
            font-style: italic;
            font-size: 18px;
            color: #405444;
        }
        .eu-sign img {
            height: 52px;
            max-width: 180px;
            object-fit: contain;
        }
        .eu-more {
            margin: 28px 0 0;
            text-align: center;
        }
        .eu-more a {
            color: #405444;
            font-weight: 700;
            text-underline-offset: 4px;
        }
        .eu-empty {
            background: #fbfaf6;
            border: 1px dashed #d7d4c8;
            padding: 28px 22px;
            color: #667066;
            font-size: 17px;
        }
        @media (max-width: 900px) {
            .eu-featured { grid-template-columns: 1fr; gap: 10px; padding: 30px; }
            .eu-aside { border-right: 0; border-bottom: 1px solid #e6e0d1; padding: 0 0 12px; }
            .eu-aside p { margin: 10px 0 0; font-size: 24px; }
            .eu-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .eu-hero-inner { padding: 70px 24px 80px; }
            .eu-hero p { font-size: 16px; }
            .eu-note { padding: 35px 24px 23px; }
            .eu-collection { padding: 0 20px 70px; }
            .eu-card, .eu-featured { padding: 25px 22px; }
            .eu-body p { font-size: 15px; }
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
