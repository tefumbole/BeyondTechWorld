<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Happy Birthday Ma Mbole</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Cinzel:wght@600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold:#d4af37; --gold2:#f0d57a; --navy:#0b2a5c; --ink:#1c160e; --paper:#fffaf0; --muted:#6b6258; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: "Source Sans Pro", sans-serif; background: #120e18; color: var(--ink); }
        .wrap { max-width: 520px; margin: 0 auto; padding: 16px 14px 40px; }
        .hero { text-align: center; color: #fff; padding: 18px 8px 8px; }
        .hero p.kicker { letter-spacing: .22em; text-transform: uppercase; font-size: 11px; color: var(--gold); margin: 0 0 6px; font-weight: 700; }
        .hero h1 { font-family: "Great Vibes", cursive; font-size: 42px; font-weight: 400; margin: 0; color: var(--gold2); }
        .hero .sub { font-family: Cinzel, serif; font-size: 13px; margin: 6px 0 0; color: #f3e6c4; }
        .flyer { width: 100%; border-radius: 16px; display: block; box-shadow: 0 18px 40px rgba(0,0,0,.45); border: 2px solid rgba(212,175,55,.45); }
        .card { background: var(--paper); border-radius: 18px; padding: 16px; margin-top: 16px; box-shadow: 0 10px 28px rgba(0,0,0,.28); }
        label { display: block; font-size: 12px; font-weight: 700; color: var(--navy); margin: 10px 0 5px; }
        .hint { font-size: 12px; color: var(--muted); margin: 4px 0 0; }
        .phone-row { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        input, select { width: 100%; height: 46px; border: 1px solid #e4d3a4; border-radius: 10px; padding: 0 12px; font-size: 16px; background: #fff; }
        .status { font-size: 12px; margin-top: 6px; display: none; }
        .selfie-box { border: 1px dashed #d4af37; border-radius: 14px; padding: 12px; background: #fff; }
        .selfie-stage { position: relative; width: 180px; height: 180px; margin: 0 auto 10px; border-radius: 50%; overflow: hidden; background: #0b2a5c; box-shadow: 0 0 0 5px #d4af37, 0 0 24px rgba(212,175,55,.45); }
        .selfie-stage video, .selfie-stage img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .selfie-stage.is-live video { display: block; }
        .selfie-stage.is-shot img { display: block; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        button, .btn { appearance: none; border: 0; border-radius: 999px; padding: 11px 16px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #c9a227); color: #1c160e; }
        .btn-navy { background: var(--navy); color: #fff; width: 100%; margin-top: 14px; height: 50px; }
        .btn-ghost { background: #fff; color: var(--navy); border: 1px solid #d4af37; }
        .err { color: #991b1b; font-size: 13px; min-height: 1em; margin-top: 8px; }
        .busy { opacity: .7; pointer-events: none; }
        @media (max-width: 420px) { .phone-row { grid-template-columns: 1fr; } .hero h1 { font-size: 36px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <p class="kicker">18 September 2026</p>
        <h1>Happy Birthday</h1>
        <p class="sub">A flyer for Ma Mbole — made with your name</p>
    </div>

    <img class="flyer" id="flyerPreview" src="{{ $templateUrl }}" alt="Birthday flyer">

    <form class="card" id="bform" method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">

        <label>WhatsApp number *</label>
        <div class="phone-row">
            <select name="country_code" id="countryCode">
                @foreach ($countries as $c)
                    <option value="{{ $c['code'] }}" {{ $c['code'] === '+237' ? 'selected' : '' }}>{{ $c['code'] }}</option>
                @endforeach
            </select>
            <input type="tel" name="phone" id="phone" placeholder="675321739" inputmode="numeric" autocomplete="tel" required>
        </div>
        <p class="status" id="phoneStatus"></p>

        <label>Your name (as it should appear) *</label>
        <input type="text" name="display_name" id="displayName" required maxlength="80" placeholder="Your name">
        <p class="hint">Filled from your number when we know it. You can edit it.</p>

        <label>What do you call her? *</label>
        <input type="text" name="call_name" id="callName" required maxlength="80" value="Ma Mbole" placeholder="Ma Mbole">
        <p class="hint">This name is written on the flyer.</p>

        <label>Selfie (optional)</label>
        <div class="selfie-box">
            <div class="selfie-stage" id="selfieStage">
                <video id="selfieVideo" playsinline autoplay muted></video>
                <img id="selfieShot" alt="Selfie">
            </div>
            <div class="toolbar">
                <button type="button" class="btn-gold" id="camBtn">Enable camera</button>
                <button type="button" class="btn-ghost" id="captureBtn" style="display:none;">Take photo</button>
                <button type="button" class="btn-ghost" id="retakeBtn" style="display:none;">Remove selfie</button>
                <label class="btn-ghost" for="selfieFile">Choose photo</label>
            </div>
            <input type="file" id="selfieFile" accept="image/*" capture="user" style="position:absolute;left:-9999px;">
            <p class="hint" id="selfieHint">If you add a photo, the background is removed and it sits in a gold ring at the bottom. Skip this if you prefer no selfie.</p>
        </div>

        <p class="err" id="formErr">@if($errors->any()){{ $errors->first() }}@endif</p>
        <button type="submit" class="btn-navy" id="goBtn">Make my flyer</button>
    </form>
</div>

<script src="{{ asset('public/js/phone-name-lookup.js') }}"></script>
<script>
(function () {
    attachPhoneNameLookup({
        url: @json($lookupUrl),
        phone: '#phone',
        code: '#countryCode',
        name: '#displayName',
        status: '#phoneStatus'
    });

    var video = document.getElementById('selfieVideo');
    var shot = document.getElementById('selfieShot');
    var stage = document.getElementById('selfieStage');
    var camBtn = document.getElementById('camBtn');
    var captureBtn = document.getElementById('captureBtn');
    var retakeBtn = document.getElementById('retakeBtn');
    var fileInput = document.getElementById('selfieFile');
    var hint = document.getElementById('selfieHint');
    var form = document.getElementById('bform');
    var err = document.getElementById('formErr');
    var goBtn = document.getElementById('goBtn');
    var stream = null;
    var selfieFile = null;

    function setStage(mode) {
        stage.classList.remove('is-live', 'is-shot');
        if (mode) stage.classList.add(mode);
    }
    function stopCam() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
    }
    function blobToFile(blob, name) {
        try {
            return new File([blob], name, { type: blob.type || 'image/png' });
        } catch (e) {
            return blob;
        }
    }

    async function cutout(blob) {
        hint.textContent = 'Cutting the background… this can take a moment.';
        try {
            var mod = await import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/+esm');
            var out = await mod.removeBackground(blob, {
                publicPath: 'https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/dist/',
                device: 'cpu'
            });
            hint.textContent = 'Background removed. Your photo will sit in the gold ring.';
            return out;
        } catch (e) {
            hint.textContent = 'Could not cut the background on this phone — we will still place your photo in the ring.';
            return blob;
        }
    }

    async function useImageBlob(blob) {
        var cut = await cutout(blob);
        selfieFile = blobToFile(cut, 'selfie.png');
        var url = URL.createObjectURL(cut);
        shot.src = url;
        setStage('is-shot');
        captureBtn.style.display = 'none';
        camBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-flex';
        stopCam();
    }

    camBtn.addEventListener('click', function () {
        err.textContent = '';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function (s) {
            stream = s;
            video.srcObject = s;
            setStage('is-live');
            captureBtn.style.display = 'inline-flex';
            camBtn.style.display = 'none';
        }).catch(function () {
            err.textContent = 'Camera was blocked. Choose a photo instead.';
        });
    });

    captureBtn.addEventListener('click', function () {
        var c = document.createElement('canvas');
        c.width = video.videoWidth || 720;
        c.height = video.videoHeight || 720;
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
            if (blob) useImageBlob(blob);
        }, 'image/jpeg', 0.9);
    });

    retakeBtn.addEventListener('click', function () {
        selfieFile = null;
        shot.removeAttribute('src');
        setStage('');
        retakeBtn.style.display = 'none';
        camBtn.style.display = 'inline-flex';
        hint.textContent = 'If you add a photo, the background is removed and it sits in a gold ring at the bottom.';
    });

    fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) return;
        useImageBlob(f);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.textContent = '';
        if (!document.getElementById('displayName').value.trim()) {
            err.textContent = 'Enter the name that should appear.';
            return;
        }
        goBtn.classList.add('busy');
        goBtn.textContent = 'Designing your flyer…';
        var body = new FormData(form);
        if (selfieFile) body.set('selfie', selfieFile, 'selfie.png');
        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) {
            return r.text().then(function (t) {
                var j = {};
                try { j = JSON.parse(t); } catch (e) {}
                return { ok: r.ok, j: j };
            });
        }).then(function (res) {
            if (res.j && res.j.redirect) {
                window.location.href = res.j.redirect;
                return;
            }
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Make my flyer';
            var msg = (res.j && res.j.message) ? res.j.message : 'Could not create the flyer.';
            if (res.j && res.j.errors) {
                var first = Object.keys(res.j.errors)[0];
                if (first && res.j.errors[first][0]) msg = res.j.errors[first][0];
            }
            err.textContent = msg;
        }).catch(function () {
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Make my flyer';
            err.textContent = 'Network error. Please try again.';
        });
    });
})();
</script>
</body>
</html>
