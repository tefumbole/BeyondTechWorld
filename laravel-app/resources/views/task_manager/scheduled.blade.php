@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.scheduled'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <h3 style="color:#0b3f90;">Scheduled Tasks</h3>
        <p class="text-muted">Tasks waiting for their send time (Africa/Kigali).</p>
        <div class="card">
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
                <div class="card-footer">{{ $tasks->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
