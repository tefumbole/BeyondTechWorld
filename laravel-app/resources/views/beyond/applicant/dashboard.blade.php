@extends('beyond.layout')

@section('title', 'Candidate Portal')
@section('meta_description', 'Track your job applications with Beyond Enterprise.')

@php
    $statusColors = [
        'awaiting_approval' => 'bg-yellow-100 text-yellow-800',
        'new' => 'bg-yellow-100 text-yellow-800',
        'reviewed' => 'bg-yellow-100 text-yellow-800',
        'interview' => 'bg-indigo-100 text-indigo-800',
        'selected' => 'bg-purple-100 text-purple-800',
        'shortlisted' => 'bg-purple-100 text-purple-800',
        'hired' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-700',
        'withdrawn' => 'bg-red-100 text-red-700',
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-brand-blue">Candidate Portal</h1>
                <p class="text-gray-500">Applicant: {{ $user->name ?: $user->email }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 border border-brand-blue text-brand-blue px-4 py-2 rounded-md font-medium hover:bg-blue-50">
                    <i data-lucide="briefcase" class="w-4 h-4"></i> Browse Jobs
                </a>
                <form method="POST" action="{{ route('beyond.logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-200 text-red-600 px-4 py-2 rounded-md font-medium hover:bg-red-50">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-brand-blue mb-4">My Applications</h2>

                @if ($applications->isEmpty())
                    <div class="text-center py-10">
                        <i data-lucide="file-text" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-gray-500 mb-4">You haven't applied to any jobs yet.</p>
                        <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold">
                            <i data-lucide="search" class="w-4 h-4"></i> Find Opportunities
                        </a>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($applications as $app)
                            @php $badge = $statusColors[$app->status] ?? 'bg-gray-100 text-gray-700'; @endphp
                            <div class="p-4 border rounded-lg bg-white shadow-sm border-l-4 border-l-brand-gold">
                                <div class="flex justify-between items-start gap-3 mb-2">
                                    <h3 class="font-bold text-gray-900">{{ optional($app->job)->title ?? 'Position' }}</h3>
                                    <span class="text-xs px-2 py-1 rounded font-bold {{ $badge }}">{{ $app->statusLabel() }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mb-3">
                                    Applied {{ $app->created_at->format('M j, Y') }} · Ref: <span class="font-mono">{{ $app->reference_number }}</span>
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @if (optional($app->job)->id)
                                        <a href="{{ route('apply.show', $app->job->id) }}" class="text-sm border border-gray-200 px-3 py-1.5 rounded-md text-gray-700 hover:bg-gray-50">View Job</a>
                                    @endif
                                    @if ($app->cv_path)
                                        <a href="{{ route('applicant.cv', $app->id) }}" class="text-sm border border-gray-200 px-3 py-1.5 rounded-md text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1">
                                            <i data-lucide="download" class="w-3.5 h-3.5"></i> My CV
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-brand-blue mb-4">Upcoming Interviews</h2>
                @if ($interviews->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <i data-lucide="calendar" class="w-12 h-12 text-gray-300 mb-2"></i>
                        <p class="text-gray-500 font-medium">No interviews scheduled yet.</p>
                        <p class="text-xs text-gray-400">We will notify you via email and WhatsApp when an interview is set.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($interviews as $app)
                            <div class="p-4 border rounded-lg border-l-4 border-l-indigo-400">
                                <h3 class="font-bold text-gray-900">{{ optional($app->job)->title ?? 'Position' }}</h3>
                                <p class="text-sm text-indigo-700 mt-1 flex items-center gap-1.5">
                                    <i data-lucide="calendar-clock" class="w-4 h-4"></i>
                                    {{ $app->interview_date->format('M j, Y g:i A') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
