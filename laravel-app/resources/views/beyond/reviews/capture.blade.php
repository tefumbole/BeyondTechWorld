@extends('beyond.layout')

@section('title', 'Continue your review')

@section('content')
<section class="rv-page">
    <div class="max-w-xl mx-auto px-4">
        <div class="rv-intro">
            <p class="rv-kicker">Beyond Enterprise</p>
            <h1>Continue on this phone</h1>
            <p>The selfie is optional. Sign here, then go back to the first screen.</p>
        </div>
        @if(session('message'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        <div class="rv-card">
            @if($photoUrl && $signatureUrl)
                <p class="rv-hint">Selfie and signature are saved. Return to the first screen.</p>
            @endif
            <form method="POST" action="{{ route('reviews.capture.store', ['token' => $token]) }}" enctype="multipart/form-data" id="capture-form" class="rv-form">
                @csrf
                <div class="rv-selfie">
                    <div class="rv-selfie-ring">
                        <img id="capture-preview" alt="" @if($photoUrl) class="is-on" src="{{ $photoUrl }}" @endif>
                        <span id="capture-placeholder" @if($photoUrl) hidden @endif>Optional</span>
                    </div>
                    <div>
                        <p class="rv-label">Selfie <span class="rv-optional">Optional</span></p>
                        <p class="rv-hint">Add a photo of yourself, or skip this.</p>
                        <div class="rv-selfie-actions">
                            <button type="button" id="capture-open" class="rv-chip">Take a selfie</button>
                            <button type="button" id="capture-file-btn" class="rv-chip rv-chip-quiet">Choose a photo</button>
                        </div>
                        <input id="capture-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="rv-file" data-skip-image-paste="1">
                        <input id="capture-fallback" type="file" accept="image/jpeg,image/png,image/webp" capture="user" class="rv-file" data-skip-image-paste="1">
                    </div>
                </div>
                <div id="capture-modal" class="rv-modal" hidden>
                    <div class="rv-modal-card">
                        <video id="capture-video" autoplay playsinline muted></video>
                        <div class="rv-selfie-actions">
                            <button type="button" id="capture-snap" class="rv-chip">Capture</button>
                            <button type="button" id="capture-close" class="rv-chip rv-chip-quiet">Close</button>
                        </div>
                        <p id="capture-error" class="rv-hint" hidden>The camera could not open. Choose a photo instead.</p>
                    </div>
                </div>
                <canvas id="capture-canvas" hidden></canvas>
                <div class="rv-field">
                    <label>Your signature</label>
                    @if($signatureUrl)
                        <img src="{{ $signatureUrl }}" alt="Saved signature" class="rv-handoff-sign">
                        <p class="rv-hint">A signature is already saved. Draw again only if you want to replace it.</p>
                    @endif
                    <div class="rv-sign"><canvas id="capture-signature" class="touch-none"></canvas></div>
                    <input type="hidden" name="signature" id="capture-signature-input">
                    <button type="button" id="capture-clear" class="rv-clear">Clear signature</button>
                </div>
                <button type="submit" class="rv-send">Save on this phone</button>
                <p id="capture-saved" class="rv-hint" hidden>Saved. It is now on the first screen.</p>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<style>
    .rv-page { background: radial-gradient(1200px 480px at 50% -80px, #dbe7ff 0%, #f4f7fb 42%, #eef2f7 100%); padding: 36px 0 64px; }
    .rv-intro { text-align: center; margin-bottom: 22px; }
    .rv-kicker { margin: 0 0 6px; letter-spacing: .16em; text-transform: uppercase; font-size: 11px; font-weight: 800; color: #8a7030; }
    .rv-intro h1 { margin: 0; font-size: 32px; color: #0b3f90; }
    .rv-intro p { color: #64748b; }
    .rv-card { background: #fff; border-radius: 28px; padding: 28px 22px; box-shadow: 0 24px 60px rgba(11, 63, 144, .08); }
    .rv-form { display: grid; gap: 18px; }
    .rv-label { margin: 0; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; font-weight: 800; color: #0b3f90; }
    .rv-hint { margin: 6px 0 0; color: #64748b; font-size: 13px; }
    .rv-optional { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 11px; }
    .rv-selfie { display: flex; gap: 16px; align-items: center; }
    .rv-selfie-ring { width: 92px; height: 92px; border-radius: 50%; overflow: hidden; flex: 0 0 92px; background: #e8eef8; display: grid; place-items: center; box-shadow: inset 0 0 0 3px #fff, 0 0 0 2px #c6ab47; }
    .rv-selfie-ring img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .rv-selfie-ring img.is-on { display: block; }
    .rv-selfie-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .rv-chip { border: 0; border-radius: 999px; padding: 8px 14px; font-weight: 800; background: #0b3f90; color: #fff; }
    .rv-chip-quiet { background: #fff; color: #0b3f90; box-shadow: inset 0 0 0 1px #d6e0ee; }
    .rv-file { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .rv-modal { position: fixed; inset: 0; z-index: 80; background: rgba(15, 23, 42, .62); display: grid; place-items: center; padding: 16px; }
    .rv-modal[hidden] { display: none; }
    .rv-modal-card { width: min(420px, 100%); background: #0f172a; border-radius: 24px; padding: 14px; }
    .rv-modal-card video { width: 100%; border-radius: 18px; background: #000; aspect-ratio: 3/4; object-fit: cover; transform: scaleX(-1); }
    .rv-sign { border-radius: 18px; background: #f8fafc; padding: 8px; }
    .rv-sign canvas { width: 100%; height: 160px; background: #fff; border-radius: 12px; touch-action: none; }
    .rv-clear { background: none; border: 0; color: #64748b; text-decoration: underline; font-size: 13px; }
    .rv-handoff-sign { display: block; height: 64px; margin: 8px 0; background: #fff; }
    .rv-send { width: 100%; border: 0; border-radius: 16px; padding: 16px; font-weight: 800; background: linear-gradient(180deg, #e4cc74, #c6ab47); color: #072f6b; }
</style>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('capture-signature');
    var pad = new SignaturePad(canvas, { penColor: '#0b3f90', minWidth: 1.2, maxWidth: 2.6 });
    function fit() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var rect = canvas.getBoundingClientRect();
        var data = pad.toData();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
        if (data.length) pad.fromData(data);
    }
    fit();
    window.addEventListener('resize', fit);
    document.getElementById('capture-clear').addEventListener('click', function () { pad.clear(); });

    var photo = document.getElementById('capture-photo');
    var fallback = document.getElementById('capture-fallback');
    var preview = document.getElementById('capture-preview');
    var placeholder = document.getElementById('capture-placeholder');
    var modal = document.getElementById('capture-modal');
    var video = document.getElementById('capture-video');
    var stream = null;
    function showFile(file) {
        if (!file || !preview) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.add('is-on');
        if (placeholder) placeholder.hidden = true;
        if (photo && file !== photo.files[0]) {
            var transfer = new DataTransfer();
            transfer.items.add(file);
            photo.files = transfer.files;
        }
    }
    document.getElementById('capture-file-btn').addEventListener('click', function () { photo.click(); });
    function stopCamera() {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        stream = null;
        video.srcObject = null;
    }
    document.getElementById('capture-open').addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            fallback.click();
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function (media) {
            stream = media;
            video.srcObject = media;
            modal.hidden = false;
        }).catch(function () { fallback.click(); });
    });
    document.getElementById('capture-close').addEventListener('click', function () { modal.hidden = true; stopCamera(); });
    document.getElementById('capture-snap').addEventListener('click', function () {
        var shot = document.getElementById('capture-canvas');
        var width = video.videoWidth || 720;
        var height = video.videoHeight || 960;
        shot.width = width;
        shot.height = height;
        var ctx = shot.getContext('2d');
        ctx.translate(width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, width, height);
        shot.toBlob(function (blob) {
            if (!blob) return;
            showFile(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
            modal.hidden = true;
            stopCamera();
            savePhone();
        }, 'image/jpeg', 0.9);
    });
    var saveTimer;
    var saving = false;
    function savePhone() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            if (saving) {
                savePhone();
                return;
            }
            var form = document.getElementById('capture-form');
            if (!form) return;
            if (!pad.isEmpty()) {
                document.getElementById('capture-signature-input').value = pad.toDataURL('image/png');
            }
            var hasPhoto = photo && photo.files && photo.files[0];
            var hasSign = document.getElementById('capture-signature-input').value !== '';
            if (!hasPhoto && !hasSign) return;
            saving = true;
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) { return response.json(); }).then(function (data) {
                saving = false;
                var note = document.getElementById('capture-saved');
                if (note && data && data.ok) note.hidden = false;
            }).catch(function () { saving = false; });
        }, 400);
    }
    if (pad && pad.addEventListener) {
        pad.addEventListener('endStroke', savePhone);
    }
    photo.addEventListener('change', function () { if (photo.files[0]) { showFile(photo.files[0]); savePhone(); } });
    fallback.addEventListener('change', function () { if (fallback.files[0]) { showFile(fallback.files[0]); savePhone(); } });
    document.getElementById('capture-form').addEventListener('submit', function () {
        if (!pad.isEmpty()) {
            document.getElementById('capture-signature-input').value = pad.toDataURL('image/png');
        }
    });
})();
</script>
@endpush
