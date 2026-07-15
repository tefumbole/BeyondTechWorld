@extends('beyond.layout')

@section('title', 'Sign Agreement')

@section('content')
@php
    $job = $application->job;
    $isInternship = $job && $job->isInternship();
@endphp
<div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="bg-brand-blue text-white px-6 py-5">
            <h1 class="text-2xl font-extrabold">{{ $isInternship ? 'Internship Agreement' : 'Employment Agreement' }}</h1>
            <p class="text-blue-100 text-sm mt-1">{{ optional($job)->title }} · Ref {{ $application->reference_number }}</p>
        </div>

        @if(session('message'))
            <div class="mx-6 mt-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-6 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="p-6 prose prose-sm max-w-none text-gray-700 space-y-4">
            <p>Dear <strong>{{ $application->full_name }}</strong>,</p>
            <p>
                You have been selected for the {{ $isInternship ? 'internship' : 'position' }}
                <strong>{{ optional($job)->title }}</strong> at Beyond Enterprise.
            </p>

            @if ($isInternship)
                <h2 class="text-lg font-bold text-brand-blue">Internship terms</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li>This internship is <strong>unpaid</strong>.</li>
                    <li>Expected working hours: <strong>7:30 AM to 4:00 PM</strong>.</li>
                    <li>You must complete <strong>daily timesheets</strong> and work at least <strong>40 hours per week</strong>.</li>
                    <li>Failure to complete assigned tasks may result in <strong>termination or premature termination</strong> of the internship.</li>
                </ul>
            @else
                <h2 class="text-lg font-bold text-brand-blue">Employment terms</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Expected working hours: <strong>7:30 AM to 4:00 PM</strong>.</li>
                    <li>You must complete <strong>daily timesheets</strong> and work at least <strong>40 hours per week</strong>.</li>
                    <li>Failure to complete assigned tasks may result in <strong>termination or premature termination</strong> of employment.</li>
                    @if(optional($job)->salary)
                        <li>Agreed compensation reference: <strong>{{ $job->salary }}</strong> (final offer subject to HR confirmation).</li>
                    @endif
                </ul>
            @endif

            <p>By signing below you confirm that you have read and agree to these terms.</p>
        </div>

        <form method="POST" action="{{ route('apply.agreement.sign', $application->agreement_token) }}" id="agreement-form" class="px-6 pb-8 space-y-4">
            @csrf
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="agreement_accepted" value="1" class="mt-1" required>
                <span>I accept the {{ $isInternship ? 'internship' : 'employment' }} agreement terms.</span>
            </label>
            <input type="hidden" name="agreement_read_confirmed" value="1">
            <div>
                <label class="text-sm font-semibold text-gray-700">Draw your signature *</label>
                <canvas id="agreement-signature-pad" class="w-full mt-1 border-2 border-dashed border-brand-gold rounded-md bg-white" style="height:160px;touch-action:none;"></canvas>
                <input type="hidden" name="signature_image" id="signature_image">
                <button type="button" id="clear-signature" class="mt-2 text-xs text-brand-blue underline">Clear signature</button>
            </div>
            <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                Sign Agreement
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('agreement-signature-pad');
    if (!canvas || !window.SignaturePad) return;
    var pad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)' });
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
    resize();
    document.getElementById('clear-signature').addEventListener('click', function () { pad.clear(); });
    document.getElementById('agreement-form').addEventListener('submit', function (e) {
        if (pad.isEmpty()) {
            e.preventDefault();
            alert('Please sign before submitting.');
            return;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
    });
})();
</script>
@endpush
