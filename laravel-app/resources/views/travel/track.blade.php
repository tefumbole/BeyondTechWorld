@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Track a parcel</h1>
    <p>Enter the tracking reference from your receipt.</p>
</section>
<form class="panel form" method="get" action="{{ route('travel.track') }}">
    <label>Tracking reference<input name="ref" required value="{{ $ref }}" placeholder="EP-P…"></label>
    <button class="primary">Track</button>
</form>
@if($ref)
    <p class="notice">
        @if($result)
            {{ $result['reference'] }} · {{ $result['from'] }} → {{ $result['to'] }}@if(!empty($result['via'])) · Stops at {{ $result['via'] }}@endif · {{ $result['status'] }}
        @else
            No parcel matches that reference.
        @endif
    </p>
@endif
@endsection
