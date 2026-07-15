@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.index'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 style="color:#0b3f90;" class="mb-1">All Tasks</h3>
                <p class="text-muted mb-0">Manage tasks and track assignments.</p>
            </div>
            @if(in_array('tasks.create', $all_permission ?? []))
                <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><i class="dripicons-plus"></i> New Task</a>
            @endif
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="GET" class="form-inline mb-3">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="Search subject…">
            <select name="status" class="form-control mr-2 mb-2">
                <option value="all">All statuses</option>
                @foreach(['Pending','Accepted','In Progress','Completed'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary mb-2">Filter</button>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Deadline</th>
                            <th>Assignees</th>
                            <th>CC</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr>
                                <td>
                                    <strong>{{ $task->title }}</strong>
                                    @if($task->is_scheduled && ! $task->notifications_sent)
                                        <span class="badge badge-info">Scheduled</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-warning">{{ $task->priority }}</span></td>
                                <td>
                                    {{ $task->deadline ? $task->deadline->format('d M Y') : '—' }}
                                    {{ $task->deadline_time ? substr($task->deadline_time, 0, 5) : '' }}
                                </td>
                                <td>{{ $task->assignments->count() }}</td>
                                <td>{{ $task->ccRecipients->count() }}</td>
                                <td>{{ $task->status }}</td>
                                <td class="text-right">
                                    @if(in_array('tasks.delete', $all_permission ?? []))
                                        <form method="POST" action="{{ route('tasks.destroy', $task->id) }}" class="d-inline" onsubmit="return confirm('Delete this task?');">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No tasks yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($tasks, 'links'))
                <div class="card-footer">{{ $tasks->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
