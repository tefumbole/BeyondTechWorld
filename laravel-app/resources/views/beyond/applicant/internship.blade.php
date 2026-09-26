@extends('beyond.layout')

@section('title', 'Internship Portal')
@section('meta_description', 'Your internship progress, completed tasks, and supervisor remarks.')

@section('content')
@php
    $progress = $progress ?? [];
    $current = $progress['current'] ?? null;
    $loginUrl = url('/login').'?redirect='.rawurlencode($current
        ? '/admin/internship/student/task/'.$current->id
        : '/admin');
@endphp
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-bold text-brand-blue">Internship Portal</h1>
                <p class="text-gray-500">{{ $user->name ?: $user->email }} · {{ optional($enrolment->program)->displayName() ?: 'Internship' }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $loginUrl }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-4 py-2 rounded-md font-medium hover:bg-blue-900">
                    Open student workspace
                </a>
                <form method="POST" action="{{ route('beyond.logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-200 text-red-600 px-4 py-2 rounded-md font-medium hover:bg-red-50">
                        Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Completed</p>
                <p class="text-3xl font-bold text-brand-blue mt-1">{{ (int) ($progress['completed'] ?? 0) }}</p>
                <p class="text-sm text-gray-500">of {{ (int) ($progress['planned'] ?? 0) }} planned</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Remaining</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ (int) ($progress['remaining'] ?? 0) }}</p>
                <p class="text-sm text-gray-500">{{ number_format((float) ($progress['progress_percent'] ?? 0), 1) }}% progress</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Overall score</p>
                <p class="text-3xl font-bold text-brand-blue mt-1">
                    @if (($progress['overall_score'] ?? null) !== null)
                        {{ number_format((float) $progress['overall_score'], 1) }}%
                    @else
                        —
                    @endif
                </p>
                <p class="text-sm text-gray-500">Average of graded tasks</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Current task</p>
                <p class="text-3xl font-bold text-brand-blue mt-1">{{ $current ? '#'.$current->progression_day : '—' }}</p>
                <p class="text-sm text-gray-500">{{ $current ? str_replace('_', ' ', $current->status) : 'None open' }}</p>
            </div>
        </div>

        @if ($current)
            <div class="bg-white rounded-xl border border-l-4 border-l-brand-gold border-gray-100 p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-brand-gold">Continue your internship</p>
                <h2 class="text-xl font-bold text-brand-blue mt-1">
                    Task #{{ $current->progression_day }} — {{ optional($current->task)->title ?: 'Open task' }}
                </h2>
                <p class="text-sm text-gray-500 mt-1 mb-4">Use the student workspace link above if this button asks you to sign in again.</p>
                <a href="{{ $loginUrl }}" class="inline-flex items-center gap-2 bg-brand-gold text-brand-blue font-bold px-5 py-2.5 rounded-md">
                    Open Task #{{ $current->progression_day }}
                </a>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h2 class="text-lg font-bold text-brand-blue mb-4">Completed tasks &amp; remarks</h2>
            @if (empty($progress['completed_tasks']))
                <p class="text-gray-500">No accepted tasks yet.</p>
            @else
                <div class="space-y-4">
                    @foreach ($progress['completed_tasks'] as $row)
                        @php $item = $row['assignment']; @endphp
                        <div class="border rounded-lg p-4 border-l-4 border-l-green-500">
                            <div class="flex flex-wrap justify-between gap-2 mb-2">
                                <h3 class="font-bold text-gray-900">Task #{{ $item->progression_day }} — {{ optional($item->task)->title ?: 'Task' }}</h3>
                                <span class="text-sm font-semibold text-brand-blue">
                                    @if ($row['score'] !== null)
                                        Score {{ (int) $row['score'] }}%
                                    @else
                                        Accepted
                                    @endif
                                </span>
                            </div>
                            @if (! empty($row['feedback']))
                                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $row['feedback'] }}</p>
                                @if (! empty($row['grader']))
                                    <p class="text-xs text-gray-400 mt-2">— {{ $row['grader'] }}</p>
                                @endif
                            @else
                                <p class="text-sm text-gray-400">No written remarks for this task.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
