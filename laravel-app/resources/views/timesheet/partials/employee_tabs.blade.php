@include('timesheet.partials.styles')
@php
    $tsTab = $tsTab ?? '';
    $tabs = [
        ['timesheet.activities', 'Create Activity', 'dripicons-plus'],
        ['timesheet.fill', 'Fill Time Sheet', 'dripicons-clock'],
        ['timesheet.working-week', 'Working Week', 'dripicons-calendar'],
    ];
@endphp
<nav class="ts-nav" aria-label="Employee Timesheet">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tsTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
