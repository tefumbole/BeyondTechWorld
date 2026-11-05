@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Parcels</h1>
    <p>Accept a parcel, scan each handover, and collect it once with a signature. No tariff is invented: enter the agency fee unless a saved tariff matches the route.</p>
</section>
<form class="panel form" method="post" action="{{ route('travel.parcels.store') }}">
    @csrf
    <label>Accepting station<select name="station_id" required>@include('travel.station-options', ['by' => 'id'])</select></label>
    <label>Trip<select name="departure_id" required>
        @foreach($departures as $departure)
            @if(in_array($departure->status, ['scheduled','loading','boarding','delayed']))
                <option value="{{ $departure->id }}">{{ $departure->operating_date }} {{ $departure->depart_local }} · {{ strtoupper($departure->class) }}</option>
            @endif
        @endforeach
    </select></label>
    <label>Sender name<input name="sender_name" required></label>
    <label>Sender phone<input name="sender_phone" type="tel" required></label>
    <label>Receiver name<input name="receiver_name" required></label>
    <label>Receiver phone<input name="receiver_phone" type="tel" required></label>
    <label>Description<input name="description" required></label>
    <label>Weight in grams<input name="weight_grams" type="number" min="0"></label>
    <label>Packages<input name="package_count" type="number" min="1" value="1"></label>
    <label>Fee (XAF)<input name="fee_xaf" type="number" min="1" required></label>
    <label>Payment<select name="channel"><option value="cash">Cash</option><option value="momo">MoMo</option></select></label>
    <button class="primary">Register parcel</button>
</form>
<div class="grid">
    @foreach($parcels as $parcel)
        <article class="card">
            <span class="tag">{{ $parcel->status }}</span>
            <h3>{{ $parcel->tracking_ref }}</h3>
            <p>{{ \App\Travel\TravelCatalog::money($parcel->fee_xaf) }} · {{ $parcel->description }}</p>
            @php
                $next = ['accepted'=>'assigned','assigned'=>'loaded','loaded'=>'in_transit','in_transit'=>'received','received'=>'ready'];
            @endphp
            @if(isset($next[$parcel->status]))
                <form method="post" action="{{ route('travel.parcels.scan', $parcel->id) }}">
                    @csrf
                    <input type="hidden" name="event" value="{{ $next[$parcel->status] }}">
                    <label>Station<select name="station_id">@include('travel.station-options', ['by' => 'id'])</select></label>
                    <button>Mark {{ str_replace('_', ' ', $next[$parcel->status]) }}</button>
                </form>
            @endif
            @if($parcel->status === 'ready')
                <form method="post" action="{{ route('travel.parcels.collect', $parcel->id) }}" class="collect">
                    @csrf
                    <label>Station<select name="station_id">@include('travel.station-options', ['by' => 'id'])</select></label>
                    <label>Verification<select name="method"><option value="otp">OTP</option><option value="id">ID checked</option></select></label>
                    <label>OTP or ID reference<input name="proof" required></label>
                    <p>Receiver signature</p>
                    <canvas width="420" height="120" aria-label="Signature pad"></canvas>
                    <input type="hidden" name="signature">
                    <button type="button" class="clear">Clear signature</button>
                    <button class="primary">Confirm handover</button>
                </form>
            @endif
        </article>
    @endforeach
</div>
<script>
document.querySelectorAll('form.collect').forEach(function (form) {
    var canvas = form.querySelector('canvas');
    var ctx = canvas.getContext('2d');
    var signed = false;
    ctx.lineWidth = 2;
    function point(e) {
        var r = canvas.getBoundingClientRect();
        return [(e.clientX - r.left) * 420 / r.width, (e.clientY - r.top) * 120 / r.height];
    }
    canvas.addEventListener('pointerdown', function (e) {
        canvas.setPointerCapture(e.pointerId);
        ctx.beginPath();
        var p = point(e);
        ctx.moveTo(p[0], p[1]);
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!canvas.hasPointerCapture(e.pointerId)) return;
        var p = point(e);
        ctx.lineTo(p[0], p[1]);
        ctx.stroke();
        signed = true;
    });
    form.querySelector('.clear').addEventListener('click', function () {
        ctx.clearRect(0, 0, 420, 120);
        signed = false;
        form.querySelector('[name=signature]').value = '';
    });
    form.addEventListener('submit', function (e) {
        if (!signed) {
            e.preventDefault();
            return;
        }
        form.querySelector('[name=signature]').value = canvas.toDataURL();
    });
});
</script>
@endsection
