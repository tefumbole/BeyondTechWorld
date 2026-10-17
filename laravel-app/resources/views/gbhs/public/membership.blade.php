@extends('gbhs.public.layout')
@section('content')
@if($step === 'intro')
    <div style="text-align:center;padding:48px 0 24px">
        <h1>Become a Member</h1>
        <a class="btn gold" href="{{ route('gbhs.membership', ['register' => 1]) }}">Register</a>
    </div>
@elseif($step === 'phone')
    <div style="text-align:center">
        <h1>Become a Member</h1>
    </div>
    <form class="card" method="post" action="{{ route('gbhs.register.phone') }}">
        @csrf
        <label>Phone number</label>
        <input name="phone" required placeholder="6XXXXXXXX" inputmode="tel" autocomplete="tel">
        <button type="submit">Continue</button>
    </form>
@elseif($step === 'profile')
    <form class="card" method="post" action="{{ route('gbhs.register.profile') }}" enctype="multipart/form-data">
        @csrf
        <p class="muted">{{ $application->full_name ? 'This name came from the phone number. You can edit it.' : 'No name was found for this number. Type your name.' }}</p>
        <label>Full name</label><input name="full_name" value="{{ $application->full_name }}" required>
        <label>Batch</label>
        <select name="batch_id" required>
            <option value="">Choose the year you finished or attended</option>
            @foreach($batches as $batch)
                <option value="{{ $batch->id }}" {{ (int)$application->batch_id === (int)$batch->id ? 'selected' : '' }}>{{ $batch->name }}</option>
            @endforeach
        </select>
        <label>Date of birth</label><input type="date" name="date_of_birth" value="{{ $application->date_of_birth }}">
        <label>Gender</label><input name="gender" value="{{ $application->gender }}">
        <label>Marital status</label><input name="marital_status" value="{{ $application->marital_status }}">
        <label>Years attended</label><input name="years_attended" value="{{ $application->years_attended }}">
        <label>Profession</label><input name="profession" value="{{ $application->profession }}">
        <label>Employer</label><input name="employer" value="{{ $application->employer }}">
        <label>Email</label><input type="email" name="email" value="{{ $application->email }}">
        <label>Country</label><input name="country" value="{{ $application->country ?: 'Cameroon' }}">
        <label>Region</label><input name="region" value="{{ $application->region }}">
        <label>City</label><input name="city" value="{{ $application->city }}">
        <label>Address</label><input name="address" value="{{ $application->address }}">
        <label>Photo</label><input type="file" name="photo" accept="image/*">
        @if($bylaw && $bylaw->file)
            <p><a href="{{ route('gbhs.bylaw', $bylaw->id) }}" target="_blank">Read bylaws {{ $bylaw->version }}</a></p>
            <label><input type="checkbox" name="agree_bylaws" value="1" style="width:auto"> I have read this bylaws version</label>
        @else
            <p>The association has not published a bylaws PDF yet, so this application cannot be submitted.</p>
        @endif
        <label><input type="checkbox" name="consent_processing" value="1" style="width:auto" required> I agree that the association may keep these details to review the application</label>
        <label><input type="checkbox" name="consent_announcements" value="1" style="width:auto"> Send me WhatsApp updates about this application</label>
        <button type="submit">Continue to signature</button>
    </form>
@elseif($step === 'sign')
    <form class="card" method="post" action="{{ route('gbhs.register.sign') }}">
        @csrf
        <p>Sign with a mouse or a finger. This image is stored with application {{ $application->reference }}.</p>
        <canvas id="pad" width="460" height="160" style="border:1px solid #d9cbb0;background:#fff;max-width:100%"></canvas>
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
    <div class="card">
        <p>Reference <strong>{{ $application->reference }}</strong></p>
        <p>Status: {{ $application->status }}</p>
        <p>Check it later with this reference and the same phone.</p>
    </div>
@endif
@endsection
