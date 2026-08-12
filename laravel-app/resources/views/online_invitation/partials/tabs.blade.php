@include('task_manager.partials.styles')
@php
    $oiTab = $oiTab ?? '';
    $status = request('status', '');
    $tabs = [
        ['route' => 'online_invitation.categories.index', 'label' => 'Categories', 'icon' => 'dripicons-tags', 'tone' => 'tone-blue', 'key' => 'categories'],
        ['route' => 'online_invitation.templates.index', 'label' => 'Templates', 'icon' => 'dripicons-photo', 'tone' => 'tone-gold', 'key' => 'templates'],
        ['route' => 'online_invitation.events.index', 'label' => 'Events', 'icon' => 'dripicons-calendar', 'tone' => 'tone-purple', 'key' => 'events'],
        ['route' => 'online_invitation.invitations.index', 'label' => 'All Invitations', 'icon' => 'dripicons-ticket', 'tone' => 'tone-pink', 'key' => 'invitations', 'params' => []],
        ['route' => 'online_invitation.invitations.index', 'label' => 'Awaiting Sending', 'icon' => 'dripicons-clock', 'tone' => 'tone-orange', 'key' => 'awaiting', 'params' => ['status' => 'awaiting_sending']],
        ['route' => 'online_invitation.invitations.index', 'label' => 'Sent', 'icon' => 'dripicons-checkmark', 'tone' => 'tone-green', 'key' => 'sent', 'params' => ['status' => 'sent']],
        ['route' => 'online_invitation.invitations.index', 'label' => 'Used', 'icon' => 'dripicons-user', 'tone' => 'tone-teal', 'key' => 'used', 'params' => ['status' => 'used']],
        ['route' => 'online_invitation.invitations.index', 'label' => 'Failed', 'icon' => 'dripicons-warning', 'tone' => 'tone-red', 'key' => 'failed', 'params' => ['status' => 'failed']],
        ['route' => 'online_invitation.invitations.attending', 'label' => 'Attending', 'icon' => 'dripicons-user-group', 'tone' => 'tone-blue', 'key' => 'attending'],
        ['route' => 'online_invitation.request_links.index', 'label' => 'Request Links', 'icon' => 'dripicons-link', 'tone' => 'tone-gold', 'key' => 'request_links'],
        ['route' => 'online_invitation.reminders.index', 'label' => 'Reminders', 'icon' => 'dripicons-bell', 'tone' => 'tone-purple', 'key' => 'reminders'],
        ['route' => 'message.delivery.index', 'label' => 'Queued Messages', 'icon' => 'dripicons-to-do', 'tone' => 'tone-teal', 'key' => 'queued', 'params' => ['module' => 'invitations']],
        ['route' => 'online_invitation.invitations.create', 'label' => 'Create Invitation', 'icon' => 'dripicons-plus', 'tone' => 'tone-pink', 'key' => 'create'],
    ];
@endphp
<nav class="tm-nav" aria-label="Digital Invitations">
    @foreach($tabs as $tab)
        @php
            $isActive = $oiTab === $tab['key'];
            if (! $isActive && empty($oiTab) && ($tab['key'] ?? '') === 'invitations' && request()->routeIs('online_invitation.invitations.index') && $status === '') {
                $isActive = true;
            }
            $href = route($tab['route'], $tab['params'] ?? []);
        @endphp
        <a href="{{ $href }}" class="{{ $tab['tone'] }} {{ $isActive ? 'is-active' : '' }}">
            <i class="{{ $tab['icon'] }}"></i> {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
