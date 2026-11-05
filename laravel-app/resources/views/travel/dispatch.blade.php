@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Dispatch desk</h1>
    <p>Schedule departures and update the status passengers see. A bus or driver cannot overlap, including turnaround. Two directions can run at the same time on different buses.</p>
</section>
@if($sandbox)
    <form method="post" action="{{ route('travel.fixture') }}">
        @csrf
        <button>Load staging departures</button>
    </form>
@endif
<p class="muted">Bamenda to Douala, Limbe, Buea and Mutengene takes about 9 hours and stops at Melong. Bamenda to Yaoundé takes about 9 hours and stops at Makenene. The return passes the same stop.</p>
<form class="panel form" method="post" action="{{ route('travel.routes') }}">
    @csrf
    <label>Origin
        <select name="origin_station_id">@include('travel.station-options', ['by' => 'id'])</select>
    </label>
    <label>Destination
        <select name="destination_station_id">@include('travel.station-options', ['by' => 'id'])</select>
    </label>
    <label>Duration in minutes<input type="number" name="duration_minutes" min="1" value="540" required></label>
    <label>Agency confirmed this duration<input type="checkbox" name="confirmed" value="1"></label>
    <button class="primary">Save route</button>
</form>
<form class="panel form" method="post" action="{{ route('travel.schedule') }}">
    @csrf
    <label>Origin<select name="from">@include('travel.station-options', ['selected' => 'Bamenda'])</select></label>
    <label>Destination<select name="to">@include('travel.station-options', ['selected' => 'Douala'])</select></label>
    <label>Date<input type="date" name="date" required value="{{ $today }}"></label>
    <label>Shift<select name="shift"><option value="morning">Morning</option><option value="evening">Evening</option></select></label>
    <label>Class<select name="class"><option value="vip">VIP · 55 · 8,000 XAF</option><option value="regular">Regular · 70 · 6,000 XAF</option></select></label>
    <label>Bus<input name="bus" required placeholder="EP-201"></label>
    <label>Driver<input name="driver" required></label>
    <button class="primary">Schedule trip</button>
</form>
<div class="grid">
    @foreach($departures as $departure)
        @php
            $origin = $stations->firstWhere('id', $departure->origin_station_id);
            $destination = $stations->firstWhere('id', $departure->destination_station_id);
        @endphp
        <article class="card">
            <span class="tag">{{ strtoupper($departure->class) }} · {{ $departure->shift }}</span>
            <h3>{{ $origin ? $origin->name : '' }} → {{ $destination ? $destination->name : '' }}</h3>
            <p>{{ $departure->operating_date }} · {{ $departure->depart_local }} · {{ $departure->status }}</p>
            @if($departure->via)<p>Stops at {{ $departure->via }} · {{ \App\Travel\TravelCatalog::durationLabel($departure->duration_minutes) }}</p>@endif
            <p class="price">{{ \App\Travel\TravelCatalog::money($departure->fare_xaf) }}</p>
            <form method="post" action="{{ route('travel.status', $departure->id) }}" class="form">
                @csrf
                <label>Trip status
                    <select name="status">
                        @foreach(['loading','boarding','delayed','departed','arrived','cancelled'] as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Reason<input name="reason"></label>
                <button>Update</button>
            </form>
            <p><a href="{{ route('travel.manifest', ['id' => $departure->id, 'station_id' => $departure->origin_station_id]) }}">Origin manifest</a></p>
        </article>
    @endforeach
</div>
@endsection
