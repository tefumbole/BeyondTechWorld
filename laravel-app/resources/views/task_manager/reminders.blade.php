@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.reminders'; @endphp
<section class="forms">
    <div class="container-fluid tm-shell">
        @include('task_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="tm-title"><i class="dripicons-clock"></i> Task Reminders</h1>
            <p class="tm-subtitle">Scheduled WhatsApp reminders for upcoming task deadlines. Sent reminders leave this list automatically.</p>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        <div class="tm-page-card">
            <form method="POST" action="{{ route('tasks.reminders.delete_selected') }}" id="tm-reminders-form">
                @csrf
                @if($reminders->count())
                    <div class="tm-toolbar">
                        <button type="submit" class="tm-btn-danger" id="tm-delete-selected" disabled onclick="return window.tmConfirmDeleteSelected();">
                            <i class="dripicons-trash"></i> Delete selected
                        </button>
                        <span class="text-muted small" id="tm-selected-count">0 selected</span>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px;">
                                    @if($reminders->count())
                                        <input type="checkbox" class="tm-check" id="tm-select-all" title="Select all">
                                    @endif
                                </th>
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
                                    <td>
                                        <input type="checkbox" class="tm-check tm-reminder-check" name="ids[]" value="{{ $reminder->id }}">
                                    </td>
                                    <td><strong>{{ optional($reminder->task)->title ?: '—' }}</strong></td>
                                    <td><span class="badge badge-warning">{{ optional($reminder->task)->priority }}</span></td>
                                    <td>{{ optional($reminder->reminder_time)->format('M d, Y H:i') }}</td>
                                    <td>
                                        {{ optional(optional($reminder->task)->deadline)->format('M d, Y') }}
                                        {{ optional($reminder->task)->deadline_time ? substr($reminder->task->deadline_time, 0, 5) : '' }}
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">Pending</span>
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" form="tm-reminder-delete-{{ $reminder->id }}" onclick="return confirm('Delete this reminder?');">
                                            <i class="dripicons-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No pending reminders.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            @foreach($reminders as $reminder)
                <form method="POST" action="{{ route('tasks.reminders.delete', $reminder->id) }}" id="tm-reminder-delete-{{ $reminder->id }}" class="d-none">
                    @csrf
                </form>
            @endforeach
            <div class="mt-3">{{ $reminders->links() }}</div>
        </div>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('tm-reminders-form');
    if (!form) return;
    var boxes = form.querySelectorAll('.tm-reminder-check');
    var all = document.getElementById('tm-select-all');
    var btn = document.getElementById('tm-delete-selected');
    var countEl = document.getElementById('tm-selected-count');

    function sync() {
        var n = 0;
        boxes.forEach(function (box) { if (box.checked) n++; });
        if (btn) btn.disabled = n === 0;
        if (countEl) countEl.textContent = n + ' selected';
        if (all) all.checked = boxes.length > 0 && n === boxes.length;
    }

    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (box) { box.checked = all.checked; });
            sync();
        });
    }
    boxes.forEach(function (box) { box.addEventListener('change', sync); });
    window.tmConfirmDeleteSelected = function () {
        var n = form.querySelectorAll('.tm-reminder-check:checked').length;
        if (!n) return false;
        return confirm('Delete ' + n + ' selected reminder' + (n === 1 ? '' : 's') + '?');
    };
    sync();
})();
</script>
@endsection
