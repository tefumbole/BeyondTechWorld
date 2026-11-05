@extends('travel.layout')
@section('content')
<section class="hero landing">
    <svg class="lines" viewBox="0 0 650 245" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs>
            <pattern id="map" width="30" height="30" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="#9ec7a2" opacity=".32"/></pattern>
        </defs>
        <rect width="650" height="245" fill="url(#map)"/>
        <g fill="none" stroke="#477d63" stroke-width="1.4">
            <path d="M50 260 250 80 380 105 490-22M180 248 302 156 385 175 563 20M420 245 505 156 640 171M280 0l80 75 150 15 130-50"/>
        </g>
        <path d="M197 215 310 155 435 162 554 57" fill="none" stroke="#d9f68e" stroke-width="2" stroke-dasharray="6 7"/>
        <g fill="#d9f68e" stroke="#214f3e" stroke-width="5">
            <circle cx="197" cy="215" r="8"/><circle cx="435" cy="162" r="8"/><circle cx="554" cy="57" r="10"/>
        </g>
    </svg>
    <small>Intercity buses &amp; parcels</small>
    <h1>Where are you headed?</h1>
    <p>Book your next trip, find your ticket or follow a parcel across Cameroon.</p>
</section>
<section>
    <div class="section-heading"><h2>Passenger services</h2></div>
    <div class="tiles">
        <a class="tile feature" href="{{ route('travel.book') }}"><span class="symbol">@include('travel.icon', ['icon' => 'bus'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Book a bus</b><span class="copy">Find a departure and choose your seat.</span></a>
        <a class="tile" href="{{ route('travel.trips') }}"><span class="symbol">@include('travel.icon', ['icon' => 'ticket'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>My trips</b><span class="copy">Open your ticket and check departure status.</span></a>
        <a class="tile" href="{{ route('travel.track') }}"><span class="symbol">@include('travel.icon', ['icon' => 'track'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Track parcel</b><span class="copy">See where your parcel is and when to collect it.</span></a>
    </div>
</section>
<section id="station">
    <div class="section-heading"><h2>Station operations</h2><span>Staff workspace</span></div>
    <div class="tiles staff-tiles">
        <a class="tile" href="{{ route('travel.staff') }}"><span class="symbol">@include('travel.icon', ['icon' => 'staff'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Staff</b><span class="copy">Open the station workspace.</span></a>
        <a class="tile" href="{{ route('travel.parcels') }}"><span class="symbol">@include('travel.icon', ['icon' => 'box'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Parcels</b><span class="copy">Accept, dispatch and release parcels.</span></a>
        <a class="tile" href="{{ route('travel.counter') }}"><span class="symbol">@include('travel.icon', ['icon' => 'counter'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Counter</b><span class="copy">Sell tickets and record payments.</span></a>
        <a class="tile" href="{{ route('travel.dispatch') }}"><span class="symbol">@include('travel.icon', ['icon' => 'route'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Dispatch</b><span class="copy">Assign buses, drivers and routes.</span></a>
        <a class="tile" href="{{ route('travel.reconcile') }}"><span class="symbol">@include('travel.icon', ['icon' => 'wallet'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Reconciliation</b><span class="copy">Review collections and close the till.</span></a>
    </div>
</section>
@endsection
