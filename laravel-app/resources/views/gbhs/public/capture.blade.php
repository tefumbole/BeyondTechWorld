@extends('gbhs.public.layout')
@section('content')
<style>
    .join { max-width: 760px; margin: 0 auto; }
    .join-card { background: #fff; border: 1px solid #e4d3a2; border-radius: 18px; padding: 18px 14px 20px; }
    .join button, .join .btn { display: block; width: 100%; margin-right: 0; text-align: center; }
    .live { position: relative; width: min(280px, 78vw); height: 340px; margin: 8px auto 4px; }
    .live video { width: 100%; height: 100%; object-fit: cover; border-radius: 46%; transform: scaleX(-1); background: #10281a; }
    .live-ring { position: absolute; inset: 8px; border: 4px solid #e8c15a; border-radius: 46%; pointer-events: none; box-shadow: inset 0 0 0 8px rgba(12, 58, 34, .18); }
    .live-step { text-align: center; font-weight: 700; color: #0c3a22; min-height: 2.6em; margin: 8px 0; }
    .live-meter { height: 8px; border-radius: 99px; background: #e4d3a2; overflow: hidden; margin: 0 auto 8px; width: min(280px, 78vw); }
    .live-meter span { display: block; height: 100%; width: 0; background: #0c3a22; }
    .live-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 8px; }
    .live-dots i { width: 10px; height: 10px; border-radius: 50%; background: #e4d3a2; display: block; }
    .live-dots i.on { background: #0c3a22; }
    .doc-video { width: min(100%, 520px); max-height: 62vh; border-radius: 16px; background: #10281a; display: block; margin: 8px auto; }
</style>
<div class="join">
    <form class="join-card" method="post" action="{{ route('gbhs.capture.store', ['token' => $token]) }}" enctype="multipart/form-data">
        @csrf
        <h1>{{ $label }}</h1>
        @if($done)
            <p>This photo was received. Return to the computer.</p>
        @elseif($kind === 'portrait')
            <p class="muted">Face the camera, turn left, then turn right. The straight photo is sent back to the computer.</p>
            <div id="liveBox" hidden>
                <div class="live"><video id="liveVideo" autoplay playsinline muted></video><div class="live-ring"></div></div>
                <p class="live-step" id="liveStep">Look straight at the camera</p>
                <div class="live-meter"><span id="liveMeter"></span></div>
                <div class="live-dots" id="liveDots"><i class="on"></i><i></i><i></i><i></i></div>
            </div>
            <input type="file" name="photo" id="photoInput" accept="image/*" hidden>
            <button type="button" class="btn" id="photoStart">Start face check</button>
            <p id="phoneNote" class="muted" hidden>Photo received. You can return to the computer.</p>
        @else
            <p class="muted">Photograph the document in good light. The image is used to read the name, number, and date of issue, then deleted.</p>
            <video id="docVideo" class="doc-video" autoplay playsinline muted hidden></video>
            <input type="file" name="document" id="docInput" accept="image/*" capture="environment" hidden>
            <button type="button" class="btn gold" id="docShoot">Open the camera</button>
            <p id="phoneNote" class="muted" hidden>Photo received. You can return to the computer.</p>
        @endif
    </form>
</div>
@include('gbhs.public.partials.face_check')
<script>
    (function () {
        var note = document.getElementById('phoneNote');
        @if(!$done && $kind === 'portrait')
        var input = document.getElementById('photoInput');
        var liveBox = document.getElementById('liveBox');
        liveBox.hidden = true;
        var check = gbhsFaceCheck({
            box: liveBox,
            video: document.getElementById('liveVideo'),
            stepEl: document.getElementById('liveStep'),
            meter: document.getElementById('liveMeter'),
            dots: document.querySelectorAll('#liveDots i'),
            onCapture: function (blob) {
                var file = new File([blob], 'portrait.jpg', { type: 'image/jpeg' });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                if (note) note.hidden = false;
                input.form.submit();
            }
        });
        document.getElementById('photoStart').addEventListener('click', function () {
            check.start().catch(function () {
                liveBox.hidden = false;
                document.getElementById('liveStep').textContent = 'This phone could not open the camera. Allow camera access and try again.';
            });
        });
        @elseif(!$done)
        var video = document.getElementById('docVideo');
        var input = document.getElementById('docInput');
        var shoot = document.getElementById('docShoot');
        var stream = null;
        var fileMode = !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia;
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) input.form.submit();
        });
        shoot.addEventListener('click', function () {
            if (fileMode) {
                input.click();
                return;
            }
            if (!stream) {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (media) {
                    stream = media;
                    video.hidden = false;
                    video.srcObject = media;
                    shoot.textContent = 'Use this photo';
                }).catch(function () {
                    fileMode = true;
                    shoot.textContent = 'Open the camera';
                    input.click();
                });
                return;
            }
            var shot = document.createElement('canvas');
            shot.width = video.videoWidth || 1280;
            shot.height = video.videoHeight || 720;
            shot.getContext('2d').drawImage(video, 0, 0);
            shot.toBlob(function (blob) {
                if (!blob) return;
                stream.getTracks().forEach(function (track) { track.stop(); });
                var file = new File([blob], 'document.jpg', { type: 'image/jpeg' });
                var transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                if (note) note.hidden = false;
                input.form.submit();
            }, 'image/jpeg', 0.92);
        });
        @endif
    })();
</script>
@endsection
