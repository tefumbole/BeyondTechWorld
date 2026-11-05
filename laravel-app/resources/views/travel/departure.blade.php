@extends('travel.layout')
@section('content')
<section class="panel">
    <h2>Select a seat</h2>
    <p>{{ $origin }} → {{ $destination }} · {{ $departure->depart_local }} · {{ strtoupper($departure->class) }} · {{ \App\Travel\TravelCatalog::money($departure->fare_xaf) }}</p>
    @if($departure->duration_minutes)<p>{{ \App\Travel\TravelCatalog::durationLabel($departure->duration_minutes) }}@if($departure->via) · Stops at {{ $departure->via }}@endif</p>@endif
    <p class="muted">Front of bus. Seat 1 is the accessible seat. The fare is {{ \App\Travel\TravelCatalog::money($departure->fare_xaf) }}.</p>
    <form method="post" action="{{ route('travel.checkout') }}" id="seat-form">
        @csrf
        <input type="hidden" name="departure_id" value="{{ $departure->id }}">
        <div class="seats" role="radiogroup" aria-label="Seat map">
            @foreach($seats as $seat)
                <button type="button" class="seat" data-seat="{{ $seat->seat_no }}" {{ $seat->status === 'free' ? '' : 'disabled' }} aria-pressed="false">
                    {{ $seat->accessible ? $seat->seat_no.' · accessible' : $seat->seat_no }}
                </button>
            @endforeach
        </div>
        <input type="hidden" name="seat" id="seat-no" required>
        <div class="form">
            <label>Passenger name<input name="name" required value="{{ old('name') }}"></label>
            <label>WhatsApp phone<input type="tel" name="phone" required value="{{ old('phone') }}" placeholder="6XXXXXXXX"></label>
            <label>Payer phone, if different<input type="tel" name="payer_phone" value="{{ old('payer_phone') }}"></label>
        </div>
        <p>
            <button type="submit" formaction="{{ route('travel.otp') }}" formmethod="post" formnovalidate onclick="document.getElementById('otp-purpose').disabled=false">Send WhatsApp OTP</button>
        </p>
        <input type="hidden" name="purpose" id="otp-purpose" disabled>
        <label>OTP<input name="otp" inputmode="numeric" required></label>
        <p><button class="primary">Hold seat</button></p>
    </form>
</section>
<script>
document.querySelectorAll('.seat').forEach(function (button) {
    button.addEventListener('click', function () {
        document.querySelectorAll('.seat').forEach(function (other) { other.classList.remove('active'); other.setAttribute('aria-pressed', 'false'); });
        button.classList.add('active');
        button.setAttribute('aria-pressed', 'true');
        var seat = button.getAttribute('data-seat');
        document.getElementById('seat-no').value = seat;
        document.getElementById('otp-purpose').value = 'ticket:{{ $departure->id }}:' + seat;
    });
});
</script>
@endsection
