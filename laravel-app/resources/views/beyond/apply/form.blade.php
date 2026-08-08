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
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 py-7 md:py-12">
            <a href="{{ route('apply.show', $job->id) }}" class="inline-flex items-center gap-2 text-blue-100 hover:text-white text-sm mb-3 md:mb-5 font-medium min-h-[2.5rem]">
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
    var bar = document.getElementById('apply-progress-bar');
    var label = document.getElementById('apply-progress-label');
    function refreshProgress() {
        if (!form || !bar) return;
        var required = form.querySelectorAll('input[required], select[required], textarea[required]');
        var done = 0, total = 0;
        required.forEach(function (el) {
            if (el.disabled || el.type === 'hidden') return;
            if (el.name === 'agreement_accepted') return;
            total++;
            if (el.type === 'file') {
                if (el.files && el.files.length) done++;
            } else if (el.type === 'radio') {
                if (form.querySelector('input[name="' + el.name + '"]:checked')) done++;
            } else if (String(el.value || '').trim() !== '') {
                done++;
            }
        });
        // Count program selection once
        var prog = form.querySelector('input[name="internship_program_id"]');
        if (prog) {
            // radios already counted per input — normalize
            var radios = form.querySelectorAll('input[name="internship_program_id"]');
            total -= Math.max(0, radios.length - 1);
            if (form.querySelector('input[name="internship_program_id"]:checked')) {
                // ensure one credit
            }
        }
        var pct = total ? Math.round((done / total) * 100) : 0;
        bar.style.width = Math.min(100, pct) + '%';
        if (label) label.textContent = pct + '% complete';
    }
    if (form) {
        form.addEventListener('input', refreshProgress);
        form.addEventListener('change', refreshProgress);
        setTimeout(refreshProgress, 300);
    }

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
        var missing = [];
        var requiredDocs = [
            ['student_id', 'ID card front'],
            ['student_id_back', 'ID card back'],
            ['selfie', 'selfie']
        ];
        var eduStatus = form.__x && form.__x.$data
            ? form.__x.$data.educationStatus
            : (document.querySelector('[name="education_status"]:checked') || {}).value;
        var academic = form.__x && form.__x.$data
            ? form.__x.$data.academicRequired
            : (document.querySelector('[name="is_academic_required"]:checked') || {}).value;
        if (eduStatus === 'currently_studying' && String(academic) === '1') {
            requiredDocs.push(['internship_letter', 'internship letter']);
        }
        requiredDocs.forEach(function (pair) {
            var input = document.querySelector('input[name="' + pair[0] + '"]');
            if (!input || !input.files || !input.files.length) missing.push(pair[1]);
        });
        if (missing.length) {
            e.preventDefault();
            alert('Please snap or attach: ' + missing.join(', '));
            return;
        }
        if (!pad || pad.isEmpty()) {
            e.preventDefault();
            alert('Please sign in the signature box.');
            return;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
    });
})();
</script>
@endpush
@endif
