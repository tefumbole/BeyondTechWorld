@extends('beyond.layout')

@section('title', 'Apply Now — Jobs & Internships')
@section('meta_description', 'Apply for jobs and internships published on the Beyond Enterprise Job Board.')

@section('content')
@php
    $internshipsFirst = !empty($internshipsFirst);
    $sections = $internshipsFirst
        ? [
            ['key' => 'internships', 'title' => 'Internships', 'subtitle' => 'Unpaid internship adverts for students.', 'items' => $internships, 'empty' => 'No internships available', 'emptyHint' => $search ? 'No internships matched your search.' : 'Active internship adverts will appear here.', 'icon' => 'graduation-cap'],
            ['key' => 'jobs', 'title' => 'Jobs', 'subtitle' => 'Paid roles with salary details.', 'items' => $jobs, 'empty' => 'No jobs available', 'emptyHint' => $search ? 'No jobs matched your search.' : 'Active job postings will appear here.', 'icon' => 'briefcase'],
        ]
        : [
            ['key' => 'jobs', 'title' => 'Jobs', 'subtitle' => 'Paid roles with salary details.', 'items' => $jobs, 'empty' => 'No jobs available', 'emptyHint' => $search ? 'No jobs matched your search.' : 'Active job postings will appear here.', 'icon' => 'briefcase'],
            ['key' => 'internships', 'title' => 'Internships', 'subtitle' => 'Unpaid internship adverts for students.', 'items' => $internships, 'empty' => 'No internships available', 'emptyHint' => $search ? 'No internships matched your search.' : 'Active internship adverts will appear here.', 'icon' => 'graduation-cap'],
        ];
@endphp
<div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-20 px-4 relative overflow-hidden">
        <div class="max-w-7xl mx-auto text-center relative z-10">
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 tracking-tight">Apply Now</h1>
            <p class="text-xl text-blue-100 max-w-2xl mx-auto font-light leading-relaxed">
                Browse real jobs and internship adverts — then apply online in minutes.
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

        @foreach ($sections as $i => $section)
            @php
                // Hide empty Jobs block when internships-only (avoid a useless empty Jobs card first/second).
                $hideEmptyJobs = $internshipsFirst && $section['key'] === 'jobs' && $section['items']->isEmpty();
            @endphp
            @continue($hideEmptyJobs)

            <section class="{{ $i < count($sections) - 1 ? 'mb-14' : '' }}">
                <div class="flex items-end justify-between mb-6 gap-4">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-extrabold text-brand-blue">{{ $section['title'] }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $section['subtitle'] }}</p>
                    </div>
                </div>

                @if ($section['items']->isEmpty())
                    <div class="text-center py-16 bg-white rounded-xl shadow-sm border border-gray-100">
                        <i data-lucide="{{ $section['icon'] }}" class="w-14 h-14 text-gray-300 mx-auto mb-3"></i>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">{{ $section['empty'] }}</h3>
                        <p class="text-gray-500 max-w-md mx-auto">{{ $section['emptyHint'] }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        @foreach ($section['items'] as $job)
                            @include('beyond.apply.partials.job_card', ['job' => $job, 'stats' => $stats])
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
