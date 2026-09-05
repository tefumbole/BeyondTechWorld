<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
    <meta name="theme-color" content="#050403">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $bio['meta']['title'] }}</title>
    <meta name="description" content="{{ $bio['meta']['description'] }}">
    <meta property="og:title" content="{{ $bio['meta']['title'] }}">
    <meta property="og:description" content="{{ $bio['meta']['description'] }}">
    <meta property="og:image" content="{{ \App\Support\PaNgwayuBiography::photoUrl($bio['meta']['og_image']) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="article">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-2: #f0d57a;
            --ink: #f7f1e4;
            --muted: #c4b498;
            --bg: #070604;
            --paper: #f4eee0;
            --paper-ink: #1a140c;
            --card: #12100c;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Source Sans Pro", sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-text-size-adjust: 100%;
        }
        a { color: inherit; }
        img { max-width: 100%; display: block; }
        .site-head {
            position: sticky; top: 0; z-index: 30;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 14px 22px;
            background: rgba(7,6,4,.92);
            border-bottom: 1px solid rgba(212,175,55,.22);
            backdrop-filter: blur(12px);
        }
        .brand { text-decoration: none; min-width: 0; }
        .brand b {
            display: block; font-family: "Cormorant Garamond", serif;
            font-size: 18px; letter-spacing: .08em; font-weight: 700;
        }
        .brand span {
            display: block; color: var(--gold); font-size: 10px;
            letter-spacing: .18em; text-transform: uppercase; font-weight: 700;
        }
        .top-nav {
            display: flex; align-items: center; gap: 18px;
            overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: none; max-width: min(720px, 58vw);
        }
        .top-nav::-webkit-scrollbar { display: none; }
        .top-nav a {
            text-decoration: none; white-space: nowrap;
            font-family: "Cormorant Garamond", serif;
            font-size: 16px; color: #f3ead6;
        }
        .top-nav a.on { color: var(--gold-2); border-bottom: 1px solid var(--gold); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 999px; padding: 12px 20px; font-weight: 700;
            text-decoration: none; font-family: inherit; font-size: 14px;
            min-height: 44px; cursor: pointer; border: 1px solid transparent;
        }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #d4af37); color: #1a1408; }
        .btn-ghost { background: transparent; color: #fff8e8; border-color: rgba(255,248,232,.45); }
        .btn-dark { background: rgba(8,6,4,.45); color: #fff8e8; border-color: rgba(212,175,55,.45); }
        .hero {
            position: relative; min-height: min(92vh, 920px);
            padding: 28px 18px 36px;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(212,175,55,.16), transparent 46%),
                radial-gradient(ellipse at 80% 20%, rgba(232,150,60,.12), transparent 40%),
                #080604;
        }
        .hero-grid {
            max-width: 1180px; margin: 0 auto;
            display: grid; grid-template-columns: 72px minmax(0, 1fr) minmax(0, 1.1fr) minmax(220px, .9fr);
            gap: 22px; align-items: center;
        }
        .rail {
            writing-mode: vertical-rl; transform: rotate(180deg);
            letter-spacing: .28em; text-transform: uppercase;
            color: var(--gold); font-size: 11px; font-weight: 700;
            opacity: .85; justify-self: start;
        }
        .hero-portrait, .hero-companion {
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,.45);
            border: 1px solid rgba(212,175,55,.28);
            background: #0a0806;
        }
        .hero-portrait img, .hero-companion img {
            width: 100%;
            height: auto;
            max-height: min(78vh, 740px);
            object-fit: contain;
            object-position: center top;
        }
        .hero-copy { text-align: center; padding: 8px 8px 0; }
        .kicker {
            letter-spacing: .24em; text-transform: uppercase; color: var(--gold);
            font-size: 12px; font-weight: 700; margin: 0 0 10px;
        }
        .hero-copy h1 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(36px, 6vw, 64px);
            line-height: 1.02; margin: 0 0 8px; font-weight: 700;
        }
        .years { color: var(--gold-2); font-size: clamp(18px, 2.4vw, 24px); margin: 0 0 10px; letter-spacing: .08em; }
        .hero-line { margin: 0 0 8px; color: #efe4c4; font-size: 15px; letter-spacing: .04em; }
        .hero-faith { margin: 0 0 16px; color: #d8cba8; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }
        .hero-quote {
            font-family: "Cormorant Garamond", serif; font-style: italic;
            font-size: clamp(18px, 2.4vw, 24px); line-height: 1.45;
            margin: 0 0 8px; color: #fff8e8;
        }
        .hero-attr { color: var(--muted); font-size: 13px; margin: 0 0 22px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .companion-cap {
            margin: 8px 4px 0; color: var(--gold-2); font-size: 12px;
            letter-spacing: .08em; text-transform: uppercase; text-align: center;
        }
        .subnav {
            position: sticky; top: 63px; z-index: 20;
            background: #0a0806; border-top: 1px solid rgba(212,175,55,.16);
            border-bottom: 1px solid rgba(212,175,55,.16);
        }
        .subnav-inner {
            max-width: 1100px; margin: 0 auto; display: flex; gap: 0;
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
            padding: 0 12px;
        }
        .subnav-inner::-webkit-scrollbar { display: none; }
        .subnav a {
            flex: 0 0 auto; text-decoration: none; color: #e8dcc0;
            padding: 12px 14px; font-size: 13px; letter-spacing: .06em;
            text-transform: uppercase; font-weight: 700; white-space: nowrap;
        }
        .subnav a + a { border-left: 1px solid rgba(255,248,232,.12); }
        .band { padding: 72px 20px; }
        .band-cream { background: var(--paper); color: var(--paper-ink); }
        .band-dark { background: #0b0907; color: var(--ink); }
        .band-char { background: #11100d; color: var(--ink); }
        .wrap { max-width: 1120px; margin: 0 auto; }
        .read {
            max-width: 820px; margin: 0 auto;
            font-size: clamp(17px, 2.1vw, 20px); line-height: 1.8;
        }
        .band-cream .read { color: #2a2218; }
        .read p { margin: 0 0 1.1em; }
        .read p:last-child { margin-bottom: 0; }
        .section-head { max-width: 820px; margin: 0 auto 28px; }
        .band-cream .kicker, .band-cream .years { color: #8a6d1a; }
        .band-cream h2, .cream-title {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(32px, 5vw, 52px); line-height: 1.08; margin: 0 0 8px;
            color: #14100a;
        }
        h2 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(32px, 5vw, 52px); line-height: 1.08; margin: 0 0 8px;
        }
        .sub { margin: 0; color: inherit; opacity: .78; font-size: 17px; }
        .split {
            display: grid; grid-template-columns: 1fr 1fr; gap: 36px; align-items: start;
        }
        .split.reverse { direction: rtl; }
        .split.reverse > * { direction: ltr; }
        .photo-frame {
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,.18);
            background: #0d0a08;
        }
        .band-cream .photo-frame { background: #ebe4d4; }
        .photo-frame img {
            width: 100%;
            height: auto;
            max-height: min(820px, 92vh);
            object-fit: contain;
            object-position: center top;
            vertical-align: top;
        }
        .photo-cap { margin: 8px 2px 0; font-size: 13px; color: #6b5410; }
        .band-dark .photo-cap, .band-char .photo-cap { color: var(--muted); }
        .quote-band {
            padding: 64px 20px; text-align: center;
            background: linear-gradient(180deg, #100c08, #0a0806);
        }
        .quote-band q {
            display: block; max-width: 820px; margin: 0 auto 12px;
            font-family: "Cormorant Garamond", serif; font-style: italic;
            font-size: clamp(26px, 4vw, 40px); line-height: 1.35; color: #fff8e8;
        }
        .quote-band q::before, .quote-band q::after { color: var(--gold); }
        .quote-inline {
            margin: 28px auto; max-width: 720px; padding: 22px 8px;
            border-top: 1px solid rgba(212,175,55,.28);
            border-bottom: 1px solid rgba(212,175,55,.28);
            text-align: center;
        }
        .quote-inline q {
            display: block; font-family: "Cormorant Garamond", serif; font-style: italic;
            font-size: clamp(22px, 3vw, 30px); line-height: 1.4;
        }
        .attr { display: block; margin-top: 10px; color: var(--gold); letter-spacing: .08em; font-size: 12px; text-transform: uppercase; }
        .band-cream .attr { color: #8a6d1a; }
        .note {
            margin-top: 18px; padding: 12px 14px; border: 1px dashed #c2a45a;
            border-radius: 12px; font-size: 14px; color: #6b5410; background: rgba(212,175,55,.08);
        }
        .band-dark .note, .band-char .note { color: var(--muted); border-color: rgba(212,175,55,.35); }
        .timeline { display: grid; gap: 0; max-width: 760px; margin: 28px auto 0; }
        .t-item { display: grid; grid-template-columns: 120px 28px 1fr; gap: 16px; }
        .t-year {
            font-family: "Cormorant Garamond", serif; font-size: 22px; color: var(--gold-2);
            text-align: right; padding-top: 4px;
        }
        .t-line { position: relative; }
        .t-line:before {
            content: ""; position: absolute; left: 12px; top: 0; bottom: 0; width: 1px;
            background: linear-gradient(#d4af37, rgba(212,175,55,.15));
        }
        .t-item:last-child .t-line:before { bottom: 18px; }
        .t-line:after {
            content: ""; position: absolute; left: 6px; top: 10px; width: 13px; height: 13px;
            border-radius: 50%; background: var(--gold); box-shadow: 0 0 0 4px rgba(212,175,55,.15);
        }
        .t-body { padding: 0 0 32px; }
        .t-body h3 { margin: 4px 0 6px; font-family: "Cormorant Garamond", serif; font-size: 24px; }
        .t-body p { margin: 0; color: #ddd2b8; line-height: 1.65; }
        .values-grid {
            display: grid; grid-template-columns: 1fr 1.1fr; gap: 28px; align-items: center;
        }
        .v-list { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
        .v-item { padding: 8px 0 10px; border-bottom: 1px solid rgba(212,175,55,.18); }
        .v-item b { display: block; font-family: "Cormorant Garamond", serif; font-size: 22px; }
        .v-item span { color: var(--muted); font-size: 13px; }
        .masonry {
            columns: 3; column-gap: 14px; margin-top: 28px;
        }
        .masonry a {
            break-inside: avoid; display: block; margin: 0 0 14px;
            border-radius: 14px; overflow: hidden; text-decoration: none;
            border: 1px solid rgba(212,175,55,.18);
        }
        .masonry img { width: 100%; height: auto; }
        .masonry span {
            display: block; padding: 8px 10px 10px; color: #d8cba8; font-size: 13px;
            background: #14100c;
        }
        .legacy { text-align: center; }
        .legacy .read { text-align: left; }
        .legacy .hero-actions { margin-top: 28px; }
        .closing { text-align: center; padding: 80px 20px 96px; }
        .closing h2 { margin-bottom: 6px; }
        .closing p { margin: 6px 0; font-family: "Cormorant Garamond", serif; font-size: 22px; color: #efe4c4; }
        .foot { text-align: center; color: #8a7b62; font-size: 13px; padding: 0 16px 28px; }
        .lightbox {
            display: none; position: fixed; inset: 0; z-index: 50;
            background: rgba(4,3,2,.88); padding: 18px;
            align-items: center; justify-content: center;
        }
        .lightbox.on { display: flex; }
        .lightbox figure { margin: 0; max-width: min(960px, 100%); text-align: center; }
        .lightbox img { max-height: 78vh; width: auto; margin: 0 auto; border-radius: 10px; }
        .lightbox figcaption { color: #f3ead6; margin-top: 12px; font-size: 15px; }
        .lightbox button {
            position: absolute; top: 14px; right: 14px; width: 42px; height: 42px;
            border-radius: 50%; border: 1px solid rgba(212,175,55,.35);
            background: rgba(8,6,4,.7); color: #fff8e8; font-size: 22px; cursor: pointer;
        }
        @media (max-width: 980px) {
            .hero-grid { grid-template-columns: 1fr; }
            .rail { writing-mode: horizontal-tb; transform: none; text-align: center; letter-spacing: .16em; }
            .hero-companion { display: none; }
            .hero-portrait img { max-height: min(70vh, 560px); }
            .split, .split.reverse, .values-grid { grid-template-columns: 1fr; }
            .masonry { columns: 2; }
            .top-nav { max-width: none; }
            .site-head { flex-wrap: wrap; }
        }
        @media (max-width: 640px) {
            .band { padding: 48px 16px; }
            .t-item { grid-template-columns: 86px 22px 1fr; gap: 10px; }
            .t-year { font-size: 16px; }
            .masonry { columns: 1; }
            .v-list { grid-template-columns: 1fr; }
            .hero { min-height: auto; padding-top: 18px; }
            .subnav { top: 108px; }
        }
    </style>
</head>
<body>
@php
    $photo = function ($path) { return \App\Support\PaNgwayuBiography::photoUrl($path); };
    $remember = route('funeral.pangwayu.remember');
    $share = $remember.'?memory=1';
    $tributes = $remember.'#eulogies';
@endphp

<header class="site-head">
    <a class="brand" href="{{ $remember }}">
        <b>PA NGWAYU FRANCIS</b>
        <span>A life well lived</span>
    </a>
    <nav class="top-nav" aria-label="Memorial">
        <a href="{{ $remember }}">Home</a>
        <a class="on" href="{{ route('funeral.pangwayu.biography') }}">Biography</a>
        <a href="#gallery">Life in Pictures</a>
        <a href="{{ $tributes }}">Tributes</a>
        <a href="{{ $tributes }}">Eulogies</a>
        <a href="{{ route('funeral.pangwayu.program') }}">Funeral Programme</a>
    </nav>
    <a class="btn btn-gold" href="{{ $share }}">Share a Memory</a>
</header>

<section class="hero">
    <div class="hero-grid">
        <div class="rail">{{ implode(' · ', $bio['hero']['values_rail']) }}</div>
        <figure class="hero-portrait">
            <img src="{{ $photo($bio['hero']['portrait']) }}" alt="{{ $bio['hero']['portrait_alt'] }}">
        </figure>
        <div class="hero-copy">
            <p class="kicker">{{ $bio['hero']['kicker'] }}</p>
            <h1>{{ $bio['hero']['name'] }}</h1>
            <p class="years">{{ $bio['hero']['years'] }}</p>
            <p class="hero-line">{{ $bio['hero']['line'] }}</p>
            <p class="hero-faith">{{ $bio['hero']['faith_line'] }}</p>
            <p class="hero-quote">“{{ $bio['hero']['quote'] }}”</p>
            <p class="hero-attr">{{ $bio['hero']['quote_attr'] }}</p>
            <div class="hero-actions">
                <a class="btn btn-gold" href="#story">Read His Story ↓</a>
                <a class="btn btn-dark" href="{{ $share }}">Share a Memory</a>
            </div>
        </div>
        <div>
            <figure class="hero-companion">
                <img src="{{ $photo($bio['hero']['companion']) }}" alt="{{ $bio['hero']['companion_alt'] }}">
            </figure>
            <p class="companion-cap">{{ $bio['hero']['companion_caption'] }}</p>
        </div>
    </div>
</section>

<nav class="subnav" aria-label="Biography sections">
    <div class="subnav-inner">
        @foreach($bio['nav'] as $item)
            <a href="{{ $item['id'] === 'tributes' ? $tributes : '#'.$item['id'] }}">{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>

<section class="band band-cream" id="intro">
    <div class="wrap">
        <div class="split">
            <div>
                <p class="kicker">{{ $bio['intro']['kicker'] }}</p>
                <h2>{{ $bio['intro']['title'] }}</h2>
                <div class="read">
                    @foreach($bio['intro']['paragraphs'] as $p)
                        <p>{{ $p }}</p>
                    @endforeach
                </div>
                <p style="margin-top:22px;"><a class="btn btn-gold" href="#story">Read His Full Biography</a></p>
            </div>
            <div>
                <div class="quote-inline">
                    <q>{{ $bio['intro']['quote'] }}</q>
                    <span class="attr">{{ $bio['intro']['quote_attr'] }}</span>
                </div>
                <figure class="photo-frame">
                    <img src="{{ $photo($bio['intro']['image']) }}" alt="{{ $bio['intro']['image_caption'] }}" loading="lazy">
                </figure>
                <p class="photo-cap">{{ $bio['intro']['image_caption'] }}</p>
            </div>
        </div>
    </div>
</section>

@foreach($bio['sections'] as $i => $section)
    @php
        $dark = $i % 2 === 1;
        $band = $dark ? 'band-dark' : 'band-cream';
        $hasImage = !empty($section['image']);
        $layout = $section['layout'] ?? 'prose';
    @endphp
    <section class="band {{ $band }}" id="{{ $section['id'] }}">
        <div class="wrap">
            @if($hasImage && strpos($layout, 'split') === 0)
                <div class="split {{ $layout === 'split-image-left' ? '' : 'reverse' }}">
                    <figure>
                        <div class="photo-frame">
                            <img src="{{ $photo($section['image']) }}" alt="{{ $section['image_caption'] ?? $section['title'] }}" loading="lazy">
                        </div>
                        @if(!empty($section['image_caption']))
                            <p class="photo-cap">{{ $section['image_caption'] }}</p>
                        @endif
                    </figure>
                    <div>
                        <div class="section-head" style="margin-left:0;margin-right:0;">
                            <p class="kicker">{{ $section['kicker'] }}</p>
                            <h2>{{ $section['title'] }}</h2>
                            @if(!empty($section['subtitle']))
                                <p class="sub">{{ $section['subtitle'] }}</p>
                            @endif
                        </div>
                        <div class="read" style="margin:0;">
                            @foreach($section['paragraphs'] as $p)
                                <p>{{ $p }}</p>
                            @endforeach
                        </div>
                        @if(!empty($section['placeholder']) && !empty($section['placeholder_note']))
                            <p class="note">{{ $section['placeholder_note'] }}</p>
                        @endif
                    </div>
                </div>
            @else
                <div class="section-head">
                    <p class="kicker">{{ $section['kicker'] }}</p>
                    <h2>{{ $section['title'] }}</h2>
                    @if(!empty($section['subtitle']))
                        <p class="sub">{{ $section['subtitle'] }}</p>
                    @endif
                </div>
                <div class="read">
                    @foreach($section['paragraphs'] as $p)
                        <p>{{ $p }}</p>
                    @endforeach
                </div>
                @if(!empty($section['placeholder']) && !empty($section['placeholder_note']))
                    <p class="note read">{{ $section['placeholder_note'] }}</p>
                @endif
                @if($hasImage)
                    <figure class="photo-frame" style="max-width:720px;margin:28px auto 0;">
                        <img src="{{ $photo($section['image']) }}" alt="{{ $section['image_caption'] ?? $section['title'] }}" loading="lazy">
                    </figure>
                    @if(!empty($section['image_caption']))
                        <p class="photo-cap" style="text-align:center;">{{ $section['image_caption'] }}</p>
                    @endif
                @endif
            @endif
        </div>
    </section>
    @if(!empty($section['quote']))
        <section class="quote-band" aria-label="Quotation">
            <q>{{ $section['quote'] }}</q>
            @if(!empty($section['quote_attr']))
                <span class="attr">{{ $section['quote_attr'] }}</span>
            @endif
        </section>
    @endif
@endforeach

@if(!empty($bio['timeline']['events']))
<section class="band band-char" id="timeline">
    <div class="wrap">
        <div class="section-head">
            <p class="kicker">{{ $bio['timeline']['kicker'] }}</p>
            <h2>{{ $bio['timeline']['title'] }}</h2>
            <p class="sub">{{ $bio['timeline']['subtitle'] }}</p>
        </div>
        <div class="timeline">
            @foreach($bio['timeline']['events'] as $event)
                <article class="t-item">
                    <div class="t-year">{{ $event['year'] }}</div>
                    <div class="t-line" aria-hidden="true"></div>
                    <div class="t-body">
                        <h3>{{ $event['title'] }}</h3>
                        <p>{{ $event['text'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="band band-cream" id="values">
    <div class="wrap values-grid">
        <figure>
            <div class="photo-frame">
                <img src="{{ $photo($bio['values']['image']) }}" alt="{{ $bio['values']['image_caption'] }}" loading="lazy">
            </div>
            <p class="photo-cap">{{ $bio['values']['image_caption'] }}</p>
        </figure>
        <div>
            <p class="kicker">{{ $bio['values']['kicker'] }}</p>
            <h2 class="cream-title">{{ $bio['values']['title'] }}</h2>
            <div class="v-list">
                @foreach($bio['values']['items'] as $value)
                    <div class="v-item">
                        <b>{{ $value['name'] }}</b>
                        <span>{{ $value['note'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="band band-dark" id="gallery">
    <div class="wrap">
        <div class="section-head">
            <p class="kicker">{{ $bio['gallery']['kicker'] }}</p>
            <h2>{{ $bio['gallery']['title'] }}</h2>
            <p class="sub">{{ $bio['gallery']['subtitle'] }}</p>
        </div>
        <div class="masonry">
            @foreach($bio['gallery']['items'] as $item)
                <a href="{{ $photo($item['src']) }}" data-caption="{{ $item['caption'] }}" data-year="{{ $item['year'] }}">
                    <img src="{{ $photo($item['src']) }}" alt="{{ $item['caption'] }}" loading="lazy">
                    <span>{{ $item['caption'] }}@if($item['year']) · {{ $item['year'] }}@endif</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="band band-cream legacy" id="legacy-close">
    <div class="wrap">
        <p class="kicker">{{ $bio['legacy_close']['kicker'] }}</p>
        <h2 class="cream-title">{{ $bio['legacy_close']['title'] }}</h2>
        <div class="read">
            @foreach($bio['legacy_close']['paragraphs'] as $p)
                <p>{{ $p }}</p>
            @endforeach
        </div>
        <div class="hero-actions">
            <a class="btn btn-gold" href="{{ $tributes }}">Read Tributes</a>
            <a class="btn btn-dark" href="{{ $share }}" style="color:#1a1408;border-color:#8a6d1a;">Share Your Memory</a>
        </div>
    </div>
</section>

<section class="closing">
    <p class="kicker">In loving memory</p>
    <h2>{{ $bio['closing']['name'] }}</h2>
    <p class="years">{{ $bio['closing']['years'] }}</p>
    @foreach($bio['closing']['lines'] as $line)
        <p>{{ $line }}</p>
    @endforeach
</section>

<p class="foot">Developed By: Sr. Engr. Tefu R. Mbole |
    <a href="https://wa.me/237675321739" target="_blank" rel="noopener">+237 675-321-739</a>
</p>

<div class="lightbox" id="lightbox" aria-hidden="true">
    <button type="button" id="lightboxClose" aria-label="Close">&times;</button>
    <figure>
        <img id="lightboxImg" alt="">
        <figcaption id="lightboxCap"></figcaption>
    </figure>
</div>

<script>
(function () {
    var box = document.getElementById('lightbox');
    var img = document.getElementById('lightboxImg');
    var cap = document.getElementById('lightboxCap');
    function openLb(href, caption, year) {
        img.src = href;
        cap.textContent = (caption || '') + (year ? ' · ' + year : '');
        box.classList.add('on');
        box.setAttribute('aria-hidden', 'false');
    }
    function closeLb() {
        box.classList.remove('on');
        box.setAttribute('aria-hidden', 'true');
        img.removeAttribute('src');
    }
    Array.prototype.forEach.call(document.querySelectorAll('.masonry a'), function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            openLb(a.getAttribute('href'), a.getAttribute('data-caption'), a.getAttribute('data-year'));
        });
    });
    document.getElementById('lightboxClose').addEventListener('click', closeLb);
    box.addEventListener('click', function (e) { if (e.target === box) closeLb(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });
    var startX = 0;
    box.addEventListener('touchstart', function (e) { startX = e.changedTouches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (e) {
        if (Math.abs(e.changedTouches[0].clientX - startX) > 60) closeLb();
    }, { passive: true });
})();
</script>
</body>
</html>
