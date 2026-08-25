@include('timesheet.partials.styles')
@php
    $tsaTab = $tsaTab ?? '';
    $tabs = [
        ['timesheet.admin.report', 'Reports', 'dripicons-document', 'tone-blue'],
        ['timesheet.admin.overtime', 'Overtime Report', 'dripicons-clock', 'tone-orange'],
        ['timesheet.admin.manage', 'Manage All', 'dripicons-user-group', 'tone-purple'],
        ['timesheet.admin.categories', 'Categories', 'dripicons-tags', 'tone-teal'],
    ];
@endphp
<nav class="ts-nav" aria-label="Timesheet Admin">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $tsaTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
