@extends('beyond.layout')

@section('title', 'Pending Task Acceptances')
@section('meta_description', 'Tasks awaiting your acceptance.')

@php
    $priorityColors = [
        'Low' => 'bg-blue-100 text-blue-800',
        'Medium' => 'bg-yellow-100 text-yellow-800',
        'High' => 'bg-orange-100 text-orange-800',
        'Critical' => 'bg-red-100 text-red-800',
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-brand-blue">Pending Acceptances</h1>
                <p class="text-gray-500">Review and sign to accept tasks assigned to you.</p>
            </div>
            <a href="{{ route('user.tasks') }}" class="inline-flex items-center gap-2 border border-brand-blue text-brand-blue px-4 py-2 rounded-md font-medium hover:bg-blue-50">
                <i data-lucide="list-checks" class="w-4 h-4"></i> All My Tasks
            </a>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i> {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif

        @if ($assignments->isEmpty())
            <div class="text-center py-16 bg-white rounded-lg border border-dashed">
                <i data-lucide="inbox" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                <h3 class="text-lg font-medium text-gray-900">Nothing pending</h3>
                <p class="text-gray-500">You have no tasks awaiting acceptance.</p>
            </div>
        @else
            <div class="space-y-4" x-data="{ signFor: null }">
                @foreach ($assignments as $a)
                    @php $task = $a->task; @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <div class="flex justify-between items-start gap-3 mb-2">
                            <div class="flex gap-2 flex-wrap">
                                <span class="text-xs px-2 py-1 rounded-full font-medium {{ $priorityColors[$task->priority] ?? 'bg-gray-100 text-gray-700' }}">{{ $task->priority }}</span>
                                @if ($task->category)
                                    <span class="text-xs px-2 py-1 rounded-full font-medium border" style="border-color: {{ $task->category->color }}; color: {{ $task->category->color }}; background-color: {{ $task->category->color }}15;">{{ $task->category->name }}</span>
                                @endif
                            </div>
                            @if ($task->deadline)
                                <span class="text-xs text-gray-500 inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Due {{ $task->deadline->format('M d, Y') }}</span>
                            @endif
                        </div>
                        <h3 class="font-bold text-lg text-gray-900">{{ $task->title }}</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">{{ $task->description }}</p>
                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="signFor = '{{ $a->id }}'" class="bg-brand-blue text-white hover:bg-brand-dark text-sm font-semibold px-5 py-2 rounded-md inline-flex items-center gap-2">
                                <i data-lucide="check-circle" class="w-4 h-4"></i> Accept &amp; Sign
                            </button>
                            <form method="POST" action="{{ route('user.tasks.decline', $a->id) }}">
                                @csrf
                                <button type="submit" class="text-sm border border-red-200 text-red-600 px-4 py-2 rounded-md hover:bg-red-50">Decline</button>
                            </form>
                        </div>
                    </div>

                    @include('beyond.tasks.partials.sign-modal', [
                        'assignmentId' => $a->id,
                        'action' => route('user.tasks.accept', $a->id),
                    ])
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
