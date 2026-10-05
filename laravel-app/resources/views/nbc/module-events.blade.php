@extends('nbc.layout')
@section('title', 'Events · Praise Team')
@section('content')
    <p class="kicker">Publish</p>
    <h1>Program and events</h1>
    <form class="card" method="POST" action="{{ route('nbc.events.store') }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" required>
        <label for="kind">Kind</label>
        <select id="kind" name="kind">
            <option value="practice">Practice</option>
            <option value="event">Event</option>
            <option value="program">Program item</option>
        </select>
        <label for="starts_at">Starts</label>
        <input id="starts_at" name="starts_at" type="datetime-local">
        <label for="place">Place</label>
        <input id="place" name="place">
        <label for="body">Details</label>
        <textarea id="body" name="body"></textarea>
        <label for="sort">Order</label>
        <input id="sort" name="sort" type="number" value="0">
        <label><input type="checkbox" name="published" value="1" checked> Show on the public site</label>
        <button type="submit">Save</button>
    </form>
    @foreach($events as $event)
        <div class="row">
            <div class="num">{{ $event->published ? 'On' : 'Off' }}</div>
            <div>
                <strong>{{ $event->title }}</strong>
                <div class="sub">{{ ucfirst($event->kind) }} @if($event->starts_at) · {{ $event->starts_at->format('j M Y g:i a') }} @endif @if($event->place) · {{ $event->place }} @endif</div>
            </div>
        </div>
    @endforeach
@endsection
