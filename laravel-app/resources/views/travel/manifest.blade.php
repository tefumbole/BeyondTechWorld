@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Manifest</h1>
    <p>{{ $origin }} → {{ $destination }} · {{ $departure->operating_date }} · {{ $departure->depart_local }} · {{ \App\Travel\TravelClock::passengerLine($departure) }}</p>
</section>
<div class="panel">
    @forelse($bookings as $booking)
        <p>
            Seat {{ $booking->requested_seat }} · {{ $booking->passenger_name }} · {{ $booking->reference }} · {{ $booking->ticket_status }}
            @if($booking->ticket_status === 'issued' && $departure->status === 'departed')
                <form method="post" action="{{ route('travel.noshow', $booking->id) }}" style="display:inline">@csrf<button>No-show</button></form>
            @endif
            @if($booking->channel === 'cash' && $booking->payment_status === 'paid' && !in_array($departure->status, ['departed','arrived']))
                <form method="post" action="{{ route('travel.void', $booking->id) }}" style="display:inline">@csrf<button>Void cash</button></form>
            @endif
        </p>
    @empty
        <p class="muted">No issued tickets yet.</p>
    @endforelse
</div>
@endsection
