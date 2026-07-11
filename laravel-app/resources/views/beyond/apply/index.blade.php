@extends('beyond.layout')

@section('title', 'Careers — Apply Now')
@section('meta_description', 'Join Beyond Enterprise. Explore our open positions and apply online.')

@section('content')
<div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-20 px-4 relative overflow-hidden">
        <div class="max-w-7xl mx-auto text-center relative z-10">
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 tracking-tight">Build Your Future With Us</h1>
            <p class="text-xl text-blue-100 max-w-2xl mx-auto font-light leading-relaxed">
                Join a team of innovators, creators, and problem solvers. Explore our open positions and find where you belong.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-10 relative z-20">

        @if (session('warning'))
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm mb-6">{{ session('warning') }}</div>
        @endif

        <form method="GET" action="{{ route('apply.index') }}" class="bg-white rounded-xl shadow-xl p-6 mb-10 border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full max-w-2xl">
                <i data-lucide="search" class="absolute left-4 top-3.5 h-5 w-5 text-gray-400"></i>
                <input name="q" value="{{ $search }}" placeholder="Search by job title, department, or location..."
                       class="w-full pl-12 py-3 text-lg bg-gray-50 border border-gray-200 rounded-md focus:bg-white focus:border-brand-blue outline-none">
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <button type="submit" class="h-12 px-6 rounded-md bg-brand-blue text-white font-semibold hover:bg-brand-dark">Search</button>
                @if ($search)
                    <a href="{{ route('apply.index') }}" class="h-12 px-6 rounded-md border border-gray-200 text-gray-700 font-medium flex items-center hover:bg-gray-50">Clear</a>
                @endif
            </div>
        </form>

        @if ($jobs->isEmpty())
            <div class="text-center py-20 bg-white rounded-xl shadow-sm border border-gray-100">
                <i data-lucide="briefcase" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-700 mb-2">No positions available</h3>
                <p class="text-gray-500 max-w-md mx-auto">
                    {{ $search ? "We couldn't find any jobs matching your search." : 'There are currently no open positions. Please check back later!' }}
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach ($jobs as $job)
                    @php $stat = $stats[$job->id]; @endphp
                    <div class="h-full hover:shadow-xl transition-all duration-300 border-t-4 border-t-brand-gold flex flex-col overflow-hidden relative rounded-xl bg-white shadow-lg">
                        <div class="p-6 flex flex-col h-full">
                            <div class="flex flex-wrap gap-2 items-center mb-4">
                                <span class="bg-gray-100 text-gray-700 font-medium capitalize border border-gray-200 text-xs px-2.5 py-1 rounded-full">{{ $job->department ?: 'General' }}</span>
                                <span class="bg-blue-100 text-blue-700 border border-blue-200 text-xs px-2.5 py-1 rounded-full">{{ $job->employment_type ?: 'Full-Time' }}</span>
                            </div>

                            @if ($job->enable_countdown && $job->deadline)
                                <div class="mb-4 text-xs font-semibold {{ $job->is_expired ? 'text-red-600' : 'text-brand-blue' }}">
                                    <i data-lucide="clock" class="w-3.5 h-3.5 inline"></i>
                                    {{ $job->is_expired ? 'Applications closed' : 'Closes '.$job->deadline->diffForHumans() }}
                                </div>
                            @endif

                            <h3 class="text-xl font-bold text-brand-blue mb-3 line-clamp-2 min-h-[3.5rem]">{{ $job->title }}</h3>

                            <div class="space-y-4 mb-6 flex-1">
                                <div class="flex items-center text-gray-600 text-sm">
                                    <i data-lucide="map-pin" class="w-4 h-4 mr-2 text-brand-gold shrink-0"></i>
                                    <span class="truncate">{{ $job->location ?: 'Remote' }}</span>
                                </div>
                                @if ($job->salary)
                                    <div class="flex items-center text-gray-700 font-medium text-sm">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 text-brand-gold shrink-0"></i>
                                        <span>{{ $job->salary }} RWF</span>
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
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
