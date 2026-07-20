@extends('beyond.layout')

@section('title', $job->title.' — Apply')
@section('meta_description', 'Apply for '.$job->title.' at Beyond Enterprise.')

@section('content')
@php
    $isInternship = $job->isInternship();
    $openForm = $errors->any() || request()->boolean('apply');
    $sections = array_values(array_filter([
        ['About the Role', $job->description, 'prose'],
        ['Responsibilities', $job->responsibilities, 'list'],
        ['Requirements', $job->requirements, 'list'],
        ['Qualifications', $job->qualifications, 'list'],
        ['Minimum Requirements', $job->min_requirements, 'list'],
    ], function ($s) { return ! empty(trim((string) $s[1])); }));
@endphp
<div class="min-h-screen bg-gray-50 pb-24"
     x-data="{ showForm: {{ $openForm ? 'true' : 'false' }} }"
     x-cloak>
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-14 px-4">
        <div class="max-w-3xl mx-auto">
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

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 space-y-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 mb-0">
                Please read the full {{ $isInternship ? 'internship' : 'job' }} details below before applying.
                <span class="text-gray-400">· {{ $stats['total_applicants'] }} applicant(s) so far</span>
            </p>
            @if ($availability['available'])
                <button type="button" @click="showForm = true; $nextTick(() => document.getElementById('apply-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="inline-flex items-center gap-2 bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold px-5 py-2.5 rounded-md text-sm">
                    Apply Now <i data-lucide="arrow-down" class="w-4 h-4"></i>
                </button>
            @endif
        </div>

        @forelse ($sections as $i => $section)
            @php [$heading, $body, $type] = $section; @endphp
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 bg-slate-50/80">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white text-sm font-extrabold">{{ $i + 1 }}</span>
                    <h2 class="text-lg md:text-xl font-extrabold text-brand-blue m-0">{{ $heading }}</h2>
                </div>
                <div class="px-5 py-5">
                    @if ($type === 'list')
                        <ul class="space-y-3 m-0 p-0 list-none">
                            @foreach (preg_split('/\r\n|\r|\n/', $body) as $line)
                                @if (trim($line) !== '')
                                    <li class="flex items-start gap-3 text-gray-700 text-[15px] leading-relaxed">
                                        <i data-lucide="check-circle-2" class="w-5 h-5 mt-0.5 text-brand-gold flex-shrink-0"></i>
                                        <span>{{ trim($line) }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-700 whitespace-pre-line leading-relaxed text-[15px] m-0">{{ $body }}</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="bg-white rounded-xl border border-gray-100 p-6 text-gray-600">
                Details for this posting will be shared during the application process.
            </div>
        @endforelse

        @if ($isInternship)
            <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 bg-slate-50/80">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-blue text-white text-sm font-extrabold">{{ count($sections) + 1 }}</span>
                    <h2 class="text-lg md:text-xl font-extrabold text-brand-blue m-0">What you will need to apply</h2>
                </div>
                <div class="px-5 py-5 text-[15px] text-gray-700 leading-relaxed space-y-2">
                    <p class="m-0">After you click <strong>Apply Now</strong>, you will provide:</p>
                    <ul class="space-y-2 list-none m-0 p-0">
                        <li class="flex gap-3"><i data-lucide="credit-card" class="w-5 h-5 text-brand-gold shrink-0 mt-0.5"></i><span><strong>National ID card — front and back</strong> (photo/names side and reverse side)</span></li>
                        <li class="flex gap-3"><i data-lucide="file-text" class="w-5 h-5 text-brand-gold shrink-0 mt-0.5"></i><span><strong>Internship letter</strong> from your school / institution</span></li>
                        <li class="flex gap-3"><i data-lucide="camera" class="w-5 h-5 text-brand-gold shrink-0 mt-0.5"></i><span><strong>Selfie / photo</strong> of yourself</span></li>
                        <li class="flex gap-3"><i data-lucide="edit-3" class="w-5 h-5 text-brand-gold shrink-0 mt-0.5"></i><span><strong>Digital signature</strong> confirming your details</span></li>
                    </ul>
                    <p class="text-sm text-gray-500 m-0 pt-2">This internship is unpaid. Selected candidates must complete daily timesheets (minimum 40 hours/week).</p>
                </div>
            </article>
        @endif

        @if (! $availability['available'])
            <div class="bg-white rounded-xl border border-gray-100 p-8 text-center shadow-sm">
                <i data-lucide="lock" class="w-10 h-10 text-gray-300 mx-auto mb-3"></i>
                <p class="text-gray-700 font-medium">{{ $availability['reason'] }}</p>
                <a href="{{ route('apply.index') }}" class="inline-block mt-4 text-brand-blue font-semibold hover:underline">Browse other openings</a>
            </div>
        @else
            <div x-show="!showForm" class="bg-white rounded-xl border border-brand-gold/40 shadow-sm p-6 md:p-8 text-center">
                <h2 class="text-xl font-extrabold text-brand-blue mb-2">Ready to apply?</h2>
                <p class="text-gray-600 mb-5 max-w-lg mx-auto">Once you have read the details above, continue to enter your personal information and upload the required documents.</p>
                <button type="button" @click="showForm = true; $nextTick(() => document.getElementById('apply-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="inline-flex items-center gap-2 bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold px-8 py-3.5 rounded-md text-base shadow-md">
                    Apply Now <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="apply-section" x-show="showForm" x-cloak class="scroll-mt-24">
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-brand-blue text-white px-5 md:px-6 py-5">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-gold text-brand-blue text-sm font-extrabold">{{ count($sections) + ($isInternship ? 2 : 1) }}</span>
                            <div>
                                <h2 class="text-xl font-extrabold m-0">Personal details &amp; documents</h2>
                                <p class="text-blue-100 text-sm m-0 mt-0.5">Apply for this {{ $isInternship ? 'internship' : 'role' }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="mx-5 md:mx-6 mt-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                            <ul class="list-disc pl-5 space-y-1 mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('apply.store', $job->id) }}" enctype="multipart/form-data"
                          class="p-5 md:p-6 space-y-5" id="apply-form"
                          x-data="{ availability: '{{ old('availability', 'Immediately') }}' }">
                        @csrf

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">Contact information</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                                    <input required name="full_name" value="{{ old('full_name') }}" type="text" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Email *</label>
                                    <input required name="email" value="{{ old('email') }}" type="email" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">WhatsApp Number *</label>
                                    <p class="text-xs text-gray-500 mt-0.5">Your only contact number — used for all application status notifications.</p>
                                    <div class="flex gap-2 mt-1">
                                        <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2.5 focus:border-brand-blue outline-none w-32">
                                            @foreach ($countryCodes as $code => $label)
                                                <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                                            @endforeach
                                        </select>
                                        <input required name="whatsapp_number" value="{{ old('whatsapp_number') }}" type="tel" placeholder="675 321 739" class="flex-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                    </div>
                                </div>
                                @unless($isInternship)
                                    <div>
                                        <label class="text-sm font-semibold text-gray-700">Expected Salary (optional)</label>
                                        <input name="expected_salary" value="{{ old('expected_salary') }}" type="text" placeholder="e.g. 600,000 RWF" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                    </div>
                                @endunless
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Availability</label>
                                    <select name="availability" x-model="availability" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                        @foreach (['Immediately', '1 week', '2 weeks', '1 month', 'Custom'] as $opt)
                                            <option value="{{ $opt }}" @if(old('availability') === $opt) selected @endif>{{ $opt === 'Custom' ? 'Custom (specify days)' : $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="availability === 'Custom'" x-cloak>
                                    <label class="text-sm font-semibold text-gray-700">Available in (days)</label>
                                    <input name="availability_days" value="{{ old('availability_days') }}" type="number" min="1" max="365" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">Documents</h3>
                            @unless($isInternship)
                                <div>
                                    <label class="text-sm font-semibold text-gray-700">Resume / CV (PDF, DOC, DOCX) *</label>
                                    <input required name="cv" type="file" accept=".pdf,.doc,.docx"
                                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold hover:file:bg-brand-dark">
                                </div>
                            @else
                                <div class="mb-4">
                                    <label class="text-sm font-semibold text-gray-700">Resume / CV (optional)</label>
                                    <input name="cv" type="file" accept=".pdf,.doc,.docx"
                                           class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold hover:file:bg-brand-dark">
                                    <p class="text-xs text-gray-500 mt-1">Not required for internships.</p>
                                </div>

                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 space-y-4">
                                    <div>
                                        <p class="text-sm font-bold text-emerald-900">Internship documents</p>
                                        <p class="text-xs text-emerald-800 mt-1">Use <strong>Snap with camera</strong> or <strong>Attach file</strong> for each item.</p>
                                    </div>

                                    <div class="rounded-md border border-emerald-300 bg-white/80 p-3 space-y-3">
                                        <div>
                                            <p class="text-sm font-bold text-gray-800">National ID card *</p>
                                            <p class="text-xs text-gray-600 mt-1">Both sides are required — like a Cameroon National Identity Card:</p>
                                            <ul class="text-xs text-gray-600 mt-1 list-disc pl-4 space-y-0.5 mb-0">
                                                <li><strong>Front</strong> — photo, names, date of birth, chip side</li>
                                                <li><strong>Back</strong> — parents, address, issue/expiry dates, unique identifier</li>
                                            </ul>
                                        </div>
                                        @foreach ([
                                            ['student_id', 'ID card — Front', 'environment', 'Snap ID Front'],
                                            ['student_id_back', 'ID card — Back', 'environment', 'Snap ID Back'],
                                        ] as [$field, $label, $facing, $snapTitle])
                                            <div data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                                                <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                                                <input type="file" name="{{ $field }}" data-doc-target accept="image/*,.pdf" class="sr-only" tabindex="-1">
                                                <input type="file" data-doc-attach accept="image/*,.pdf" class="hidden" id="attach-{{ $field }}">
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <label for="attach-{{ $field }}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md border border-brand-blue text-brand-blue text-xs font-bold cursor-pointer bg-white hover:bg-blue-50">
                                                        <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach file
                                                    </label>
                                                    <button type="button" data-doc-snap class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-brand-blue text-white text-xs font-bold hover:bg-brand-dark">
                                                        <i data-lucide="camera" class="w-3.5 h-3.5"></i> Snap with camera
                                                    </button>
                                                </div>
                                                <p class="text-xs text-emerald-700 mt-1.5 min-h-[1rem]" data-doc-status>No file yet</p>
                                                <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-28 rounded-md border border-emerald-200 object-cover">
                                            </div>
                                        @endforeach
                                    </div>

                                    @foreach ([
                                        ['internship_letter', 'Internship Letter', 'environment', 'Snap Internship Letter'],
                                        ['selfie', 'Selfie / Photo', 'user', 'Snap Selfie'],
                                    ] as [$field, $label, $facing, $snapTitle])
                                        <div data-apply-doc data-facing="{{ $facing }}" data-title="{{ $snapTitle }}">
                                            <label class="text-sm font-semibold text-gray-700">{{ $label }} *</label>
                                            <input type="file" name="{{ $field }}" data-doc-target accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="sr-only" tabindex="-1">
                                            <input type="file" data-doc-attach accept="image/*{{ $field !== 'selfie' ? ',.pdf' : '' }}" class="hidden" id="attach-{{ $field }}">
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <label for="attach-{{ $field }}" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md border border-brand-blue text-brand-blue text-xs font-bold cursor-pointer bg-white hover:bg-blue-50">
                                                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Attach file
                                                </label>
                                                <button type="button" data-doc-snap class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-brand-blue text-white text-xs font-bold hover:bg-brand-dark">
                                                    <i data-lucide="camera" class="w-3.5 h-3.5"></i> Snap with camera
                                                </button>
                                            </div>
                                            <p class="text-xs text-emerald-700 mt-1.5 min-h-[1rem]" data-doc-status>No file yet</p>
                                            <img data-doc-preview alt="{{ $label }} preview" class="hidden mt-2 max-h-28 rounded-md border border-emerald-200 object-cover">
                                            @if ($field === 'selfie')
                                                <p class="text-xs text-gray-500 mt-1">Front camera opens for selfie; you can Flip to use the other camera.</p>
                                            @endif
                                        </div>
                                    @endforeach

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
                            @endunless
                        </div>

                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-wide text-brand-blue mb-3 pb-2 border-b border-gray-100">Cover letter (optional)</h3>
                            <textarea name="cover_letter" rows="4" placeholder="Tell us why you're a great fit..." class="w-full rounded-md border border-gray-200 px-3 py-2.5 focus:border-brand-blue outline-none">{{ old('cover_letter') }}</textarea>
                        </div>

                        <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3.5 rounded-md flex items-center justify-center gap-2 text-base">
                            <i data-lucide="send" class="w-5 h-5"></i> Submit Application
                        </button>
                        <p class="text-xs text-gray-500 text-center mb-0">You will be notified on WhatsApp that your application is under review.</p>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@if ($isInternship && $availability['available'])
@push('scripts')
@include('beyond.apply.partials.camera_capture')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    if (window.lucide) lucide.createIcons();
    document.addEventListener('alpine:initialized', function () {
        if (window.lucide) lucide.createIcons();
    });
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
    // Resize when form becomes visible
    var observer = new MutationObserver(function () {
        if (canvas.offsetParent !== null) resize();
    });
    var formSection = document.getElementById('apply-section');
    if (formSection) observer.observe(formSection, { attributes: true, attributeFilter: ['style', 'class'] });
    setTimeout(resize, 300);
    document.getElementById('clear-signature').addEventListener('click', function () { pad.clear(); });
    document.getElementById('apply-form').addEventListener('submit', function (e) {
        var missing = [];
        var requiredDocs = [
            ['student_id', 'ID card front'],
            ['student_id_back', 'ID card back'],
            ['internship_letter', 'internship letter'],
            ['selfie', 'selfie']
        ];
        requiredDocs.forEach(function (pair) {
            var input = document.querySelector('input[name="' + pair[0] + '"]');
            if (!input || !input.files || !input.files.length) missing.push(pair[1]);
        });
        if (missing.length) {
            e.preventDefault();
            alert('Please snap or attach: ' + missing.join(', '));
            return;
        }
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
