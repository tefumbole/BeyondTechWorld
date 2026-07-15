@php
    $tmTab = $tmTab ?? '';
@endphp
<div class="mb-3 beyond-task-tabs d-flex flex-wrap" style="gap:8px;border-bottom:1px solid #e5eaf3;padding-bottom:10px;">
    @foreach([
        ['tasks.dashboard', 'Task Dashboard', 'dripicons-view-thumb'],
        ['tasks.create', 'Create Task', 'dripicons-plus'],
        ['tasks.index', 'All Tasks', 'dripicons-view-list'],
        ['tasks.scheduled', 'Scheduled', 'dripicons-calendar'],
        ['tasks.reminders', 'Reminders', 'dripicons-clock'],
        ['tasks.pending', 'Pending Acceptances', 'dripicons-message'],
        ['tasks.settings', 'Task Settings', 'dripicons-gear'],
    ] as $tab)
        <a href="{{ route($tab[0]) }}" class="btn btn-sm {{ $tmTab === $tab[0] ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</div>
