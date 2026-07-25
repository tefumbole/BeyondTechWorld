@include('announcement_manager.partials.styles')
@php
    $anTab = $anTab ?? '';
    $tabs = [
        ['announcements.compose', 'Compose', 'dripicons-document-edit', 'tone-blue'],
        ['announcements.index', 'All Announcements', 'dripicons-document', 'tone-gold'],
        ['announcements.scheduled', 'Scheduled', 'dripicons-clock', 'tone-orange'],
        ['announcements.reminders', 'Reminders', 'dripicons-clock', 'tone-teal'],
        ['announcements.templates', 'Templates', 'dripicons-folder', 'tone-purple'],
        ['announcements.categories', 'Categories', 'dripicons-view-list', 'tone-green'],
        ['announcements.settings', 'Settings', 'dripicons-gear', 'tone-red'],
    ];
@endphp
<nav class="an-nav" aria-label="Announcements">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $anTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
