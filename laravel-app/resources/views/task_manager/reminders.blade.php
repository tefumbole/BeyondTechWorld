@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.reminders'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <h3 style="color:#0b3f90;"><i class="dripicons-clock"></i> Task Reminders</h3>
        <p class="text-muted">Scheduled WhatsApp reminders for upcoming task deadlines.</p>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Priority</th>
                            <th>Reminder Time</th>
                            <th>Task Deadline</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reminders as $reminder)
                            <tr>
                                <td><strong>{{ optional($reminder->task)->title ?: '—' }}</strong></td>
                                <td><span class="badge badge-warning">{{ optional($reminder->task)->priority }}</span></td>
                                <td>{{ optional($reminder->reminder_time)->format('M d, Y H:i') }}</td>
                                <td>
                                    {{ optional(optional($reminder->task)->deadline)->format('M d, Y') }}
                                    {{ optional($reminder->task)->deadline_time ? substr($reminder->task->deadline_time, 0, 5) : '' }}
                                </td>
                                <td>
                                    @if($reminder->is_sent)
                                        <span class="badge badge-success">Sent</span>
                                    @else
                                        <span class="badge badge-secondary">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('tasks.reminders.delete', $reminder->id) }}" onsubmit="return confirm('Delete reminder?');">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No reminders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $reminders->links() }}</div>
        </div>
    </div>
</section>
@endsection
