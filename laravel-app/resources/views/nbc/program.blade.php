@extends('nbc.layout')
@section('title', 'Program · Praise Team')
@section('content')
    <p class="kicker">Contents</p>
    <h1>Program</h1>
    <p class="sub">The order of service and rehearsals published for the praise team.</p>
    @forelse($items as $index => $item)
        <div class="row">
            <div class="num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div>
                <strong>{{ $item->title }}</strong>
                <div class="sub">
                    {{ $item->starts_at ? $item->starts_at->format('g:i a') : '' }}
                    @if($item->place) · {{ $item->place }} @endif
                </div>
                @if($item->body)<div>{{ $item->body }}</div>@endif
            </div>
        </div>
    @empty
        <p class="sub">The program will appear here when a leader publishes it.</p>
    @endforelse
@endsection
