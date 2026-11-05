@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Station desk</h1>
    <p>Sell a cash ticket for a seat that is still free. The ticket is issued only after the cash is recorded.</p>
</section>
<form class="panel form" method="post" action="{{ route('travel.sell') }}">
    @csrf
    <label>Station
        <select name="station_id" required>
            @include('travel.station-options', ['by' => 'id'])
        </select>
    </label>
    <label>Trip
        <select name="departure_id" required>
            @foreach($departures as $departure)
                @if(in_array($departure->status, ['scheduled','loading','boarding','delayed']))
                    @php $origin = $stations->firstWhere('id', $departure->origin_station_id); $destination = $stations->firstWhere('id', $departure->destination_station_id); @endphp
                    <option value="{{ $departure->id }}">{{ $origin ? $origin->name : '' }} → {{ $destination ? $destination->name : '' }} · {{ $departure->depart_local }} · {{ strtoupper($departure->class) }}</option>
                @endif
            @endforeach
        </select>
    </label>
    <label>Seat number<input name="seat" type="number" min="1" required></label>
    <label>Passenger<input name="name" required></label>
    <label>WhatsApp phone<input name="phone" type="tel" required></label>
    <button class="primary">Issue ticket</button>
</form>
<form class="panel form" method="post" action="{{ route('travel.board') }}">
    @csrf
    <label>Station
        <select name="station_id" required>@include('travel.station-options', ['by' => 'id'])</select>
    </label>
    <label>Ticket reference<input name="reference" required></label>
    <button>Scan boarding</button>
</form>
<p><a href="{{ route('travel.parcels') }}">Parcels</a> · <a href="{{ route('travel.reconcile') }}">Reconciliation</a></p>
@endsection
