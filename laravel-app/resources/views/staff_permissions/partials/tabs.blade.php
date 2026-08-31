<style>
    .perm-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.5rem;
        padding: 0;
        border: 0;
    }
    .perm-nav a {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 10px;
        border: 2px solid #cbd5e1;
        background: #fff;
        color: #64748b;
        text-decoration: none !important;
        font-weight: 700;
        font-size: 13px;
        line-height: 1.2;
        white-space: nowrap;
    }
    .perm-nav a:hover { transform: translateY(-1px); text-decoration: none !important; }
    .perm-nav a.is-active { color: #fff !important; }
    .perm-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .perm-nav a.tone-orange.is-active, .perm-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .perm-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .perm-nav a.tone-green.is-active, .perm-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .perm-nav a.tone-red { border-color: #ef4444; color: #ef4444; }
    .perm-nav a.tone-red.is-active, .perm-nav a.tone-red:hover { background: #ef4444; border-color: #ef4444; color: #fff !important; }
    .perm-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .perm-nav a.tone-blue.is-active, .perm-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .perm-nav-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
        font-size: 11px; font-weight: 800; background: rgba(15, 23, 42, 0.08); color: inherit;
    }
    .perm-nav a.is-active .perm-nav-count { background: rgba(255, 255, 255, 0.28); }
    .perm-nav a.tone-orange.is-active .perm-nav-count { background: rgba(16, 33, 61, 0.12); color: #10213d; }
</style>
@php
    $permTab = $permTab ?? '';
    $permTabCounts = $permTabCounts ?? \App\StaffPermission::tabCounts();
    $permTabs = [
        ['permissions.requests', 'Awaiting Approval', 'dripicons-clock', 'tone-orange'],
        ['permissions.approved', 'Approved', 'dripicons-checkmark', 'tone-green'],
        ['permissions.denied', 'Denied', 'dripicons-wrong', 'tone-red'],
        ['permissions.index', 'All', 'dripicons-list', 'tone-blue'],
    ];
@endphp
<nav class="perm-nav" aria-label="Permissions">
    @foreach($permTabs as $tab)
        @php $count = $permTabCounts[$tab[0]] ?? null; @endphp
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $permTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
            @if($count !== null)
                <span class="perm-nav-count">{{ $count }}</span>
            @endif
        </a>
    @endforeach
</nav>
