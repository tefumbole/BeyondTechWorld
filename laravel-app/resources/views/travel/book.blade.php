@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Your next stop starts here.</h1>
    <p>Book a seat, choose your departure and travel across Cameroon.</p>
</section>
<form class="panel form" method="get" action="{{ route('travel.book') }}">
    <label>From
        <select name="from">@include('travel.station-options', ['selected' => $from])</select>
    </label>
    <label>To
        <select name="to">@include('travel.station-options', ['selected' => $to])</select>
    </label>
    <label>Travel date
        <input type="date" name="date" required value="{{ $date }}">
    </label>
    <button class="primary">Find buses</button>
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
            <a class="primary" style="display:inline-block;text-decoration:none;border-radius:10px;padding:11px 16px" href="{{ route('travel.departure', $trip->id) }}">Choose seat</a>
        </article>
    @empty
        <p class="notice">{{ $from === $to ? 'Choose different stations.' : 'No departures for this route and date.' }}</p>
    @endforelse
</div>
@endsection
