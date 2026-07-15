@php $stat = $stats[$job->id]; @endphp
<div class="h-full hover:shadow-xl transition-all duration-300 border-t-4 {{ $job->isInternship() ? 'border-t-emerald-500' : 'border-t-brand-gold' }} flex flex-col overflow-hidden relative rounded-xl bg-white shadow-lg">
    <div class="p-6 flex flex-col h-full">
        <div class="flex flex-wrap gap-2 items-center mb-4">
            <span class="bg-gray-100 text-gray-700 font-medium capitalize border border-gray-200 text-xs px-2.5 py-1 rounded-full">{{ $job->department ?: 'General' }}</span>
            <span class="{{ $job->isInternship() ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-blue-100 text-blue-700 border-blue-200' }} border text-xs px-2.5 py-1 rounded-full">
                {{ $job->isInternship() ? 'Internship' : ($job->employment_type ?: 'Full-Time') }}
            </span>
        </div>

        @if ($job->enable_countdown && $job->deadline)
            @if ($job->is_expired)
                <div class="mb-4 text-xs font-semibold text-red-600">
                    <i data-lucide="clock" class="w-3.5 h-3.5 inline"></i> Applications closed
                </div>
            @else
                <div class="mb-4 rounded-lg overflow-hidden">
                    @include('beyond.partials.event_countdown', [
                        'targetIso' => $job->deadline->copy()->endOfDay()->toIso8601String(),
                        'compact' => true,
                        'hideAfter' => false,
                        'countdownLabel' => 'Closes in',
                        'completionMessage' => 'Applications closed',
                        'timezone' => config('app.timezone', 'Africa/Kigali'),
                    ])
                </div>
            @endif
        @endif

        <h3 class="text-xl font-bold text-brand-blue mb-3 line-clamp-2 min-h-[3.5rem]">{{ $job->title }}</h3>

        <div class="space-y-4 mb-6 flex-1">
            <div class="flex items-center text-gray-600 text-sm">
                <i data-lucide="map-pin" class="w-4 h-4 mr-2 text-brand-gold shrink-0"></i>
                <span class="truncate">{{ $job->location ?: 'Remote' }}</span>
            </div>
            @if (! $job->isInternship() && $job->salary)
                <div class="flex items-center text-gray-700 font-medium text-sm">
                    <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 text-brand-gold shrink-0"></i>
                    <span>{{ $job->salary }}</span>
                </div>
            @endif
            @if ($job->isInternship())
                <div class="flex items-center text-emerald-700 font-medium text-sm">
                    <i data-lucide="graduation-cap" class="w-4 h-4 mr-2 shrink-0"></i>
                    <span>Unpaid internship · Timesheets required</span>
                </div>
            @endif
            <div class="bg-gray-50 rounded-lg p-3 space-y-2 border border-gray-100 text-xs text-gray-700">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="briefcase" class="w-3.5 h-3.5 text-blue-600"></i>
                    <span class="font-semibold">{{ $job->max_positions ?: 1 }} Position(s) Available</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="users" class="w-3.5 h-3.5 text-blue-600"></i>
                    <span>{{ $stat['total_applicants'] }}{{ $job->max_applicants ? '/'.$job->max_applicants : '' }} Applicant(s)</span>
                </div>
                <div class="flex items-center gap-1 text-gray-400 italic border-t border-gray-200 pt-2 mt-1">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    Last submission: {{ $stat['last_application_date'] }}
                </div>
            </div>
        </div>

        <div class="mt-auto pt-4 border-t border-gray-100">
            <a href="{{ route('apply.show', $job->id) }}"
               class="w-full inline-flex items-center justify-center gap-2 text-white font-semibold shadow-md transition-all py-2.5 rounded-md {{ $job->is_expired ? 'bg-gray-400 cursor-not-allowed pointer-events-none' : 'bg-brand-blue hover:bg-brand-dark' }}">
                {{ $job->is_expired ? 'Closed' : 'View & Apply' }}
                @if (! $job->is_expired)<i data-lucide="arrow-right" class="w-4 h-4"></i>@endif
            </a>
        </div>
    </div>
</div>
