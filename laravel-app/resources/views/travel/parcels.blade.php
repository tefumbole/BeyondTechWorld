@extends('travel.layout')
@section('content')
<style>
    .desk-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
    .desk-top h1{font-size:clamp(32px,5vw,48px);margin:4px 0 0;letter-spacing:-.04em}
    .eyebrow{margin:0;font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:#3e6b58}
    .switch{display:flex;background:#fff;border:1px solid #dce4d8;border-radius:14px;padding:4px;gap:4px}
    .switch a{text-decoration:none;color:inherit;padding:10px 16px;border-radius:11px;font-weight:700}
    .switch a.on{background:#124638;color:#fff}
    .wizard{background:#fff;border:1px solid #e0e7de;border-radius:22px;padding:22px}
    .progress{display:flex;gap:8px;margin:0 0 18px;padding:0;list-style:none}
    .progress li{flex:1;font-size:12px;font-weight:700;color:#7b8d84;border-top:3px solid #e4ebe3;padding-top:8px}
    .progress li.on{color:#124638;border-color:#124638}
    .fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .fields .wide{grid-column:1 / -1}
    .lookup{display:flex;gap:8px;align-items:end}
    .lookup label{flex:1}
    .name-box{background:#f7f8f2;border:1px solid #dce4d8;border-radius:14px;padding:14px}
    .name-box strong{display:block;font-size:18px;margin-top:4px}
    .pay-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .pay-choice{text-align:left;border-radius:16px;padding:18px;min-height:120px;background:#fff;border:1px solid #dce4d8}
    .pay-choice b{display:block;font-size:18px;margin-bottom:6px}
    .pay-choice.momo{background:#d9f68e;border-color:transparent}
    .summary{display:grid;gap:6px;margin-bottom:16px}
    .rows{display:grid;gap:10px;margin-top:14px}
    .parcel-row{display:grid;grid-template-columns:1fr auto;gap:8px 16px;align-items:center;text-decoration:none;color:inherit;background:#fff;border:1px solid #e0e7de;border-radius:16px;padding:16px}
    .parcel-row small{color:#52675f}
    .handover{margin-top:14px}
    .choices{display:flex;gap:8px;margin:8px 0 14px}
    .choices label{display:flex;gap:8px;align-items:center;background:#f7f8f2;border-radius:12px;padding:10px 12px;font-weight:700}
    canvas{border-radius:12px}
    [hidden]{display:none !important}
    @media(max-width:700px){.fields,.pay-row{grid-template-columns:1fr}.fields .wide{grid-column:auto}}
</style>
<div class="desk-top">
    <div>
        <p class="eyebrow">Station operations</p>
        <h1>Parcels</h1>
    </div>
    <nav class="switch">
        <a class="{{ $desk === 'send' ? 'on' : '' }}" href="{{ route('travel.parcels') }}">Send</a>
        <a class="{{ $desk === 'collection' ? 'on' : '' }}" href="{{ route('travel.parcels', ['desk' => 'collection']) }}">Collection</a>
    </nav>
</div>

@if($desk === 'send')
<form class="wizard" id="send-form" method="post" action="{{ route('travel.parcels.store') }}">
    @csrf
    <ol class="progress">
        <li class="on" data-dot="1">Sender</li>
        <li data-dot="2">Receiver</li>
        <li data-dot="3">Route</li>
        <li data-dot="4">Payment</li>
    </ol>

    <section data-pane="1">
        <h2>Who is sending?</h2>
        <p class="muted">Enter the client’s phone. The name is read from Mobile Money before you continue.</p>
        <div class="lookup">
            <label>Sender phone
                <input name="sender_phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="6XX XXX XXX">
            </label>
            <button type="button" class="primary find" data-phone="sender_phone" data-name="sender_name" data-note="sender-note">Find name</button>
        </div>
        <div class="name-box wide" id="sender-note" hidden>
            <span class="muted" data-copy></span>
            <label>Confirm sender name
                <input name="sender_name" required>
            </label>
        </div>
        <p><button type="button" class="primary next" data-next="2">Next</button></p>
    </section>

    <section data-pane="2" hidden>
        <h2>Who is receiving?</h2>
        <p class="muted">The receiver’s number is resolved the same way. Confirm the name before the route.</p>
        <div class="lookup">
            <label>Receiver phone
                <input name="receiver_phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="6XX XXX XXX">
            </label>
            <button type="button" class="primary find" data-phone="receiver_phone" data-name="receiver_name" data-note="receiver-note">Find name</button>
        </div>
        <div class="name-box" id="receiver-note" hidden>
            <span class="muted" data-copy></span>
            <label>Confirm receiver name
                <input name="receiver_name" required>
            </label>
        </div>
        <p>
            <button type="button" class="back-step" data-next="1">Back</button>
            <button type="button" class="primary next" data-next="3">Next</button>
        </p>
    </section>

    <section data-pane="3" hidden>
        <h2>Where is it going?</h2>
        <div class="fields">
            <label>Sending station
                <select name="origin_station_id" required>@include('travel.station-options', ['by' => 'id'])</select>
            </label>
            <label>Receiving station
                <select name="destination_station_id" required>@include('travel.station-options', ['by' => 'id'])</select>
            </label>
            <label>Expected receiving date
                <input type="date" name="expected_on" required min="{{ $today }}" value="{{ old('expected_on', $expectedDefault) }}">
            </label>
            <label>Packages
                <input name="package_count" type="number" min="1" value="1">
            </label>
            <label class="wide">Description
                <input name="description" required placeholder="What is in the parcel">
            </label>
            <label>Weight in grams
                <input name="weight_grams" type="number" min="0">
            </label>
            <label>Fee (XAF)
                <input name="fee_xaf" type="number" min="1" required>
            </label>
        </div>
        <p class="muted">Enter the agency fee. A saved tariff is used instead when one matches this route and weight.</p>
        <p>
            <button type="button" class="back-step" data-next="2">Back</button>
            <button type="button" class="primary next" data-next="4">Next</button>
        </p>
    </section>

    <section data-pane="4" hidden>
        <h2>How will they pay?</h2>
        <div class="summary" id="pay-summary"></div>
        <div class="pay-row">
            <button class="pay-choice" type="submit" name="channel" value="cash"><b>Cash</b><span>Register the parcel and send the invoice to the sender’s phone.</span></button>
            <button class="pay-choice momo" type="submit" name="channel" value="momo"><b>Mobile Money</b><span>Confirm the paying number. The invoice goes out after the payment is confirmed.</span></button>
        </div>
        <p><button type="button" class="back-step" data-next="3">Back</button></p>
    </section>
</form>
@else
<form class="panel form" method="get" action="{{ route('travel.parcels') }}">
    <input type="hidden" name="desk" value="collection">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <label>Station
        <select name="station" required onchange="this.form.submit()">
            <option value="">Choose the station</option>
            @include('travel.station-options', ['by' => 'id', 'selected' => $stationId])
        </select>
    </label>
</form>
@if($stationId)
    <nav class="switch">
        <a class="{{ $tab === 'awaiting' ? 'on' : '' }}" href="{{ route('travel.parcels', ['desk' => 'collection', 'station' => $stationId, 'tab' => 'awaiting']) }}">Awaiting collection</a>
        <a class="{{ $tab === 'collected' ? 'on' : '' }}" href="{{ route('travel.parcels', ['desk' => 'collection', 'station' => $stationId, 'tab' => 'collected']) }}">Collected</a>
    </nav>
    <div class="rows">
        @forelse($rows as $parcel)
            <a class="parcel-row" href="{{ route('travel.parcels', ['desk' => 'collection', 'station' => $stationId, 'tab' => $tab, 'open' => $parcel->id]) }}">
                <span>
                    <b>{{ $parcel->tracking_ref }}</b><br>
                    <small>{{ $parcel->receiver_name }} · {{ $names[$parcel->origin_station_id] ?? '' }} → {{ $names[$parcel->destination_station_id] ?? '' }}</small>
                </span>
                <span class="tag">{{ str_replace('_', ' ', $parcel->status) }}</span>
            </a>
        @empty
            <p class="notice">{{ $tab === 'collected' ? 'Nothing has been collected at this station yet.' : 'No parcels are waiting at this station.' }}</p>
        @endforelse
    </div>
    @if($open)
        <section class="panel handover">
            <span class="tag">{{ str_replace('_', ' ', $open->status) }}</span>
            <h2>{{ $open->tracking_ref }}</h2>
            <p>{{ $open->sender_name }} sent {{ $open->description }} to {{ $open->receiver_name }}.</p>
            <p class="muted">Receiver {{ $open->receiver_phone }}@if($open->expected_on) · expected {{ \Carbon\Carbon::parse($open->expected_on)->format('j M Y') }}@endif</p>
            @php $next = ['accepted'=>'assigned','assigned'=>'loaded','loaded'=>'in_transit','in_transit'=>'received','received'=>'ready']; @endphp
            @if(isset($next[$open->status]))
                <form method="post" action="{{ route('travel.parcels.scan', $open->id) }}">
                    @csrf
                    <input type="hidden" name="event" value="{{ $next[$open->status] }}">
                    <input type="hidden" name="station_id" value="{{ $stationId }}">
                    <button class="primary">Mark {{ str_replace('_', ' ', $next[$open->status]) }}</button>
                </form>
            @elseif($open->status === 'ready')
                <form method="post" action="{{ route('travel.otp') }}">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $open->receiver_phone }}">
                    <input type="hidden" name="purpose" value="parcel:{{ $open->id }}">
                    <button type="submit">Send OTP to the receiver</button>
                </form>
                <form class="collect" method="post" action="{{ route('travel.parcels.collect', $open->id) }}">
                    @csrf
                    <input type="hidden" name="station_id" value="{{ $stationId }}">
                    <div class="choices">
                        <label><input type="radio" name="handover" value="signature" checked> Sign</label>
                        <label><input type="radio" name="handover" value="otp"> OTP</label>
                    </div>
                    <div data-mode="signature">
                        <p>Receiver signature</p>
                        <canvas width="420" height="120" aria-label="Signature pad"></canvas>
                        <button type="button" class="clear">Clear signature</button>
                    </div>
                    <label data-mode="otp" hidden>Pickup code
                        <input name="proof" inputmode="numeric" autocomplete="one-time-code">
                    </label>
                    <input type="hidden" name="signature">
                    <p><button class="primary">Confirm handover</button></p>
                </form>
            @else
                <p class="notice">Handed over.</p>
            @endif
        </section>
    @endif
@endif
@endif
<script>
(function () {
    var form = document.getElementById('send-form');
    if (!form) return;
    var token = form.querySelector('input[name=_token]').value;
    var step = 1;
    function show(next) {
        step = next;
        form.querySelectorAll('[data-pane]').forEach(function (pane) {
            pane.hidden = pane.getAttribute('data-pane') !== String(step);
        });
        form.querySelectorAll('[data-dot]').forEach(function (dot) {
            dot.classList.toggle('on', Number(dot.getAttribute('data-dot')) <= step);
        });
        if (step === 4) {
            var summary = document.getElementById('pay-summary');
            var origin = form.origin_station_id.options[form.origin_station_id.selectedIndex].text;
            var destination = form.destination_station_id.options[form.destination_station_id.selectedIndex].text;
            summary.innerHTML = '<b>' + form.sender_name.value + '</b> to <b>' + form.receiver_name.value + '</b><span>' + origin + ' → ' + destination + ' · ' + form.expected_on.value + '</span><span>' + form.description.value + ' · ' + Number(form.fee_xaf.value).toLocaleString() + ' XAF</span>';
        }
    }
    form.querySelectorAll('[data-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = Number(button.getAttribute('data-next'));
            if (target > step && !ready(step)) return;
            show(target);
        });
    });
    function ready(current) {
        if (current === 1) return named('sender_phone', 'sender_name', 'sender-note');
        if (current === 2) return named('receiver_phone', 'receiver_name', 'receiver-note');
        if (current === 3) {
            if (form.origin_station_id.value === form.destination_station_id.value) return false;
            return form.expected_on.value && form.description.value.trim() && Number(form.fee_xaf.value) >= 1;
        }
        return true;
    }
    function named(phone, name, note) {
        var box = document.getElementById(note);
        return form[phone].value.trim() !== '' && box && !box.hidden && form[name].value.trim() !== '';
    }
    form.addEventListener('submit', function (e) {
        if (!ready(1) || !ready(2) || !ready(3)) e.preventDefault();
    });
    form.querySelectorAll('.find').forEach(function (button) {
        button.addEventListener('click', function () {
            var phone = form[button.getAttribute('data-phone')];
            var name = form[button.getAttribute('data-name')];
            var box = document.getElementById(button.getAttribute('data-note'));
            button.disabled = true;
            fetch('{{ route('travel.parcels.lookup') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({phone: phone.value})
            }).then(function (response) {
                return response.json().then(function (body) { return {ok: response.ok, body: body}; });
            }).then(function (result) {
                box.hidden = false;
                box.querySelector('[data-copy]').textContent = result.body.message || 'Type the name the client confirms.';
                if (result.ok && result.body.phone) phone.value = result.body.phone;
                if (result.ok && result.body.name) name.value = result.body.name;
            }).catch(function () {
                box.hidden = false;
                box.querySelector('[data-copy]').textContent = 'The name could not be read. Type the name the client confirms.';
            }).then(function () { button.disabled = false; });
        });
    });
})();
(function () {
    var form = document.querySelector('form.collect');
    if (!form) return;
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
        form.signature.value = '';
    });
    form.querySelectorAll('[name=handover]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var otp = form.querySelector('[name=handover]:checked').value === 'otp';
            form.querySelector('[data-mode=signature]').hidden = otp;
            form.querySelector('[data-mode=otp]').hidden = !otp;
        });
    });
    form.addEventListener('submit', function (e) {
        var handover = form.querySelector('[name=handover]:checked').value;
        if (handover === 'signature') {
            if (!signed) { e.preventDefault(); return; }
            form.signature.value = canvas.toDataURL();
        }
    });
})();
</script>
@endsection
