@extends('nbc.layout')
@section('content')
    <p class="kicker">Nkwen Baptist Church</p>
    <h1>Praise Team</h1>
    <p class="sub">Rehearsals, services, and news for the praise team. Sign in after your application is approved.</p>
    <h2>Upcoming</h2>
    @forelse($events as $index => $event)
        <div class="row">
            <div class="num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div>
                <strong>{{ $event->title }}</strong>
                <div class="sub">
                    {{ $event->starts_at ? $event->starts_at->format('D j M Y, g:i a') : 'Date to be announced' }}
                    @if($event->place) · {{ $event->place }} @endif
                </div>
                @if($event->body)<div>{{ $event->body }}</div>@endif
            </div>
        </div>
    @empty
        <p class="sub">No upcoming events yet.</p>
    @endforelse
    <h2>Announcements</h2>
    @forelse($announcements as $index => $item)
        <a class="row" href="{{ route('nbc.announcement', $item->id) }}">
            <div class="num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div>
                <strong>{{ $item->title }}</strong>
                <div class="sub">{{ $item->published_at ? $item->published_at->format('j M Y') : '' }}</div>
            </div>
        </a>
    @empty
        <p class="sub">No announcements yet.</p>
    @endforelse
@endsection
