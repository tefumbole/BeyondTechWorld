@extends('beyond.layout')

@php
    $isInternship = $job->isInternship();
    $offerPrograms = $isInternship ? $job->internshipPrograms() : collect();
@endphp

@section('title', 'Apply — '.$job->title)
@section('meta_description', 'Submit your application for '.$job->title.' at Beyond Enterprise.')

@section('content')
@include('beyond.apply.partials.apply_styles')
<div class="min-h-screen apply-shell">
    <div class="relative overflow-hidden bg-brand-blue text-white">
        <div class="absolute inset-0 opacity-30" style="background:linear-gradient(120deg,rgba(198,171,71,.35),transparent 45%),radial-gradient(circle at 80% 20%,rgba(255,255,255,.12),transparent 40%);"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 py-5 md:py-6">
            <a href="{{ route('apply.show', $job->id) }}" class="inline-flex items-center gap-2 text-blue-100 hover:text-white text-sm mb-2 md:mb-3 font-medium min-h-[2.5rem]">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to posting
            </a>
            <p class="text-brand-gold text-xs font-bold uppercase tracking-wider m-0">Application</p>
            <h1 class="text-xl sm:text-2xl md:text-4xl font-extrabold tracking-tight leading-snug max-w-3xl mt-1">{{ $job->title }}</h1>
            <p class="text-blue-100 text-sm mt-2 md:mt-3 m-0 leading-relaxed">
                @if($isInternship)
                    Pick your program, fill your details, upload documents, and submit.
                @else
                    Enter your details and upload your CV to apply.
                @endif
            </p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 -mt-3 sm:-mt-4 relative z-10" id="apply-section">
        @include('beyond.apply.partials.application_form')
    </div>
</div>
@endsection

@if ($isInternship)
@push('scripts')
@include('beyond.apply.partials.camera_capture')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    if (window.lucide) lucide.createIcons();
    document.addEventListener('alpine:initialized', function () {
        if (window.lucide) lucide.createIcons();
    });

    var form = document.getElementById('apply-form');
    var pad = null;
    var canvas = document.getElementById('apply-signature-pad');
    if (canvas && window.SignaturePad) {
        pad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255,255,255)',
            minWidth: 1.2,
            maxWidth: 2.8,
            throttle: 8
        });
        function resize() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var data = pad.toData();
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            pad.clear();
            if (data.length) pad.fromData(data);
        }
        window.addEventListener('resize', resize);
        setTimeout(resize, 200);
        var clearBtn = document.getElementById('clear-signature');
        if (clearBtn) clearBtn.addEventListener('click', function () { pad.clear(); });
    }

    if (form) form.addEventListener('submit', function (e) {
        var data = form._x_dataStack && form._x_dataStack[0];
        if (data && data.section && data.section < 5) {
            e.preventDefault();
            if (typeof data.go === 'function') data.go(data.section + 1);
            return;
        }
        var missing = [];
        [
            ['student_id', 'ID or passport front'],
            ['student_id_back', 'ID or passport back'],
            ['selfie', 'selfie']
        ].forEach(function (pair) {
            var input = document.querySelector('input[name="' + pair[0] + '"]');
            if (!input || input.disabled) return;
            var card = input.closest('[data-apply-doc]');
            var preview = card ? card.querySelector('[data-doc-preview]') : null;
            var fromPhone = preview && preview.getAttribute('src') && !preview.classList.contains('hidden');
            if ((!input.files || !input.files.length) && !fromPhone) missing.push(pair[1]);
        });
        if (missing.length) {
            e.preventDefault();
            alert('Please scan, attach, or send from your phone: ' + missing.join(', '));
            return;
        }
        var agreed = form.querySelector('[name="agreement_accepted"]');
        if (agreed && !agreed.checked) {
            e.preventDefault();
            alert('Confirm the paid internship terms.');
            return;
        }
        if (!pad || pad.isEmpty()) {
            e.preventDefault();
            alert('Please sign in the signature box.');
            return;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
    });

    var handoffUrl = @json(route('apply.handoff', $job->id));
    var statusUrl = @json(route('apply.capture.status'));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var polling = null;
    function applyPhonePhoto(slot, imageUrl) {
        var card = document.querySelector('[data-handoff-slot="' + slot + '"]');
        if (!card || !imageUrl) return;
        var img = card.querySelector('[data-doc-preview]');
        var status = card.querySelector('[data-doc-status]');
        var input = card.querySelector('[data-doc-target]');
        if (img && img.getAttribute('src') !== imageUrl) {
            img.src = imageUrl;
            img.classList.remove('hidden');
            if (status) status.textContent = 'Received from the phone.';
            if (input && (!input.files || !input.files.length)) {
                fetch(imageUrl).then(function (res) { return res.blob(); }).then(function (blob) {
                    if (!window.DataTransfer) return;
                    var file = new File([blob], slot + '.jpg', { type: blob.type || 'image/jpeg' });
                    var list = new DataTransfer();
                    list.items.add(file);
                    input.files = list.files;
                }).catch(function () {});
            }
        }
    }
    function pollPhone() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } }).then(function (res) { return res.json(); }).then(function (body) {
            if (!body) return;
            if (body.token) {
                var hidden = document.getElementById('apply-handoff-token');
                if (hidden) hidden.value = body.token;
            }
            applyPhonePhoto('id_front', body.id_front);
            applyPhonePhoto('id_back', body.id_back);
            applyPhonePhoto('selfie', body.selfie);
        }).catch(function () {});
    }
    document.querySelectorAll('[data-handoff]').forEach(function (button) {
        button.addEventListener('click', function () {
            var slot = button.getAttribute('data-handoff');
            var body = new FormData();
            body.append('focus', slot);
            body.append('_token', csrf ? csrf.getAttribute('content') : '');
            fetch(handoffUrl, {
                method: 'POST',
                body: body,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) { return res.json(); }).then(function (data) {
                var box = document.querySelector('[data-qr-slot="' + slot + '"]');
                if (!box) return;
                box.innerHTML = data.qr
                    ? '<p class="text-xs text-gray-600 mb-2">Scan this with the phone, then photograph the ID and take the selfie.</p>' + data.qr
                    : '<p class="text-xs text-red-700">The QR code could not be created. Try again.</p>';
                box.classList.remove('hidden');
                box.classList.add('is-open');
                if (!polling) polling = setInterval(pollPhone, 2000);
                pollPhone();
            }).catch(function () {});
        });
    });
})();
</script>
@endpush
@endif
