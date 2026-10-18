@extends('gbhs.public.layout')
@section('content')
<style>
    .join { max-width: 760px; margin: 0 auto; }
    .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 8px 0 18px; }
    .steps span { background: #fff; border: 1px solid #e4d3a2; border-radius: 999px; padding: 8px 10px; text-align: center; font-size: .95rem; color: #5c6b62; }
    .steps span.on { background: var(--forest); color: var(--gold); border-color: var(--forest); font-weight: 700; }
    .join-card { background: #fff; border: 1px solid #e4d3a2; border-radius: 16px; padding: 22px 22px 26px; box-shadow: 0 10px 30px rgba(18, 53, 31, .06); }
    .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .fields .wide { grid-column: 1 / -1; }
    .batch-box { position: relative; }
    .batch-box ul { list-style: none; margin: 4px 0 0; padding: 4px 0; max-height: 220px; overflow: auto; background: #fff; border: 1px solid #e4d3a2; border-radius: 10px; }
    .batch-box li { padding: 8px 12px; cursor: pointer; }
    .batch-box li:hover, .batch-box li.on { background: #f7f3e8; }
    .photo-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .photo-row button { margin: 8px 0 0; }
    .photo-preview { width: 96px; height: 96px; object-fit: cover; border-radius: 12px; border: 1px solid #e4d3a2; }
    .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 600; }
    .check input { width: auto; margin-top: 4px; }
    @media (max-width: 700px) { .fields, .steps { grid-template-columns: 1fr; } }
</style>
@php
    $batchRows = [];
    foreach ($batches as $batch) {
        $batchRows[] = ['id' => $batch->id, 'name' => $batch->name];
    }
@endphp
<div class="join">
@if($step === 'intro')
    <div class="join-card" style="text-align:center">
        <p class="muted" style="letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--gold-deep)">GBHS Kumbo</p>
        <h1>Become a Member</h1>
        <div class="steps">
            <span>1 · Your number</span>
            <span>2 · Your details</span>
            <span>3 · Your signature</span>
        </div>
        <p>Three short steps. We look up the name on your phone, then you choose your batch and sign.</p>
        <a class="btn gold" href="{{ route('gbhs.membership', ['register' => 1]) }}">Start</a>
    </div>
@elseif($step === 'phone')
    <div class="steps"><span class="on">1 · Your number</span><span>2 · Your details</span><span>3 · Your signature</span></div>
    <form class="join-card" method="post" action="{{ route('gbhs.register.phone') }}">
        @csrf
        <h1>Your number</h1>
        <p class="muted">Cameroon is selected. Search if your number is from another country.</p>
        <label>Phone number</label>
        <div class="phone-row">
            @include('gbhs.public.partials.country_code')
            <input name="phone" id="joinPhone" required placeholder="675321739" inputmode="tel" autocomplete="tel">
        </div>
        <label>Full name</label>
        <input name="full_name" id="joinName" required placeholder="Name from your number">
        <p class="muted" id="joinHint">Type the number. We fill the Mobile Money name when we find it. You can edit it.</p>
        <button type="submit">Continue</button>
    </form>
    <script>
        gbhsLookupName(
            document.getElementById('joinPhone'),
            document.querySelector('#joinPhone') && document.querySelector('form [data-cc-value]'),
            document.getElementById('joinName'),
            document.getElementById('joinHint')
        );
    </script>
@elseif($step === 'profile')
    <div class="steps"><span>1 · Your number</span><span class="on">2 · Your details</span><span>3 · Your signature</span></div>
    <form class="join-card" method="post" action="{{ route('gbhs.register.profile') }}" enctype="multipart/form-data">
        @csrf
        <h1>Your details</h1>
        <p class="muted">{{ $application->full_name ? 'This name came from the phone number. You can edit it.' : 'Type your name.' }}</p>
        <div class="fields">
            <div class="wide">
                <label>Full name</label>
                <input name="full_name" value="{{ $application->full_name }}" required>
            </div>
            <div class="wide batch-box">
                <label>Batch</label>
                <input type="search" id="batchSearch" placeholder="Search the year you finished or attended" autocomplete="off" required>
                <input type="hidden" name="batch_id" id="batchId" value="{{ $application->batch_id }}">
                <ul id="batchList"></ul>
            </div>
            <div><label>Date of birth</label><input type="date" name="date_of_birth" value="{{ $application->date_of_birth }}"></div>
            <div><label>Gender</label><input name="gender" value="{{ $application->gender }}"></div>
            <div><label>Marital status</label><input name="marital_status" value="{{ $application->marital_status }}"></div>
            <div><label>Years attended</label><input name="years_attended" value="{{ $application->years_attended }}"></div>
            <div><label>Profession</label><input name="profession" value="{{ $application->profession }}"></div>
            <div><label>Employer</label><input name="employer" value="{{ $application->employer }}"></div>
            <div><label>Email</label><input type="email" name="email" value="{{ $application->email }}"></div>
            <div><label>Country</label><input name="country" value="{{ $application->country ?: 'Cameroon' }}"></div>
            <div><label>Region</label><input name="region" value="{{ $application->region }}"></div>
            <div><label>City</label><input name="city" value="{{ $application->city }}"></div>
            <div class="wide"><label>Address</label><input name="address" value="{{ $application->address }}"></div>
            <div class="wide">
                <label>Photo</label>
                <div class="photo-row">
                    <button type="button" class="btn" id="photoCam">Take a selfie</button>
                    <button type="button" class="btn gold" id="photoFile">Attach a photo</button>
                    <img class="photo-preview" id="photoPreview" alt="" hidden>
                </div>
                <input type="file" name="photo" id="photoInput" accept="image/*" hidden>
                <p class="muted">Use the camera or choose a picture you already have.</p>
            </div>
        </div>
        @if($bylaw && $bylaw->file)
            <p><a href="{{ route('gbhs.bylaw', $bylaw->id) }}" target="_blank">Read bylaws {{ $bylaw->version }}</a></p>
            <label class="check"><input type="checkbox" name="agree_bylaws" value="1"> I have read this bylaws version</label>
        @else
            <p>The association has not published a bylaws PDF yet, so this application cannot be submitted.</p>
        @endif
        <label class="check"><input type="checkbox" name="consent_processing" value="1" required> I agree that the association may keep these details to review the application</label>
        <label class="check"><input type="checkbox" name="consent_announcements" value="1"> Send me WhatsApp updates about this application</label>
        <button type="submit">Continue to signature</button>
    </form>
    <script>
        (function () {
            var rows = @json($batchRows);
            var search = document.getElementById('batchSearch');
            var hidden = document.getElementById('batchId');
            var list = document.getElementById('batchList');
            var current = rows.filter(function (r) { return String(r.id) === String(hidden.value); })[0];
            if (current) search.value = current.name;
            function paint() {
                var q = (search.value || '').toLowerCase().trim();
                var shown = !q ? rows : rows.filter(function (r) { return String(r.name).toLowerCase().indexOf(q) !== -1; });
                list.innerHTML = '';
                shown.forEach(function (r) {
                    var li = document.createElement('li');
                    if (String(r.id) === String(hidden.value)) li.className = 'on';
                    li.textContent = r.name;
                    li.addEventListener('click', function () {
                        hidden.value = r.id;
                        search.value = r.name;
                        list.innerHTML = '';
                    });
                    list.appendChild(li);
                });
            }
            search.addEventListener('input', function () { hidden.value = ''; paint(); });
            search.addEventListener('focus', paint);
            document.addEventListener('click', function (e) { if (!search.parentNode.contains(e.target)) list.innerHTML = ''; });
            var input = document.getElementById('photoInput');
            var preview = document.getElementById('photoPreview');
            document.getElementById('photoCam').addEventListener('click', function () { gbhsPickPhoto(input, true, preview); });
            document.getElementById('photoFile').addEventListener('click', function () { gbhsPickPhoto(input, false, preview); });
            document.querySelector('form').addEventListener('submit', function (e) {
                if (!hidden.value) { e.preventDefault(); search.focus(); paint(); }
            });
        })();
    </script>
@elseif($step === 'sign')
    <div class="steps"><span>1 · Your number</span><span>2 · Your details</span><span class="on">3 · Your signature</span></div>
    <form class="join-card" method="post" action="{{ route('gbhs.register.sign') }}">
        @csrf
        <h1>Your signature</h1>
        <p>Sign with a mouse or a finger. This image is stored with application {{ $application->reference }}.</p>
        <canvas id="pad" width="640" height="180" style="border:1px dashed var(--forest);border-radius:12px;background:#fff;width:100%;touch-action:none"></canvas>
        <input type="hidden" name="signature" id="signature">
        <div><button type="button" id="clear">Clear</button> <button type="submit">Submit application</button></div>
    </form>
    <script>
        var canvas = document.getElementById('pad');
        var ctx = canvas.getContext('2d');
        var drawing = false;
        function pos(e) {
            var r = canvas.getBoundingClientRect();
            var p = e.touches ? e.touches[0] : e;
            return { x: (p.clientX - r.left) * (canvas.width / r.width), y: (p.clientY - r.top) * (canvas.height / r.height) };
        }
        function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', function () { drawing = false; });
        canvas.addEventListener('touchstart', start, {passive:false});
        canvas.addEventListener('touchmove', move, {passive:false});
        canvas.addEventListener('touchend', function () { drawing = false; });
        document.getElementById('clear').onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); };
        document.querySelector('form').onsubmit = function () { document.getElementById('signature').value = canvas.toDataURL('image/png'); };
    </script>
@else
    <div class="join-card">
        <h1>Application received</h1>
        <p>Reference <strong>{{ $application->reference }}</strong></p>
        <p>Status: {{ $application->status }}</p>
        <p>Check it later with this reference and the same phone.</p>
    </div>
@endif
</div>
@endsection
