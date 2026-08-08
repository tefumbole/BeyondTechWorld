@extends('beyond.layout')

@section('title', $job->title.' — Apply')
@section('meta_description', 'Apply for '.$job->title.' at Beyond Enterprise.')

@section('content')
@php
    $isInternship = $job->isInternship();
    $offerPrograms = $isInternship ? $job->internshipPrograms() : collect();
    $sections = array_values(array_filter([
        ['About the role', $job->description, 'prose'],
        ['Responsibilities', $job->responsibilities, 'list'],
        ['Requirements', $job->requirements, 'list'],
        ['Qualifications', $job->qualifications, 'list'],
        ['Minimum requirements', $job->min_requirements, 'list'],
    ], function ($s) { return ! empty(trim((string) $s[1])); }));
@endphp
@include('beyond.apply.partials.apply_styles')
<div class="min-h-screen apply-shell pb-28">
    <div class="relative overflow-hidden bg-brand-blue text-white">
        <div class="absolute inset-0 opacity-30" style="background:linear-gradient(120deg,rgba(198,171,71,.35),transparent 45%),radial-gradient(circle at 80% 20%,rgba(255,255,255,.12),transparent 40%);"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 py-12 md:py-16">
            <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 text-blue-100 hover:text-white text-sm mb-5 font-medium">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> All openings
            </a>
            <div class="flex flex-wrap gap-2 items-center mb-4">
                <span class="bg-white/12 backdrop-blur text-white text-xs font-semibold px-3 py-1 rounded-full border border-white/15">{{ $job->department ?: 'General' }}</span>
                <span class="bg-brand-gold/95 text-brand-blue text-xs font-bold px-3 py-1 rounded-full">{{ $isInternship ? 'Internship' : ($job->employment_type ?: 'Full-Time') }}</span>
            </div>
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight leading-tight max-w-3xl">{{ $job->title }}</h1>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-5 text-blue-100 text-sm">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4 text-brand-gold"></i> {{ $job->location ?: 'Remote' }}</span>
                @if (! $isInternship && $job->salary)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="dollar-sign" class="w-4 h-4 text-brand-gold"></i> {{ $job->salary }}</span>
                @endif
                @if ($isInternship)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="graduation-cap" class="w-4 h-4 text-brand-gold"></i> Unpaid · 7:30–16:00 · 40 hrs/week</span>
                @endif
                @if ($job->deadline)
                    <span class="inline-flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4 text-brand-gold"></i> {{ $job->is_expired ? 'Closed' : 'Closes '.$job->deadline->format('M j, Y') }}</span>
                @endif
                <span class="inline-flex items-center gap-1.5"><i data-lucide="users" class="w-4 h-4 text-brand-gold"></i> {{ $stats['total_applicants'] }} applicant(s)</span>
            </div>
            @if ($job->enable_countdown && $job->deadline && ! $job->is_expired)
                <div class="mt-7 max-w-md">
                    @include('beyond.partials.event_countdown', [
                        'targetIso' => $job->deadline->copy()->endOfDay()->toIso8601String(),
                        'compact' => true,
                        'countdownLabel' => 'Closes in',
                        'completionMessage' => 'Applications closed',
                        'timezone' => config('app.timezone', 'Africa/Kigali'),
                    ])
                </div>
            @endif
            @if ($availability['available'])
                <div class="mt-8">
                    <a href="{{ route('apply.form', $job->id) }}"
                       class="inline-flex items-center gap-2 bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold px-7 py-3.5 rounded-xl text-base shadow-lg shadow-black/10">
                        Apply now <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10 space-y-5">
        @if ($isInternship && $offerPrograms->isNotEmpty())
            <div class="apply-panel p-5 md:p-6">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">Choose your track</p>
                        <h2 class="text-xl font-extrabold text-brand-blue m-0 mt-1">Available internship programs</h2>
                        <p class="text-sm text-gray-500 m-0 mt-1">You will confirm your program when you apply.</p>
                    </div>
                    @if ($availability['available'])
                        <a href="{{ route('apply.form', $job->id) }}" class="text-sm font-bold text-brand-blue hover:underline">Start application →</a>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($offerPrograms as $prog)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <p class="font-bold text-brand-blue m-0 text-sm">{{ $prog->displayName() }}</p>
                            @if(!empty($prog->code))
                                <p class="text-xs text-gray-500 m-0 mt-0.5">{{ $prog->code }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @forelse ($sections as $i => $section)
            @php [$heading, $body, $type] = $section; @endphp
            <article class="apply-panel overflow-hidden">
                <div class="px-5 md:px-6 pt-5 pb-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                    <h2 class="text-xl font-extrabold text-brand-blue m-0 mt-1">{{ $heading }}</h2>
                </div>
                <div class="px-5 md:px-6 pb-5 pt-2">
                    @if ($type === 'list')
                        <ul class="space-y-3 m-0 p-0 list-none">
                            @foreach (preg_split('/\r\n|\r|\n/', $body) as $line)
                                @if (trim($line) !== '')
                                    <li class="flex items-start gap-3 text-gray-700 text-[15px] leading-relaxed">
                                        <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-brand-gold shrink-0"></span>
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
            <div class="apply-panel p-6 text-gray-600">
                Details for this posting will be shared during the application process.
            </div>
        @endforelse

        @if ($isInternship)
            <article class="apply-panel overflow-hidden">
                <div class="px-5 md:px-6 pt-5 pb-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">Checklist</p>
                    <h2 class="text-xl font-extrabold text-brand-blue m-0 mt-1">What you will need</h2>
                </div>
                <div class="px-5 md:px-6 pb-5 pt-2 text-[15px] text-gray-700 leading-relaxed">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex gap-3">
                            <i data-lucide="layers" class="w-5 h-5 text-brand-blue shrink-0 mt-0.5"></i>
                            <div><strong class="text-brand-blue">Program + duration</strong><p class="text-sm text-gray-600 m-0 mt-1">Pick your track and how many days you will intern.</p></div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex gap-3">
                            <i data-lucide="credit-card" class="w-5 h-5 text-brand-blue shrink-0 mt-0.5"></i>
                            <div><strong class="text-brand-blue">National ID</strong><p class="text-sm text-gray-600 m-0 mt-1">Front and back photos.</p></div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex gap-3">
                            <i data-lucide="file-text" class="w-5 h-5 text-brand-blue shrink-0 mt-0.5"></i>
                            <div><strong class="text-brand-blue">School letter</strong><p class="text-sm text-gray-600 m-0 mt-1">If this internship is academic-required.</p></div>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex gap-3">
                            <i data-lucide="camera" class="w-5 h-5 text-brand-blue shrink-0 mt-0.5"></i>
                            <div><strong class="text-brand-blue">Selfie + signature</strong><p class="text-sm text-gray-600 m-0 mt-1">Confirm your identity digitally.</p></div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 m-0 pt-4">Unpaid internship. Selected candidates complete daily timesheets (minimum 40 hours/week).</p>
                </div>
            </article>
        @endif

        @if (! $availability['available'])
            <div class="apply-panel p-8 text-center">
                <i data-lucide="lock" class="w-10 h-10 text-gray-300 mx-auto mb-3"></i>
                <p class="text-gray-700 font-medium">{{ $availability['reason'] }}</p>
                <a href="{{ route('apply.index') }}" class="inline-block mt-4 text-brand-blue font-semibold hover:underline">Browse other openings</a>
            </div>
        @else
            <div class="apply-panel p-6 md:p-8 text-center border border-brand-gold/35">
                <h2 class="text-2xl font-extrabold text-brand-blue mb-2">Ready to apply?</h2>
                <p class="text-gray-600 mb-5 max-w-lg mx-auto">
                    @if($isInternship)
                        Next you will choose your <strong>program</strong>, duration, and upload documents.
                    @else
                        Continue to enter your details and upload your CV.
                    @endif
                </p>
                <a href="{{ route('apply.form', $job->id) }}"
                   class="inline-flex items-center gap-2 bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold px-8 py-3.5 rounded-xl text-base shadow-md">
                    Apply now <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

