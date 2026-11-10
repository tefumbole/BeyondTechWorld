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
    .ev-chart {
        margin-top: 22px;
        background: #fff;
        border: 1px solid #e4eaf4;
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 10px 28px rgba(11, 63, 144, 0.06);
    }
    .ev-chart h3 { margin: 0 0 18px; font-size: 18px; font-weight: 800; color: #0b3f90; }
    .ev-dash-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .ev-dash-btn {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .ev-dash-btn:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .ev-bars { display: flex; align-items: flex-end; gap: 18px; min-height: 240px; }
    .ev-bar { flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 0; }
    .ev-bar-track { width: 100%; max-width: 88px; height: 180px; display: flex; align-items: flex-end; justify-content: center; }
    .ev-bar-fill { width: 100%; border-radius: 14px 14px 6px 6px; min-height: 8px; }
    .ev-bar-val { margin-top: 10px; font-size: 20px; font-weight: 800; color: #0b3f90; }
    .ev-bar-label { margin-top: 4px; font-size: 13px; font-weight: 700; color: #334155; text-align: center; }
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
        <div class="ev-chart">
            <h3>Events</h3>
            <div class="ev-bars">
                @foreach($chart as $bar)
                    <div class="ev-bar">
                        <div class="ev-bar-track">
                            <div class="ev-bar-fill" style="height: {{ max(8, (int) round($bar['count'] / $chartMax * 100)) }}%; background: {{ $bar['color'] }};"></div>
                        </div>
                        <div class="ev-bar-val">{{ $bar['count'] }}</div>
                        <div class="ev-bar-label">{{ $bar['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection
