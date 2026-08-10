@extends('beyond.layout')

@section('title', 'Accept your internship offer')

@section('content')
@php
    $job = $application->job;
    $steps = [
        1 => 'Accept offer',
        2 => 'Create account',
        3 => 'Working Week',
        4 => 'Sign',
    ];
@endphp
<div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="bg-brand-blue text-white px-6 py-5">
            <h1 class="text-2xl font-extrabold">Accept your offer &amp; set up your account</h1>
            <p class="text-blue-100 text-sm mt-1">{{ optional($job)->title }} · Ref {{ $application->reference_number }}</p>
        </div>

        <div class="px-6 pt-5 flex flex-wrap gap-2">
            @foreach($steps as $n => $label)
                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $step === $n ? 'bg-brand-gold text-brand-blue' : ($step > $n ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500') }}">
                    {{ $n }}. {{ $label }}
                </span>
            @endforeach
        </div>

        @if(session('message'))
            <div class="mx-6 mt-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                <ul class="list-disc pl-5 mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($step === 1)
            <div class="p-6 prose prose-sm max-w-none text-gray-700 space-y-4">
                <p>Dear <strong>{{ $application->full_name }}</strong>,</p>
                <p>
                    You have been selected for the internship
                    <strong>{{ optional($job)->title }}</strong> at Beyond Enterprise.
                </p>
                <h2 class="text-lg font-bold text-brand-blue">Internship terms</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li>This internship is <strong>unpaid</strong>.</li>
                    <li>Expected working hours: <strong>per your Working Week</strong> (set in the next steps).</li>
                    <li>You must complete <strong>daily timesheets</strong> on your configured working days.</li>
                    <li>Failure to complete assigned tasks may result in <strong>termination</strong> of the internship.</li>
                </ul>
                <p>By continuing you confirm that you accept this offer and these terms.</p>
            </div>
            <form method="POST" action="{{ route('apply.agreement.sign', $application->agreement_token) }}" class="px-6 pb-8 space-y-4">
                @csrf
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="agreement_read_confirmed" value="1">
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="agreement_accepted" value="1" class="mt-1" required>
                    <span>I accept the internship offer and terms &amp; conditions.</span>
                </label>
                <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                    Continue to account setup
                </button>
            </form>
        @elseif($step === 2)
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-bold text-brand-blue m-0">Create your account password</h2>
                <p class="text-sm text-gray-600 m-0">Use this email and password to sign in to the Beyond Enterprise portal.</p>
                <form method="POST" action="{{ route('apply.agreement.sign', $application->agreement_token) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="step" value="2">
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Email</label>
                        <input type="email" value="{{ $application->email }}" class="w-full mt-1 border border-slate-200 rounded-md px-3 py-2 bg-slate-50" readonly>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">WhatsApp</label>
                        <input type="text" value="{{ $application->whatsapp_number ?: $application->phone }}" class="w-full mt-1 border border-slate-200 rounded-md px-3 py-2 bg-slate-50" readonly>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Password *</label>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password"
                               class="w-full mt-1 border border-slate-200 rounded-md px-3 py-2" placeholder="At least 8 characters">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Confirm password *</label>
                        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
                               class="w-full mt-1 border border-slate-200 rounded-md px-3 py-2">
                    </div>
                    <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                        Continue to Working Week
                    </button>
                </form>
            </div>
        @elseif($step === 3)
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-bold text-brand-blue m-0">Confirm your Working Week</h2>
                <p class="text-sm text-gray-600 m-0">Daily tasks release only on the days you configure here.</p>
                <form method="POST" action="{{ route('apply.agreement.sign', $application->agreement_token) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="step" value="3">
                    @include('beyond.apply.partials.working_week_fields', [
                        'wwData' => $wwData,
                        'formId' => 'offer-ww',
                        'required' => true,
                    ])
                    <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                        Continue to signature
                    </button>
                </form>
            </div>
        @else
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-bold text-brand-blue m-0">Sign to complete</h2>
                <p class="text-sm text-gray-600 m-0">Draw your signature to finish accepting the offer.</p>
                <form method="POST" action="{{ route('apply.agreement.sign', $application->agreement_token) }}" id="offer-sign-form" class="space-y-4">
                    @csrf
                    <input type="hidden" name="step" value="4">
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Draw your signature *</label>
                        <canvas id="agreement-signature-pad" class="w-full mt-1 border-2 border-dashed border-brand-gold rounded-md bg-white" style="height:160px;touch-action:none;"></canvas>
                        <input type="hidden" name="signature_image" id="signature_image">
                        <button type="button" id="clear-signature" class="mt-2 text-xs text-brand-blue underline">Clear signature</button>
                    </div>
                    <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                        Accept offer &amp; finish setup
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection

@if($step === 4)
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
    document.getElementById('offer-sign-form').addEventListener('submit', function (e) {
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
@endif
