@extends('beyond.layout')

@section('title', $job->title.' — Apply')
@section('meta_description', 'Apply for '.$job->title.' at Beyond Enterprise.')

@section('content')
@php $isInternship = $job->isInternship(); @endphp
<div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-14 px-4">
        <div class="max-w-5xl mx-auto">
            <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 text-blue-100 hover:text-white text-sm mb-4">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> All Positions
            </a>
            <div class="flex flex-wrap gap-2 items-center mb-3">
                <span class="bg-white/15 text-white text-xs px-2.5 py-1 rounded-full">{{ $job->department ?: 'General' }}</span>
                <span class="bg-white/15 text-white text-xs px-2.5 py-1 rounded-full">{{ $isInternship ? 'Internship' : ($job->employment_type ?: 'Full-Time') }}</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">{{ $job->title }}</h1>
            <div class="flex flex-wrap gap-4 mt-4 text-blue-100 text-sm">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4"></i> {{ $job->location ?: 'Remote' }}</span>
                @if (! $isInternship && $job->salary)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="dollar-sign" class="w-4 h-4"></i> {{ $job->salary }}</span>
                @endif
                @if ($isInternship)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="graduation-cap" class="w-4 h-4"></i> Unpaid · 7:30–16:00 · 40 hrs/week</span>
                @endif
                @if ($job->deadline)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4"></i> {{ $job->is_expired ? 'Closed' : 'Closes '.$job->deadline->format('M j, Y') }}</span>
                @endif
            </div>
            @if ($job->enable_countdown && $job->deadline && ! $job->is_expired)
                <div class="mt-6 max-w-md">
                    @include('beyond.partials.event_countdown', [
                        'targetIso' => $job->deadline->copy()->endOfDay()->toIso8601String(),
                        'compact' => true,
                        'countdownLabel' => 'Closes in',
                        'completionMessage' => 'Applications closed',
                        'timezone' => config('app.timezone', 'Africa/Kigali'),
                    ])
                </div>
            @endif
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="grid lg:grid-cols-3 gap-8 items-start">

            <div class="lg:col-span-2 space-y-6">
                @if ($job->description)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-brand-blue mb-3">About the Role</h2>
                        <p class="text-gray-600 whitespace-pre-line leading-relaxed">{{ $job->description }}</p>
                    </div>
                @endif

                @foreach ([
                    ['Responsibilities', $job->responsibilities],
                    ['Requirements', $job->requirements],
                    ['Qualifications', $job->qualifications],
                    ['Minimum Requirements', $job->min_requirements],
                ] as [$heading, $body])
                    @if ($body)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-lg font-bold text-brand-blue mb-3">{{ $heading }}</h2>
                            <ul class="space-y-2">
                                @foreach (preg_split('/\r\n|\r|\n/', $body) as $line)
                                    @if (trim($line) !== '')
                                        <li class="flex items-start gap-3 text-gray-700 text-sm">
                                            <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 text-brand-gold flex-shrink-0"></i>
                                            <span>{{ trim($line) }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="lg:col-span-1 lg:sticky lg:top-24">
                <div class="bg-white rounded-xl shadow-lg border-t-4 border-t-brand-gold overflow-hidden">
                    <div class="bg-brand-blue text-white px-6 py-4">
                        <h2 class="text-lg font-bold">Apply for this {{ $isInternship ? 'internship' : 'role' }}</h2>
                        <p class="text-blue-100 text-sm">{{ $stats['total_applicants'] }} applicant(s) so far</p>
                    </div>

                    @if (! $availability['available'])
                        <div class="p-6 text-center">
                            <i data-lucide="lock" class="w-10 h-10 text-gray-300 mx-auto mb-3"></i>
                            <p class="text-gray-600 font-medium">{{ $availability['reason'] }}</p>
                            <a href="{{ route('apply.index') }}" class="inline-block mt-4 text-brand-blue font-semibold hover:underline">Browse other openings</a>
                        </div>
                    @else
                        @if ($errors->any())
                            <div class="mx-6 mt-6 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('apply.store', $job->id) }}" enctype="multipart/form-data"
                              class="p-6 space-y-4" id="apply-form"
                              x-data="{ availability: '{{ old('availability', 'Immediately') }}' }">
                            @csrf
                            <div>
                                <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                                <input required name="full_name" value="{{ old('full_name') }}" type="text" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-700">Email *</label>
                                <input required name="email" value="{{ old('email') }}" type="email" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-700">Phone *</label>
                                <div class="flex gap-2 mt-1">
                                    <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 focus:border-brand-blue outline-none w-32">
                                        @foreach ($countryCodes as $code => $label)
                                            <option value="{{ $code }}" @if(old('country_code', '+250') === $code) selected @endif>{{ $code }}</option>
                                        @endforeach
                                    </select>
                                    <input required name="phone" value="{{ old('phone') }}" type="tel" placeholder="788 123 456" class="flex-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-700">WhatsApp Number *</label>
                                <p class="text-xs text-gray-500 mt-0.5">Used for application status notifications.</p>
                                <div class="flex gap-2 mt-1">
                                    <select name="whatsapp_country_code" class="rounded-md border border-gray-200 px-2 py-2 focus:border-brand-blue outline-none w-32">
                                        @foreach ($countryCodes as $code => $label)
                                            <option value="{{ $code }}" @if(old('whatsapp_country_code', old('country_code', '+250')) === $code) selected @endif>{{ $code }}</option>
                                        @endforeach
                                    </select>
                                    <input required name="whatsapp_number" value="{{ old('whatsapp_number') }}" type="tel" placeholder="788 123 456" class="flex-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                                </div>
                            </div>
                            @unless($isInternship)
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Expected Salary (optional)</label>
                                    <input name="expected_salary" value="{{ old('expected_salary') }}" type="text" placeholder="e.g. 600,000 RWF" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                                </div>
                            @endunless
                            <div>
                                <label class="text-sm font-semibold text-gray-700">Availability</label>
                                <select name="availability" x-model="availability" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                                    @foreach (['Immediately', '1 week', '2 weeks', '1 month', 'Custom'] as $opt)
                                        <option value="{{ $opt }}" @if(old('availability') === $opt) selected @endif>{{ $opt === 'Custom' ? 'Custom (specify days)' : $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="availability === 'Custom'" x-cloak>
                                <label class="text-sm font-semibold text-gray-700">Available in (days)</label>
                                <input name="availability_days" value="{{ old('availability_days') }}" type="number" min="1" max="365" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-700">Resume / CV (PDF, DOC, DOCX) *</label>
                                <input required name="cv" type="file" accept=".pdf,.doc,.docx"
                                       class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold hover:file:bg-brand-dark">
                            </div>

                            @if ($isInternship)
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 space-y-3">
                                    <p class="text-sm font-bold text-emerald-900">Internship documents</p>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Student ID *</label>
                                        <input required name="student_id" type="file" accept="image/*,.pdf" class="w-full mt-1 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Internship Letter *</label>
                                        <input required name="internship_letter" type="file" accept="image/*,.pdf" class="w-full mt-1 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Selfie / Photo *</label>
                                        <input required name="selfie" type="file" accept="image/*" capture="user" class="w-full mt-1 text-sm">
                                        <p class="text-xs text-gray-500 mt-1">Take a selfie or attach a clear photo of yourself.</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Signature *</label>
                                        <canvas id="apply-signature-pad" class="w-full mt-1 border-2 border-dashed border-brand-gold rounded-md bg-white" style="height:140px;touch-action:none;"></canvas>
                                        <input type="hidden" name="signature_image" id="signature_image">
                                        <button type="button" id="clear-signature" class="mt-2 text-xs text-brand-blue underline">Clear signature</button>
                                    </div>
                                    <label class="flex items-start gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="agreement_accepted" value="1" class="mt-1" required>
                                        <span>I confirm my documents are accurate and I understand this internship is unpaid with required timesheets.</span>
                                    </label>
                                </div>
                            @endif

                            <div>
                                <label class="text-sm font-semibold text-gray-700">Cover Letter (optional)</label>
                                <textarea name="cover_letter" rows="4" placeholder="Tell us why you're a great fit..." class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">{{ old('cover_letter') }}</textarea>
                            </div>
                            <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-5 h-5"></i> Submit Application
                            </button>
                            <p class="text-xs text-gray-500 text-center">You will be notified on WhatsApp that your application is under review.</p>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if ($isInternship && $availability['available'])
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('apply-signature-pad');
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
    document.getElementById('apply-form').addEventListener('submit', function (e) {
        if (pad.isEmpty()) {
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
