@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.scheduled'; @endphp
<section class="forms">
    <div class="container-fluid tm-shell">
        @include('task_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="tm-title">Scheduled Tasks</h1>
            <p class="tm-subtitle">Tasks waiting for their send time (Africa/Kigali).</p>
        </div>
        <div class="tm-page-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Subject</th><th>Send at</th><th>Assignees</th><th>Priority</th></tr></thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr>
                                <td><strong>{{ $task->title }}</strong></td>
                                <td>{{ optional($task->scheduled_for)->format('d M Y H:i') ?: '—' }}</td>
                                <td>{{ $task->assignments->count() }}</td>
                                <td>{{ $task->priority }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No scheduled tasks.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($tasks, 'links'))
                <div class="mt-3">{{ $tasks->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
