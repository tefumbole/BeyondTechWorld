@extends('nbc.layout')
@section('title', 'Events · Praise Team')
@section('content')
    <p class="kicker">Coming up</p>
    <h1>Events</h1>
    @forelse($events as $index => $event)
        <div class="row">
            <div class="num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div>
                <strong>{{ $event->title }}</strong>
                <div class="sub">
                    {{ ucfirst($event->kind) }}
                    @if($event->starts_at) · {{ $event->starts_at->format('D j M Y, g:i a') }} @endif
                    @if($event->place) · {{ $event->place }} @endif
                </div>
                @if($event->body)<div>{{ $event->body }}</div>@endif
            </div>
        </div>
    @empty
        <p class="sub">No upcoming events yet.</p>
    @endforelse
@endsection
