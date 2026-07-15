@include('task_manager.partials.styles')
@php
    $tmTab = $tmTab ?? '';
    $tabs = [
        ['tasks.dashboard', 'Task Dashboard', 'dripicons-view-thumb'],
        ['tasks.create', 'Create Task', 'dripicons-plus'],
        ['tasks.index', 'All Tasks', 'dripicons-view-list'],
        ['tasks.scheduled', 'Scheduled', 'dripicons-calendar'],
        ['tasks.reminders', 'Reminders', 'dripicons-clock'],
        ['user.tasks', 'My Tasks', 'dripicons-checkmark'],
        ['tasks.pending', 'Pending Acceptances', 'dripicons-inbox'],
        ['tasks.settings', 'Task Settings', 'dripicons-gear'],
    ];
@endphp
<nav class="tm-nav" aria-label="Task Manager">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tmTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
