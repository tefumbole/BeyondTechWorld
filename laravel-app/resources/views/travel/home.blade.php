@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>{{ \App\Travel\TravelCopy::line('hero_book') }}</h1>
    <p>{{ \App\Travel\TravelCopy::line('hero_book_sub') }}</p>
</section>
<form class="panel form" method="get" action="{{ route('travel.home') }}">
    <label>{{ \App\Travel\TravelCopy::line('from') }}
        <select name="from">
            @include('travel.station-options', ['selected' => $from])
        </select>
    </label>
    <label>{{ \App\Travel\TravelCopy::line('to') }}
        <select name="to">
            @include('travel.station-options', ['selected' => $to])
        </select>
    </label>
    <label>{{ \App\Travel\TravelCopy::line('date') }}
        <input type="date" name="date" required value="{{ $date }}">
    </label>
    <button class="primary">{{ \App\Travel\TravelCopy::line('find') }}</button>
</form>
<div class="grid">
    @forelse($trips as $trip)
        <article class="card">
            <span class="tag">{{ $trip->class_label }} · {{ ucfirst($trip->shift) }}</span>
            <h3>{{ $trip->depart_local }}</h3>
            @if($trip->duration_label)<p>{{ $trip->duration_label }}</p>@endif
            @if($trip->via)<p>Stops at {{ $trip->via }}</p>@endif
            <p class="muted">{{ \App\Travel\TravelClock::passengerLine($trip) }}</p>
            <p class="price">{{ \App\Travel\TravelCatalog::money($trip->fare_xaf) }}</p>
            <p>{{ $trip->available }} seats available</p>
            <a class="nav primary" href="{{ route('travel.departure', $trip->id) }}">{{ \App\Travel\TravelCopy::line('seat') }}</a>
        </article>
    @empty
        <p class="notice">{{ $from === $to ? 'Choose different stations.' : 'No departures for this route and date.' }}</p>
    @endforelse
</div>
@endsection
