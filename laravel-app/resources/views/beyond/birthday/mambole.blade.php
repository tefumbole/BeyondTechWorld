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
        body {
            font-family: "Source Sans Pro", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 520px at 50% -80px, rgba(212,175,55,.18), transparent 60%),
                linear-gradient(180deg, #0c0a12 0%, #161022 40%, #0b2a5c 100%);
        }
        .wrap { max-width: 440px; margin: 0 auto; padding: 28px 16px 48px; }
        .hero { text-align: center; color: #fff; padding: 8px 8px 18px; }
        .hero p.kicker { letter-spacing: .22em; text-transform: uppercase; font-size: 11px; color: var(--gold); margin: 0 0 8px; font-weight: 700; }
        .hero h1 { font-family: "Great Vibes", cursive; font-size: 48px; font-weight: 400; margin: 0; color: var(--gold2); }
        .hero .sub { font-family: Cinzel, serif; font-size: 12px; margin: 10px 0 0; color: #f3e6c4; line-height: 1.45; }
        .rule { width: 72px; height: 1px; margin: 16px auto 0; background: linear-gradient(90deg, transparent, var(--gold), transparent); }
        .card { background: var(--paper); border-radius: 18px; padding: 18px 16px 20px; box-shadow: 0 18px 40px rgba(0,0,0,.35); }
        label { display: block; font-size: 12px; font-weight: 700; color: var(--navy); margin: 10px 0 5px; }
        .hint { font-size: 12px; color: var(--muted); margin: 4px 0 0; }
        .phone-row { display: grid; grid-template-columns: 118px 1fr; gap: 8px; }
        input, select { width: 100%; height: 46px; border: 1px solid #e4d3a4; border-radius: 10px; padding: 0 12px; font-size: 16px; background: #fff; }
        .status { font-size: 12px; margin-top: 6px; display: none; }
        .selfie-box { border: 1px dashed #d4af37; border-radius: 14px; padding: 12px; background: #fff; }
        .selfie-stage { position: relative; width: 180px; height: 180px; margin: 0 auto 10px; border-radius: 50%; overflow: hidden; background: #0b2a5c; box-shadow: 0 0 0 5px #d4af37, 0 0 24px rgba(212,175,55,.45); }
        .selfie-stage video, .selfie-stage img { width: 100%; height: 100%; object-fit: cover; object-position: center 18%; display: none; }
        .selfie-stage.is-live video { display: block; }
        .selfie-stage.is-shot img { display: block; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        button, .btn { appearance: none; border: 0; border-radius: 999px; padding: 11px 16px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .btn-gold { background: linear-gradient(180deg, #f0d57a, #c9a227); color: #1c160e; }
        .btn-navy { background: var(--navy); color: #fff; width: 100%; margin-top: 14px; height: 50px; }
        .btn-ghost { background: #fff; color: var(--navy); border: 1px solid #d4af37; }
        .err { color: #991b1b; font-size: 13px; min-height: 1em; margin-top: 8px; }
        .busy { opacity: .7; pointer-events: none; }
        .step { display: none; }
        .step.is-on { display: block; }
        @media (max-width: 420px) { .phone-row { grid-template-columns: 1fr; } .hero h1 { font-size: 40px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <p class="kicker">18 September 2026</p>
        <h1>Happy Birthday</h1>
        <p class="sub">Send Ma Mbole a birthday flyer on WhatsApp</p>
        <div class="rule"></div>
    </div>

    <form class="card" id="bform" method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">

        <div class="step is-on" id="step1">
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

            <p class="err" id="step1Err"></p>
            <button type="button" class="btn-navy" id="nextBtn">Next</button>
        </div>

        <div class="step" id="step2">
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
                <p class="hint" id="selfieHint">The background is removed automatically and your face is placed in the gold ring. You can skip this.</p>
            </div>

            <p class="err" id="formErr">@if($errors->any()){{ $errors->first() }}@endif</p>
            <button type="submit" class="btn-navy" id="goBtn">Submit</button>
            <button type="button" class="btn-ghost" id="backBtn" style="width:100%;margin-top:10px;">Back</button>
        </div>
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

    var step1 = document.getElementById('step1');
    var step2 = document.getElementById('step2');
    var nextBtn = document.getElementById('nextBtn');
    var backBtn = document.getElementById('backBtn');
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
    var step1Err = document.getElementById('step1Err');
    var goBtn = document.getElementById('goBtn');
    var stream = null;
    var selfieFile = null;
    var cutoutMod = null;

    import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/+esm').then(function (mod) {
        cutoutMod = mod;
    }).catch(function () {});

    function showStep(n) {
        step1.classList.toggle('is-on', n === 1);
        step2.classList.toggle('is-on', n === 2);
        window.scrollTo(0, 0);
    }

    nextBtn.addEventListener('click', function () {
        step1Err.textContent = '';
        var phone = (document.getElementById('phone').value || '').replace(/\D/g, '');
        if (phone.length < 8) {
            step1Err.textContent = 'Enter a valid WhatsApp number.';
            return;
        }
        if (!document.getElementById('displayName').value.trim()) {
            step1Err.textContent = 'Enter the name that should appear.';
            return;
        }
        if (!document.getElementById('callName').value.trim()) {
            step1Err.textContent = 'Enter what you call her.';
            return;
        }
        showStep(2);
    });
    backBtn.addEventListener('click', function () {
        stopCam();
        showStep(1);
    });

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

    function cropFace(blob) {
        return new Promise(function (resolve) {
            var url = URL.createObjectURL(blob);
            var img = new Image();
            img.onload = function () {
                var w = img.naturalWidth;
                var h = img.naturalHeight;
                var c = document.createElement('canvas');
                c.width = w;
                c.height = h;
                var ctx = c.getContext('2d');
                ctx.drawImage(img, 0, 0);
                URL.revokeObjectURL(url);
                var data = ctx.getImageData(0, 0, w, h).data;
                var minX = w, minY = h, maxX = 0, maxY = 0, found = 0;
                var step = w * h > 400000 ? 2 : 1;
                for (var y = 0; y < h; y += step) {
                    for (var x = 0; x < w; x += step) {
                        var a = data[((y * w) + x) * 4 + 3];
                        if (a < 40) continue;
                        found++;
                        if (x < minX) minX = x;
                        if (y < minY) minY = y;
                        if (x > maxX) maxX = x;
                        if (y > maxY) maxY = y;
                    }
                }
                var total = Math.ceil(w / step) * Math.ceil(h / step);
                var sx, sy, side;
                if (found > 40 && found < total * 0.92) {
                    var bw = Math.max(1, maxX - minX);
                    var bh = Math.max(1, maxY - minY);
                    var headH = Math.max(bw * 0.95, bh * 0.58);
                    var cx = (minX + maxX) / 2;
                    var cy = minY + headH * 0.42;
                    side = Math.round(Math.max(bw, headH) * 1.16);
                    sx = Math.round(cx - side / 2);
                    sy = Math.round(cy - side / 2);
                } else {
                    side = Math.round(Math.min(w, h * 0.72));
                    sx = Math.round((w - side) / 2);
                    sy = Math.round(h * 0.08);
                }
                if (sx < 0) sx = 0;
                if (sy < 0) sy = 0;
                if (sx + side > w) sx = Math.max(0, w - side);
                if (sy + side > h) sy = Math.max(0, h - side);
                side = Math.min(side, w - sx, h - sy);
                var out = document.createElement('canvas');
                out.width = side;
                out.height = side;
                out.getContext('2d').drawImage(img, sx, sy, side, side, 0, 0, side, side);
                out.toBlob(function (cut) { resolve(cut || blob); }, 'image/png');
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(blob); };
            img.src = url;
        });
    }

    async function cutout(blob) {
        hint.textContent = 'Removing the background…';
        try {
            if (!cutoutMod) {
                cutoutMod = await import('https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/+esm');
            }
            var out = await cutoutMod.removeBackground(blob, {
                publicPath: 'https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.5.8/dist/',
                device: 'cpu'
            });
            hint.textContent = 'Placing your face in the ring…';
            return cropFace(out);
        } catch (e) {
            hint.textContent = 'Could not cut the background on this phone — we will still place your face in the ring.';
            return cropFace(blob);
        }
    }

    async function useImageBlob(blob) {
        camBtn.classList.add('busy');
        var cut = await cutout(blob);
        selfieFile = blobToFile(cut, 'selfie.png');
        var url = URL.createObjectURL(cut);
        shot.src = url;
        setStage('is-shot');
        captureBtn.style.display = 'none';
        camBtn.style.display = 'none';
        camBtn.classList.remove('busy');
        retakeBtn.style.display = 'inline-flex';
        hint.textContent = 'Background removed. Your face is ready for the flyer.';
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
        hint.textContent = 'The background is removed automatically and your face is placed in the gold ring.';
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
            showStep(1);
            return;
        }
        goBtn.classList.add('busy');
        goBtn.textContent = 'Sending…';
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
                try { j = JSON.parse(t); } catch (e2) {}
                return { ok: r.ok, j: j };
            });
        }).then(function (res) {
            if (res.j && res.j.redirect) {
                window.location.href = res.j.redirect;
                return;
            }
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Submit';
            var msg = (res.j && res.j.message) ? res.j.message : 'Could not create the flyer.';
            if (res.j && res.j.errors) {
                var first = Object.keys(res.j.errors)[0];
                if (first && res.j.errors[first][0]) msg = res.j.errors[first][0];
            }
            err.textContent = msg;
        }).catch(function () {
            goBtn.classList.remove('busy');
            goBtn.textContent = 'Submit';
            err.textContent = 'Network error. Please try again.';
        });
    });
})();
</script>
</body>
</html>
