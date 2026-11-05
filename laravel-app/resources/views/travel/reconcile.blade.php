@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Reconciliation</h1>
    <p>Open tills, paid-but-unallocated seats, and cancellations waiting for a refund or another booking. Nothing here is refunded automatically.</p>
</section>
<form class="panel form" method="post" action="{{ route('travel.till.close') }}">
    @csrf
    <label>Declared cash (XAF)<input type="number" name="declared" min="0" required></label>
    <button class="primary">Close my open till</button>
</form>
<div class="panel">
    <h2>Tills</h2>
    @forelse($tills as $till)
        <p>Till {{ $till->id }} · expected {{ \App\Travel\TravelCatalog::money($till->expected_cash) }}
            · {{ $till->closed_at ? 'closed, declared '.\App\Travel\TravelCatalog::money($till->declared_cash) : 'open' }}</p>
    @empty
        <p class="muted">No tills yet.</p>
    @endforelse
</div>
<div class="panel">
    <h2>Paid, seat not allocated</h2>
    @forelse($unallocated as $booking)
        <p>{{ $booking->reference }} · {{ $booking->passenger_name }} · requested seat {{ $booking->requested_seat }} · {{ \App\Travel\TravelCatalog::money($booking->amount_xaf) }}</p>
    @empty
        <p class="muted">None.</p>
    @endforelse
</div>
<div class="panel">
    <h2>Refund or rebooking review</h2>
    @forelse($refunds as $booking)
        <p>{{ $booking->reference }} · {{ $booking->passenger_name }} · {{ \App\Travel\TravelCatalog::money($booking->amount_xaf) }}</p>
    @empty
        <p class="muted">None.</p>
    @endforelse
</div>
@endsection
