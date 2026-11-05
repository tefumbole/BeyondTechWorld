@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>{{ \App\Travel\TravelCopy::line('trips') }}</h1>
    <p>See your confirmed seats and departure status.</p>
</section>
<form class="panel form" method="post" action="{{ route('travel.otp') }}">
    @csrf
    <input type="hidden" name="purpose" value="my-trips">
    <label>WhatsApp phone<input type="tel" name="phone" required></label>
    <button>Send WhatsApp OTP</button>
</form>
<form class="panel form" method="post" action="{{ route('travel.trips.unlock') }}">
    @csrf
    <label>WhatsApp phone<input type="tel" name="phone" required></label>
    <label>OTP<input name="otp" inputmode="numeric" required></label>
    <button class="primary">Show my trips</button>
</form>
<div class="panel">
    @forelse($bookings as $booking)
        @php $departure = \Illuminate\Support\Facades\DB::table('travel_departures')->where('id', $booking->departure_id)->first(); @endphp
        <p>
            <b>{{ $booking->reference }}</b>
            · seat {{ $booking->requested_seat }}
            · {{ $booking->payment_status }}
            · {{ $booking->ticket_status }}
            @if($departure) · {{ \App\Travel\TravelClock::passengerLine($departure) }} @endif
        </p>
    @empty
        <p class="muted">Your tickets appear here after you confirm the code.</p>
    @endforelse
</div>
@endsection
