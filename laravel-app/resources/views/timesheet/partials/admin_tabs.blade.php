@include('timesheet.partials.styles')
@php
    $tsaTab = $tsaTab ?? '';
    $tabs = [
        ['timesheet.admin.report', 'TimeSheet Report', 'dripicons-document'],
        ['timesheet.admin.overtime', 'Overtime Report', 'dripicons-clock'],
        ['timesheet.admin.manage', 'Manage All', 'dripicons-user-group'],
        ['timesheet.admin.categories', 'Categories', 'dripicons-tags'],
    ];
@endphp
<nav class="ts-nav" aria-label="Timesheet Admin">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tsaTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
