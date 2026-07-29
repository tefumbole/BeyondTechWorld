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
    ];

    $anRoleId = auth()->check() ? (int) auth()->user()->role_id : 0;
    $anCan = function ($permissionName) use ($anRoleId) {
        if (! $anRoleId) return false;
        $permission = \DB::table('permissions')->where('name', $permissionName)->first();
        if (! $permission) return false;
        return (bool) \DB::table('role_has_permissions')->where([
            ['permission_id', $permission->id],
            ['role_id', $anRoleId],
        ])->first();
    };
    if ($anCan('create_sms')) {
        $tabs[] = ['setting.createSms', 'Create SMS', 'dripicons-message', 'tone-pink'];
    }
    if ($anCan('sms_setting')) {
        $tabs[] = ['setting.messaging', 'SMS Settings', 'dripicons-message', 'tone-teal'];
    }
    $tabs[] = ['announcements.settings', 'Settings', 'dripicons-gear', 'tone-red'];
@endphp
<nav class="an-nav" aria-label="Announcements">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $anTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
