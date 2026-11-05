@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>TRAVEL</h1>
    <p>Choose the desk. Dispatch sets which bus runs, the counter sells tickets, and parcels records a handover.</p>
</section>
<div class="grid">
    <a class="card" href="{{ route('travel.staff') }}"><span class="tag">Staff</span><h3>Staff</h3><p class="muted">The desks for the people running the station.</p></a>
    <a class="card" href="{{ route('travel.parcels') }}"><span class="tag">Parcels</span><h3>Parcels</h3><p class="muted">Accept a parcel, scan it along the trip, and record the signed pickup.</p></a>
    <a class="card" href="{{ route('travel.counter') }}"><span class="tag">Counter</span><h3>Counter</h3><p class="muted">Sell a cash ticket and scan boarding.</p></a>
    <a class="card" href="{{ route('travel.dispatch') }}"><span class="tag">Dispatch</span><h3>Dispatch</h3><p class="muted">Set which bus travels from where to where, and update the trip status.</p></a>
    <a class="card" href="{{ route('travel.reconcile') }}"><span class="tag">Cash</span><h3>Reconciliation</h3><p class="muted">Close the till and review payments that still need a seat or a refund.</p></a>
    <a class="card" href="{{ route('travel.home') }}"><span class="tag">Public</span><h3>Book a bus</h3><p class="muted">The page a passenger uses to choose a seat.</p></a>
    <a class="card" href="{{ route('travel.track') }}"><span class="tag">Public</span><h3>Track a parcel</h3><p class="muted">Look up a tracking reference.</p></a>
</div>
@endsection
