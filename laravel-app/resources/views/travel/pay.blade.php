@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Payment</h1>
    <p>{{ $booking->reference }} · seat {{ $booking->requested_seat }} · {{ \App\Travel\TravelCatalog::money($booking->amount_xaf) }}</p>
</section>
<section class="panel">
    @if($booking->payment_status === 'paid' || $booking->payment_status === 'unallocated')
        <p class="notice">This payment is already recorded.</p>
    @elseif($sandbox)
        <p>Sandbox mode does not send a MoMo prompt. Use it to test the ticket, then switch payments live only after the checks pass.</p>
        <form method="post" action="{{ route('travel.charge', $booking->id) }}">
            @csrf
            <button class="primary">{{ \App\Travel\TravelCopy::line('sandbox') }}</button>
        </form>
    @else
        <form method="post" action="{{ route('travel.charge', $booking->id) }}">
            @csrf
            <button class="primary">{{ \App\Travel\TravelCopy::line('pay') }}</button>
        </form>
    @endif
</section>
@endsection
