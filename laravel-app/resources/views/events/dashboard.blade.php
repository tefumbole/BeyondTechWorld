@extends('layout.main')

@section('content')
@include('events.partials.modern')
<style>
    .ev-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .ev-board a {
        display: block;
        text-decoration: none;
        color: inherit;
        background: #fff;
        border: 1px solid #eef2f7;
        border-left: 4px solid #0b3f90;
        border-radius: 14px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        min-height: 0;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .ev-board a:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 34px rgba(11, 63, 144, 0.12);
        color: inherit;
    }
    .ev-board .count {
        font-size: 2rem;
        font-weight: 800;
        color: #0b3f90;
        line-height: 1.1;
    }
    .ev-board .name {
        margin-top: 0;
        margin-bottom: 4px;
        font-size: 13px;
        font-weight: 600;
        color: #6b7280;
    }
    .ev-board .note {
        margin-top: 4px;
        color: #64748b;
        font-size: 13px;
    }
    .ev-board .open {
        margin-top: 16px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #1456b8;
    }
    @media (max-width: 991px) {
        .ev-board { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 575px) {
        .ev-board { grid-template-columns: 1fr; }
    }
    .ev-graphs { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; margin-top: 8px; }
    .ev-chart {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    }
    .ev-chart h3 { margin: 0 0 16px; font-size: 18px; font-weight: 800; color: #0b3f90; }
    .ev-dash-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .ev-dash-btn {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .ev-dash-btn:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .ev-bars { display: flex; align-items: flex-end; justify-content: space-around; gap: 12px; height: 220px; }
    .ev-bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
    .ev-bar-fill { width: 100%; max-width: 72px; border-radius: 12px 12px 4px 4px; }
    .ev-bar-val { margin-top: 8px; font-size: 18px; font-weight: 800; color: #0b3f90; }
    .ev-bar-label { margin-top: 2px; font-size: 13px; font-weight: 700; color: #334155; text-align: center; }
    .ev-line { display: flex; align-items: center; gap: 12px; margin: 12px 0; }
    .ev-line span { width: 110px; font-size: 13px; font-weight: 700; color: #334155; }
    .ev-line strong { width: 28px; text-align: right; color: #0b3f90; }
    .ev-line i { display: block; height: 14px; border-radius: 999px; }
    @media (max-width: 991px) {
        .ev-graphs { grid-template-columns: 1fr; }
    }
</style>
<section class="forms events-shell">
    <div class="container-fluid">
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        <div class="ev-page-head">
            <div>
                <h1 class="ev-dash-title">Events Dashboard</h1>
                <p class="ev-lead">Upcoming, in progress, completed, and published events.</p>
            </div>
            @if(in_array('events.create', $all_permission))
                <a href="{{ route('events.create') }}" class="ev-dash-btn"><i class="dripicons-plus"></i> Create Event</a>
            @endif
        </div>
        <div class="ev-board">
            @foreach($cards as $card)
                <a href="{{ route('events.index', ['view' => $card['view']]) }}" style="border-left-color: {{ $card['accent'] }};">
                    <div class="name">{{ $card['label'] }}</div>
                    <div class="count">{{ $card['count'] }}</div>
                    <div class="note">{{ $card['note'] }}</div>
                </a>
            @endforeach
        </div>
        <div class="ev-graphs">
            <div class="ev-chart">
                <h3>Events</h3>
                <div class="ev-bars">
                    @foreach($chart as $bar)
                        <div class="ev-bar">
                            <div class="ev-bar-fill" style="height: {{ $bar['height'] }}px; background: {{ $bar['color'] }};"></div>
                            <div class="ev-bar-val">{{ $bar['count'] }}</div>
                            <div class="ev-bar-label">{{ $bar['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="ev-chart">
                <h3>Share of events</h3>
                @foreach($chart as $bar)
                    <div class="ev-line">
                        <span>{{ $bar['label'] }}</span>
                        <div style="flex:1;background:#f1f5f9;border-radius:999px;">
                            <i style="width: {{ $bar['width'] }}%; background: {{ $bar['color'] }};"></i>
                        </div>
                        <strong>{{ $bar['count'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection
