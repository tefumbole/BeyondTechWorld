@extends('nbc.layout')
@section('title', 'Announcements · Praise Team')
@section('content')
    <p class="kicker">News</p>
    <h1>Announcements</h1>
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
