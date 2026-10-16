<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Join · Nkwen Baptist Church Praise Team</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #10284f; --gold: #e4c16a; --ink: #1d2433; --muted: #6d7380; --cream: #f6f3ec; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: "Source Sans Pro", sans-serif; color: var(--ink); background: var(--cream); overflow-x: hidden; }
        a { color: var(--navy); }
        .wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 18px 14px 48px; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        .brand { color: var(--navy); text-decoration: none; }
        .brand strong { display: block; font-size: 16px; line-height: 1.2; }
        .brand span { display: block; font-size: 13px; color: #5c6574; }
        .card { background: #fff; border-radius: 22px; padding: 26px 22px 18px; box-shadow: 0 18px 50px rgba(16, 40, 79, .12); }
        .kicker { margin: 0 0 8px; letter-spacing: .18em; font-size: 12px; font-weight: 700; color: #8a6a22; }
        h1 { font-family: "Playfair Display", serif; font-size: 34px; line-height: 1.1; margin: 0 0 6px; color: var(--navy); }
        .lead { margin: 0 0 16px; color: #5d6572; }
        .step { display: none; }
        .step.is-on { display: block; }
        label { display: block; font-size: 14px; font-weight: 700; color: var(--navy); margin: 14px 0 6px; }
        .choice { font-weight: 600; margin: 8px 0; }
        .choice input { width: auto; margin-right: 8px; }
        input[type="text"], input[type="tel"], input[type="number"], input[type="email"], input[type="password"], input[type="date"], select, textarea {
            width: 100%; min-height: 46px; border: 1px solid #e4e0d8; border-radius: 12px; padding: 10px 12px; font: inherit; font-size: 16px; background: #fff; color: var(--ink);
        }
        textarea { min-height: 110px; }
        .phone-row { display: grid; grid-template-columns: 88px minmax(0, 1fr); gap: 8px; }
        .row-2 { display: flex; gap: 8px; flex-wrap: wrap; }
        .row-2 label { flex: 1; min-width: 140px; }
        button, .btn { border: 0; background: var(--navy); color: #fff; border-radius: 999px; padding: 10px 16px; font-weight: 700; font-family: inherit; font-size: 15px; cursor: pointer; }
        button.ghost { background: #eef1f6; color: var(--navy); }
        button:disabled { opacity: .55; cursor: default; }
        .actions { display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap; }
        .note { font-size: 13px; color: var(--muted); margin: 8px 0 0; }
        .alert { background: #f8f1df; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
        .alert.error { background: #fdecea; color: #7a241e; }
        .gift { border: 1px solid #eee7da; border-radius: 12px; padding: 8px 10px; margin: 8px 0; }
        .gift select { margin-top: 8px; display: none; }
        .gift.is-on select { display: block; }
        .bylaws { white-space: pre-wrap; max-height: 220px; overflow: auto; background: #f8f6f1; border-radius: 12px; padding: 12px; font-size: 14px; line-height: 1.5; }
        canvas { width: 100%; height: 150px; border: 1px dashed #e4e0d8; border-radius: 12px; background: #fff; touch-action: none; }
        .progress { font-size: 13px; color: var(--muted); font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="{{ route('nbc.home') }}">
            <strong>Nkwen Baptist Church</strong>
            <span>Praise Team</span>
        </a>
        <a href="{{ route('nbc.login') }}">Sign in</a>
    </header>
    <div class="card">
        @if(session('nbc_message'))
            <p class="kicker">Application</p>
            <h1>You are on the list</h1>
            <p class="lead">{{ session('nbc_message') }}</p>
            <a class="btn" href="{{ route('nbc.home') }}">Back to the site</a>
        @else
            <p class="kicker">Join</p>
            <h1 id="step-title">Your phone</h1>
            <p class="progress" id="step-progress">Step 1 of 11</p>
            @if(session('nbc_error'))<div class="alert error">{{ session('nbc_error') }}</div>@endif
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('nbc.join.store') }}" id="join-form">
                @csrf
                <input type="hidden" name="phone" id="phone-full" value="{{ old('phone', $verifiedPhone) }}">

                <section class="step is-on" data-step="1" data-title="Your phone">
                    <p class="lead">We send a code to this number. Choose WhatsApp or a text message.</p>
                    <label>Phone number</label>
                    <div class="phone-row">
                        <input type="tel" id="dial" value="237" inputmode="numeric" aria-label="Country code">
                        <input type="tel" id="local-phone" placeholder="6XX XXX XXX" inputmode="numeric">
                    </div>
                    <div class="row-2">
                        <label class="choice"><input type="radio" name="channel_pick" value="whatsapp" checked> WhatsApp</label>
                        <label class="choice"><input type="radio" name="channel_pick" value="sms"> Message</label>
                    </div>
                    <div class="actions">
                        <button type="button" id="send-code">Send code</button>
                    </div>
                    <div id="code-box" style="display:none">
                        <label for="otp-code">Code</label>
                        <input id="otp-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                        <div class="actions">
                            <button type="button" id="verify-code">Verify</button>
                        </div>
                    </div>
                    <p class="note" id="phone-note"></p>
                </section>

                <section class="step" data-step="2" data-title="About you">
                    <label>Sex</label>
                    <label class="choice"><input type="radio" name="sex" value="male" {{ old('sex') === 'male' ? 'checked' : '' }} required> Male</label>
                    <label class="choice"><input type="radio" name="sex" value="female" {{ old('sex') === 'female' ? 'checked' : '' }}> Female</label>
                    <label>Marital status</label>
                    <select name="marital_status" required>
                        <option value="">Select</option>
                        @foreach(['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'divorced' => 'Divorced'] as $key => $label)
                            <option value="{{ $key }}" {{ old('marital_status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label>Age</label>
                    <input type="number" name="age" min="5" max="120" value="{{ old('age') }}" required>
                    <label>Quarter where you live</label>
                    <input type="text" name="quarter" value="{{ old('quarter') }}" required>
                    <label>Immediate contact number</label>
                    <input type="tel" name="immediate_contact" value="{{ old('immediate_contact') }}" required>
                    <label>Education</label>
                    <select name="education" required>
                        <option value="">Select</option>
                        @foreach($catalog['education'] as $key => $label)
                            <option value="{{ $key }}" {{ old('education') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label>Area of work</label>
                    <input type="text" name="area_of_work" value="{{ old('area_of_work') }}" placeholder="Computer Engineering, Medical Nurse" required>
                </section>

                <section class="step" data-step="3" data-title="Your name">
                    <p class="lead">This starts as the name on your MoMo account. Change it to the name you want to be called.</p>
                    <label>Name</label>
                    <input type="text" name="name" id="calling-name" value="{{ old('name') }}" required>
                </section>

                <section class="step" data-step="4" data-title="Church membership">
                    <label class="choice"><input type="radio" name="church_membership" value="member" {{ old('church_membership') === 'member' ? 'checked' : '' }} required> I am a member already</label>
                    <label class="choice"><input type="radio" name="church_membership" value="admit" {{ old('church_membership') === 'admit' ? 'checked' : '' }}> I am still to be admitted</label>
                </section>

                <section class="step" data-step="5" data-title="Title">
                    <label>Title</label>
                    <select name="title" id="title" required>
                        <option value="">Select</option>
                        @foreach($catalog['titles'] as $key => $label)
                            <option value="{{ $key }}" {{ old('title') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div id="title-other" style="display:none">
                        <label>Specify your title</label>
                        <input type="text" name="title_other" value="{{ old('title_other') }}">
                    </div>
                </section>

                <section class="step" data-step="6" data-title="Baptism">
                    <label>Have you been baptised by immersion?</label>
                    <label class="choice"><input type="radio" name="baptised" value="yes" {{ old('baptised') === 'yes' ? 'checked' : '' }} required> Yes</label>
                    <label class="choice"><input type="radio" name="baptised" value="no" {{ old('baptised') === 'no' ? 'checked' : '' }}> No</label>
                    <div id="inquirer-box" style="display:none">
                        <label class="choice"><input type="checkbox" name="inquirer" value="1" {{ old('inquirer') ? 'checked' : '' }}> I am undergoing inquirer classes</label>
                    </div>
                </section>

                <section class="step" data-step="7" data-title="Salvation">
                    <label>Salvation testimony</label>
                    @foreach(['born_again' => 'Born Again', 'not_born_again' => 'Not Born Again', 'other' => 'Other', 'dont_know' => "Don't Know"] as $key => $label)
                        <label class="choice"><input type="radio" name="salvation" value="{{ $key }}" {{ old('salvation') === $key ? 'checked' : '' }} required> {{ $label }}</label>
                    @endforeach
                    <div id="born-box" style="display:none">
                        <label>Year <span class="note">not required</span></label>
                        <input type="number" name="salvation_year" min="1900" max="2100" value="{{ old('salvation_year') }}">
                        <label>Date <span class="note">not required</span></label>
                        <input type="date" name="salvation_date" value="{{ old('salvation_date') }}">
                        <label>Tell us your salvation testimony</label>
                        <textarea name="salvation_testimony">{{ old('salvation_testimony') }}</textarea>
                    </div>
                </section>

                <section class="step" data-step="8" data-title="Church wing">
                    <label>Do you belong to any wing of the church?</label>
                    <select name="wing" id="wing" required>
                        <option value="">Select</option>
                        @foreach($catalog['wings'] as $key => $label)
                            <option value="{{ $key }}" {{ old('wing') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div id="wing-other" style="display:none">
                        <label>Specify</label>
                        <input type="text" name="wing_other" value="{{ old('wing_other') }}">
                    </div>
                </section>

                <section class="step" data-step="9" data-title="Why you want to join">
                    <label>Why do you want to join the praise team?</label>
                    <textarea name="reason" required>{{ old('reason') }}</textarea>
                </section>

                <section class="step" data-step="10" data-title="Your gifts">
                    <p class="lead">Tick everything that fits. Each one has its own level. You can be a lead singer and a backup singer, at different levels.</p>
                    <label>Instruments</label>
                    @foreach($catalog['instruments'] as $key => $label)
                        <div class="gift">
                            <label class="choice"><input type="checkbox" name="instruments[]" value="{{ $key }}" {{ in_array($key, (array) old('instruments', []), true) ? 'checked' : '' }}> {{ $label }}</label>
                            <select name="instrument_level[{{ $key }}]">
                                <option value="">Level</option>
                                @foreach($catalog['levels'] as $level => $levelLabel)
                                    <option value="{{ $level }}" {{ old('instrument_level.'.$key) === $level ? 'selected' : '' }}>{{ $levelLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div id="instrument-other" style="display:none">
                        <label>Which instrument?</label>
                        <input type="text" name="instrument_other" value="{{ old('instrument_other') }}">
                    </div>
                    <label>Vocals</label>
                    @foreach($catalog['vocals'] as $key => $label)
                        <div class="gift">
                            <label class="choice"><input type="checkbox" name="vocals[]" value="{{ $key }}" {{ in_array($key, (array) old('vocals', []), true) ? 'checked' : '' }}> {{ $label }}</label>
                            <select name="vocal_level[{{ $key }}]">
                                <option value="">Level</option>
                                @foreach($catalog['levels'] as $level => $levelLabel)
                                    <option value="{{ $level }}" {{ old('vocal_level.'.$key) === $level ? 'selected' : '' }}>{{ $levelLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <label>Genre</label>
                    @foreach($catalog['genres'] as $key => $label)
                        <div class="gift">
                            <label class="choice"><input type="checkbox" name="genres[]" value="{{ $key }}" {{ in_array($key, (array) old('genres', []), true) ? 'checked' : '' }}> {{ $label }}</label>
                            <select name="genre_level[{{ $key }}]">
                                <option value="">Level</option>
                                @foreach($catalog['levels'] as $level => $levelLabel)
                                    <option value="{{ $level }}" {{ old('genre_level.'.$key) === $level ? 'selected' : '' }}>{{ $levelLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </section>

                <section class="step" data-step="11" data-title="Bylaws and account">
                    <p class="lead">Bylaws version {{ $bylaws->version }}. Read them, then sign.</p>
                    <div class="bylaws">{{ $bylaws->body }}</div>
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    <label>Password</label>
                    <input type="password" name="password" minlength="8" required>
                    <label class="choice"><input type="checkbox" name="agree" value="1" {{ old('agree') ? 'checked' : '' }} required> I have read the bylaws and I agree.</label>
                    <label>Signature</label>
                    <canvas id="pad" width="800" height="200"></canvas>
                    <input type="hidden" name="signature" id="signature">
                    <div class="actions"><button type="button" id="clear" class="ghost">Clear signature</button></div>
                </section>

                <div class="actions" id="nav-actions">
                    <button type="button" class="ghost" id="back" style="display:none">Back</button>
                    <button type="button" id="next">Continue</button>
                    <button type="submit" id="submit" style="display:none">Submit application</button>
                </div>
            </form>
        @endif
    </div>
</div>
@if(!session('nbc_message'))
<script>
(function () {
    var form = document.getElementById('join-form');
    var steps = Array.prototype.slice.call(document.querySelectorAll('.step'));
    var index = 0;
    var title = document.getElementById('step-title');
    var progress = document.getElementById('step-progress');
    var back = document.getElementById('back');
    var next = document.getElementById('next');
    var submit = document.getElementById('submit');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var verifiedPhone = @json($verifiedPhone);
    var phoneFull = document.getElementById('phone-full');
    var note = document.getElementById('phone-note');
    var verified = verifiedPhone !== '';

    function show(i) {
        index = i;
        steps.forEach(function (step, n) { step.classList.toggle('is-on', n === i); });
        title.textContent = steps[i].getAttribute('data-title');
        progress.textContent = 'Step ' + (i + 1) + ' of ' + steps.length;
        back.style.display = i === 0 ? 'none' : '';
        next.style.display = i === steps.length - 1 ? 'none' : '';
        submit.style.display = i === steps.length - 1 ? '' : 'none';
    }
    function currentDigits() {
        var dial = document.getElementById('dial').value.replace(/\D+/g, '');
        var local = document.getElementById('local-phone').value.replace(/\D+/g, '');
        var digits = dial + local;
        if (local.length === 9 && local.charAt(0) === '6') digits = '237' + local;
        return digits;
    }
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().then(function (payload) { return { ok: response.ok, payload: payload }; });
        });
    }
    function resetVerified() {
        verified = false;
        phoneFull.value = '';
    }
    document.getElementById('dial').addEventListener('input', resetVerified);
    document.getElementById('local-phone').addEventListener('input', resetVerified);
    document.getElementById('send-code').addEventListener('click', function () {
        note.textContent = 'Sending…';
        verified = false;
        post(@json(route('nbc.join.otp')), {
            dial: document.getElementById('dial').value,
            phone: document.getElementById('local-phone').value,
            channel: (form.querySelector('input[name="channel_pick"]:checked') || {}).value || 'whatsapp'
        }).then(function (result) {
            if (!result.ok || !result.payload.ok) {
                note.textContent = (result.payload && result.payload.error) ? result.payload.error : 'The code could not be sent.';
                return;
            }
            document.getElementById('code-box').style.display = '';
            note.textContent = 'Enter the code we sent.';
        }).catch(function () { note.textContent = 'The code could not be sent.'; });
    });
    document.getElementById('verify-code').addEventListener('click', function () {
        note.textContent = 'Checking…';
        post(@json(route('nbc.join.verify')), { code: document.getElementById('otp-code').value })
            .then(function (result) {
                if (!result.ok || !result.payload.ok) {
                    note.textContent = (result.payload && result.payload.error) ? result.payload.error : 'That code does not match.';
                    return;
                }
                verified = true;
                verifiedPhone = result.payload.phone;
                phoneFull.value = result.payload.phone;
                if (result.payload.name) document.getElementById('calling-name').value = result.payload.name;
                note.textContent = 'Phone verified.';
            }).catch(function () { note.textContent = 'That code could not be checked.'; });
    });

    function wireToggle(selectId, boxId, value) {
        var select = document.getElementById(selectId);
        var box = document.getElementById(boxId);
        if (!select || !box) return;
        function sync() { box.style.display = select.value === value ? '' : 'none'; }
        select.addEventListener('change', sync);
        sync();
    }
    wireToggle('title', 'title-other', 'other');
    wireToggle('wing', 'wing-other', 'other');
    function syncBaptism() {
        var chosen = form.querySelector('input[name="baptised"]:checked');
        document.getElementById('inquirer-box').style.display = chosen && chosen.value === 'no' ? '' : 'none';
    }
    function syncSalvation() {
        var chosen = form.querySelector('input[name="salvation"]:checked');
        document.getElementById('born-box').style.display = chosen && chosen.value === 'born_again' ? '' : 'none';
    }
    Array.prototype.forEach.call(form.querySelectorAll('input[name="baptised"]'), function (input) {
        input.addEventListener('change', syncBaptism);
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="salvation"]'), function (input) {
        input.addEventListener('change', syncSalvation);
    });
    syncBaptism();
    syncSalvation();
    Array.prototype.forEach.call(document.querySelectorAll('.gift'), function (gift) {
        var box = gift.querySelector('input[type="checkbox"]');
        var level = gift.querySelector('select');
        function sync() {
            gift.classList.toggle('is-on', box.checked);
            if (box.value === 'other' && box.name === 'instruments[]') {
                document.getElementById('instrument-other').style.display = box.checked ? '' : 'none';
            }
        }
        box.addEventListener('change', sync);
        sync();
        level.dataset.bound = '1';
    });

    function missing(step) {
        var n = step.getAttribute('data-step');
        if (n === '1') {
            if (!verified || phoneFull.value === '') return 'Verify the code sent to your phone.';
            return '';
        }
        var fields = step.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.type === 'radio') {
                var group = step.querySelectorAll('input[name="' + field.name + '"]');
                var any = false;
                for (var g = 0; g < group.length; g++) if (group[g].checked) any = true;
                if (!any && field.required) return 'Choose one option to continue.';
            } else if (field.required && !field.value.trim() && field.offsetParent !== null) {
                return 'Fill in the required fields.';
            }
        }
        if (n === '5' && document.getElementById('title').value === 'other' && !form.querySelector('[name="title_other"]').value.trim()) {
            return 'Specify your title.';
        }
        if (n === '8' && document.getElementById('wing').value === 'other' && !form.querySelector('[name="wing_other"]').value.trim()) {
            return 'Specify the church wing.';
        }
        if (n === '10') {
            var picked = step.querySelectorAll('input[type="checkbox"]:checked');
            if (!picked.length) return 'Choose at least one gift.';
            for (var p = 0; p < picked.length; p++) {
                var select = picked[p].closest('.gift').querySelector('select');
                if (!select.value) return 'Choose a level for each gift you select.';
                if (picked[p].value === 'other' && picked[p].name === 'instruments[]' && !form.querySelector('[name="instrument_other"]').value.trim()) {
                    return 'Name the other instrument.';
                }
            }
        }
        return '';
    }

    next.addEventListener('click', function () {
        var problem = missing(steps[index]);
        if (problem) { note.textContent = problem; return; }
        note.textContent = '';
        show(index + 1);
    });
    back.addEventListener('click', function () { show(index - 1); });

    var canvas = document.getElementById('pad');
    var hidden = document.getElementById('signature');
    var context = canvas.getContext('2d');
    var drawing = false;
    var signed = false;
    function point(event) {
        var rect = canvas.getBoundingClientRect();
        var source = event.touches ? event.touches[0] : event;
        return { x: (source.clientX - rect.left) * (canvas.width / rect.width), y: (source.clientY - rect.top) * (canvas.height / rect.height) };
    }
    function start(event) {
        drawing = true; signed = true;
        var p = point(event);
        context.beginPath();
        context.moveTo(p.x, p.y);
        event.preventDefault();
    }
    function move(event) {
        if (!drawing) return;
        var p = point(event);
        context.lineWidth = 2; context.lineCap = 'round'; context.strokeStyle = '#10284f';
        context.lineTo(p.x, p.y); context.stroke();
        event.preventDefault();
    }
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', function () { drawing = false; });
    canvas.addEventListener('touchstart', start, {passive: false});
    canvas.addEventListener('touchmove', move, {passive: false});
    window.addEventListener('touchend', function () { drawing = false; });
    document.getElementById('clear').addEventListener('click', function () {
        context.clearRect(0, 0, canvas.width, canvas.height);
        signed = false;
    });
    form.addEventListener('submit', function (event) {
        if (!signed) {
            event.preventDefault();
            note.textContent = 'Sign in the box before you continue.';
            show(steps.length - 1);
            return;
        }
        hidden.value = canvas.toDataURL('image/png');
    });
    if (verifiedPhone) {
        phoneFull.value = verifiedPhone;
        note.textContent = 'Phone verified.';
    }
    @if($errors->any() || session('nbc_error'))
        show(steps.length - 1);
    @else
        show(0);
    @endif
})();
</script>
@include('partials.otp_assist', ['otpInputId' => 'otp-code'])
@endif
</body>
</html>
