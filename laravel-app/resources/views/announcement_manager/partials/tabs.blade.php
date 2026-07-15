@include('announcement_manager.partials.styles')
@php
    $anTab = $anTab ?? '';
    $tabs = [
        ['announcements.compose', 'Compose', 'dripicons-document-edit'],
        ['announcements.index', 'All Announcements', 'dripicons-document'],
        ['announcements.scheduled', 'Scheduled', 'dripicons-clock'],
        ['announcements.reminders', 'Reminders', 'dripicons-clock'],
        ['announcements.templates', 'Templates', 'dripicons-folder'],
        ['announcements.categories', 'Categories', 'dripicons-view-list'],
        ['announcements.settings', 'Settings', 'dripicons-gear'],
    ];
@endphp
<nav class="an-nav" aria-label="Announcements">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $anTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
