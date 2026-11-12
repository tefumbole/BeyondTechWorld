@extends('beyond.layout')

@section('title', 'ID and selfie')

@section('content')
<section class="py-8 sm:py-12 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-7">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">Paid internship</p>
            <h1 class="text-2xl font-extrabold text-brand-blue m-0 mt-1">Continue on this phone</h1>
            <p class="text-slate-600 mt-2">Photograph the ID or passport, then take a selfie. Each photo appears on the first screen.</p>
            @if($expired)
                <p class="mt-4 rounded-xl bg-red-50 text-red-800 px-3 py-2 text-sm">This link has expired. Go back to the application and generate a new QR code.</p>
            @else
                @foreach ([
                    'id_front' => ['ID or passport — front', 'environment'],
                    'id_back' => ['ID or passport — back', 'environment'],
                    'selfie' => ['Selfie', 'user'],
                ] as $slot => $meta)
                    <div class="mt-5 rounded-xl border border-slate-200 p-3" id="slot-{{ $slot }}">
                        <p class="text-sm font-bold text-brand-blue m-0">{{ $meta[0] }}</p>
                        <img data-preview="{{ $slot }}" alt="" class="mt-2 max-h-40 w-full object-cover rounded-lg border border-slate-200 {{ $photos[$slot] ? '' : 'hidden' }}" @if($photos[$slot]) src="{{ $photos[$slot] }}" @endif>
                        <p data-note="{{ $slot }}" class="text-xs text-emerald-700 mt-2 mb-2">{{ $photos[$slot] ? 'Saved on the first screen.' : 'Not saved yet.' }}</p>
                        <div class="flex gap-2">
                            <button type="button" class="flex-1 rounded-full bg-brand-blue text-white font-bold py-2.5" data-scan="{{ $slot }}" data-facing="{{ $meta[1] }}">Scan</button>
                            <label class="flex-1 rounded-full border border-brand-blue text-brand-blue font-bold py-2.5 text-center">
                                Attach
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" data-attach="{{ $slot }}">
                            </label>
                        </div>
                    </div>
                @endforeach
                <p id="capture-error" class="mt-4 text-sm text-red-700 hidden"></p>
            @endif
        </div>
    </div>
</section>
<div id="capture-camera" class="fixed inset-0 z-50 hidden items-end sm:items-center justify-center bg-black/70 p-0 sm:p-4">
    <div class="bg-white rounded-t-2xl sm:rounded-2xl w-full max-w-lg overflow-hidden">
        <video id="capture-video" class="w-full aspect-[3/4] bg-black object-cover" autoplay playsinline muted></video>
        <div class="flex gap-2 p-3">
            <button type="button" id="capture-shot" class="flex-1 rounded-full bg-brand-gold text-brand-blue font-extrabold py-3">Capture</button>
            <button type="button" id="capture-close" class="px-4 rounded-full border border-slate-300 font-bold">Close</button>
        </div>
    </div>
</div>
@endsection

@if(empty($expired))
@push('scripts')
<script>
(function () {
    var token = @json($token);
    var url = @json(route('apply.capture.store', ['token' => $token]));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var error = document.getElementById('capture-error');
    var modal = document.getElementById('capture-camera');
    var video = document.getElementById('capture-video');
    var stream = null;
    var slot = 'id_front';
    var facing = 'environment';

    function note(message, bad) {
        if (!error) return;
        error.textContent = message || '';
        error.classList.toggle('hidden', !message);
        error.classList.toggle('text-red-700', !!bad);
        error.classList.toggle('text-emerald-700', !bad);
    }

    function show(slotName, imageUrl) {
        var img = document.querySelector('[data-preview="' + slotName + '"]');
        var line = document.querySelector('[data-note="' + slotName + '"]');
        if (img) {
            img.src = imageUrl;
            img.classList.remove('hidden');
        }
        if (line) line.textContent = 'Saved on the first screen.';
    }

    function send(file, slotName) {
        var body = new FormData();
        body.append('slot', slotName);
        body.append('photo', file, slotName + '.jpg');
        body.append('_token', csrf ? csrf.getAttribute('content') : '');
        note('Saving…', false);
        fetch(url, { method: 'POST', body: body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.url) {
                    note((result.data && result.data.message) || 'The photo was not saved.', true);
                    return;
                }
                show(slotName, result.data.url);
                note('Saved. It is now on the first screen.', false);
            })
            .catch(function () { note('The photo was not saved. Try again.', true); });
    }

    function stop() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    document.querySelectorAll('[data-scan]').forEach(function (button) {
        button.addEventListener('click', function () {
            slot = button.getAttribute('data-scan');
            facing = button.getAttribute('data-facing') || 'environment';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                note('This phone browser has no camera here. Use Attach.', true);
                return;
            }
            stop();
            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing } }, audio: false }).then(function (next) {
                stream = next;
                video.srcObject = stream;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }).catch(function () {
                note('The camera did not open. Use Attach.', true);
            });
        });
    });
    document.querySelectorAll('[data-attach]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) send(input.files[0], input.getAttribute('data-attach'));
        });
    });
    document.getElementById('capture-close').addEventListener('click', function () {
        stop();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
    document.getElementById('capture-shot').addEventListener('click', function () {
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 800;
        canvas.height = video.videoHeight || 1000;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (blob) send(blob, slot);
            stop();
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 'image/jpeg', 0.86);
    });
    var focus = (location.search.match(/focus=([a-z_]+)/) || [])[1];
    if (focus) {
        var target = document.getElementById('slot-' + focus);
        if (target) target.scrollIntoView({ block: 'center' });
    }
})();
</script>
@endpush
@endif
