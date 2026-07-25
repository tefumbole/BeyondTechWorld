@include('task_manager.partials.styles')
@php
    $tmTab = $tmTab ?? '';
    $tabs = [
        ['tasks.dashboard', 'Task Dashboard', 'dripicons-view-thumb', 'tone-blue'],
        ['tasks.create', 'Create Task', 'dripicons-plus', 'tone-gold'],
        ['tasks.index', 'All Tasks', 'dripicons-view-list', 'tone-purple'],
        ['tasks.scheduled', 'Scheduled', 'dripicons-calendar', 'tone-orange'],
        ['tasks.reminders', 'Reminders', 'dripicons-clock', 'tone-teal'],
        ['user.tasks', 'My Tasks', 'dripicons-checkmark', 'tone-green'],
        ['tasks.pending', 'Pending Acceptances', 'dripicons-inbox', 'tone-pink'],
        ['tasks.settings', 'Task Settings', 'dripicons-gear', 'tone-red'],
    ];
@endphp
<nav class="tm-nav" aria-label="Task Manager">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $tmTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
