@extends('beyond.layout')

@section('title', 'My Tasks')
@section('meta_description', 'Manage your assigned tasks and update progress.')

@php
    $statusColors = [
        'Pending' => 'bg-gray-100 text-gray-700 border-gray-200',
        'Accepted' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
        'In Progress' => 'bg-blue-100 text-blue-700 border-blue-200',
        'Completed' => 'bg-green-100 text-green-700 border-green-200',
        'Overdue' => 'bg-red-100 text-red-700 border-red-200',
        'Declined' => 'bg-red-50 text-red-500 border-red-100',
    ];
    $priorityColors = [
        'Low' => 'bg-blue-100 text-blue-800',
        'Medium' => 'bg-yellow-100 text-yellow-800',
        'High' => 'bg-orange-100 text-orange-800',
        'Critical' => 'bg-red-100 text-red-800',
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-brand-blue">My Tasks</h1>
                <p class="text-gray-500">Manage your assigned tasks and update progress.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('user.tasks.pending') }}" class="inline-flex items-center gap-2 border border-brand-blue text-brand-blue px-4 py-2 rounded-md font-medium hover:bg-blue-50">
                    <i data-lucide="inbox" class="w-4 h-4"></i> Pending Acceptances
                </a>
                <form method="POST" action="{{ route('beyond.logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-200 text-red-600 px-4 py-2 rounded-md font-medium hover:bg-red-50">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i> {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $errors->first() }}</div>
        @endif

        <form method="GET" class="flex flex-wrap gap-3 items-center bg-white p-3 rounded-lg shadow-sm border">
            <div class="flex gap-2 flex-wrap">
                @foreach ($statuses as $s)
                    <a href="{{ route('user.tasks', array_filter(['status' => $s === 'All' ? null : $s, 'category' => $categoryFilter === 'All' ? null : $categoryFilter])) }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium {{ $statusFilter === $s ? 'bg-brand-blue text-white' : 'border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                        {{ $s }}
                    </a>
                @endforeach
            </div>
            <div class="h-6 w-px bg-gray-200 hidden md:block"></div>
            <select name="category" onchange="window.location.href='{{ route('user.tasks') }}?status={{ $statusFilter }}&category='+this.value"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-900">
                <option value="All">All Categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @if($categoryFilter === $c->id) selected @endif>{{ $c->name }}</option>
                @endforeach
            </select>
        </form>

        @if ($assignments->isEmpty())
            <div class="text-center py-16 bg-white rounded-lg border border-dashed">
                <i data-lucide="check-circle" class="w-12 h-12 mx-auto text-gray-300 mb-3"></i>
                <h3 class="text-lg font-medium text-gray-900">No tasks found</h3>
                <p class="text-gray-500">You don't have any tasks matching the selected filters.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                 x-data="{ signFor: null }">
                @foreach ($assignments as $a)
                    @php
                        $task = $a->task;
                        $isCompleted = $a->status === 'Completed';
                        $isPending = $a->status === 'Pending';
                        $isDeclined = $a->status === 'Declined';
                        $overdue = $service->isOverdue($a);
                        $displayStatus = $overdue && ! $isCompleted ? 'Overdue' : $a->status;
                        $badge = $statusColors[$displayStatus] ?? 'bg-gray-100 text-gray-700';
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border-t-4 flex flex-col {{ ($isCompleted || $isDeclined) ? 'opacity-75' : '' }}"
                         style="border-top-color: {{ $service->priorityColor($task->priority) }}">
                        <div class="p-5 pb-2">
                            <div class="flex justify-between items-start mb-2 gap-2">
                                <div class="flex gap-2 flex-wrap">
                                    <span class="text-xs px-2 py-1 rounded-full font-medium {{ $priorityColors[$task->priority] ?? 'bg-gray-100 text-gray-700' }}">{{ $task->priority }}</span>
                                    @if ($task->category)
                                        <span class="text-xs px-2 py-1 rounded-full font-medium border"
                                              style="border-color: {{ $task->category->color }}; color: {{ $task->category->color }}; background-color: {{ $task->category->color }}15;">
                                            {{ $task->category->name }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full border font-medium {{ $badge }}">{{ $displayStatus }}</span>
                            </div>
                            <h3 class="font-bold text-lg text-gray-900 line-clamp-2">{{ $task->title }}</h3>
                        </div>
                        <div class="px-5 pb-4 flex-1">
                            <p class="text-sm text-gray-500 line-clamp-2 mb-4">{{ $task->description }}</p>

                            @if (! $isPending && ! $isDeclined)
                                <form method="POST" action="{{ route('user.tasks.update', $a->id) }}"
                                      class="space-y-3 bg-gray-50 p-3 rounded-lg border border-gray-100"
                                      x-data="{ progress: {{ $a->progress }}, status: '{{ $a->status }}' }">
                                    @csrf
                                    <div class="flex justify-between text-xs font-medium text-gray-700">
                                        <span>Update Progress</span>
                                        <span x-text="progress + '%'"></span>
                                    </div>
                                    <input type="range" name="progress" min="0" max="100" step="5" x-model="progress"
                                           @if($isCompleted) disabled @endif class="w-full accent-brand-blue">
                                    <div class="flex items-center gap-2">
                                        <select name="status" x-model="status" @if($isCompleted) disabled @endif
                                                class="h-8 text-xs bg-white text-gray-900 flex-1 rounded border border-gray-200 px-2">
                                            <option value="Accepted">Accepted</option>
                                            <option value="In Progress">In Progress</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                        @if (! $isCompleted)
                                            <button type="submit" class="h-8 px-3 bg-brand-blue text-white rounded inline-flex items-center gap-1 text-xs font-semibold">
                                                <i data-lucide="save" class="w-3 h-3"></i> Save
                                            </button>
                                        @endif
                                    </div>
                                </form>
                            @endif

                            @if ($task->deadline)
                                <div class="mt-4 flex items-center text-xs {{ $overdue && ! $isCompleted ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                    <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>
                                    Due: {{ $task->deadline->format('M d, Y') }}{{ $task->deadline_time ? ' '.\Illuminate\Support\Str::substr($task->deadline_time, 0, 5) : '' }}
                                </div>
                            @endif
                        </div>
                        <div class="bg-gray-50/50 border-t p-3 flex gap-2 justify-end">
                            @if ($isPending)
                                <button type="button" @click="signFor = '{{ $a->id }}'"
                                        class="flex-1 bg-brand-blue text-white hover:bg-brand-dark text-sm font-semibold py-2 rounded-md">
                                    Accept Task
                                </button>
                                <form method="POST" action="{{ route('user.tasks.decline', $a->id) }}">
                                    @csrf
                                    <button type="submit" class="text-sm border border-red-200 text-red-600 px-3 py-2 rounded-md hover:bg-red-50">Decline</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('user.tasks.remove', $a->id) }}" onsubmit="return confirm('Remove this task from your list?')">
                                    @csrf
                                    <button type="submit" class="text-sm text-gray-500 hover:text-red-600 px-3 py-2 inline-flex items-center gap-1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i> Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($isPending)
                        @include('beyond.tasks.partials.sign-modal', [
                            'assignmentId' => $a->id,
                            'action' => route('user.tasks.accept', $a->id),
                        ])
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
