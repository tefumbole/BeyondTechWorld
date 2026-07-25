<style>
    .ct-shell { max-width: 1200px; margin: 0 auto; }

    .ct-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.5rem;
        padding: 0;
        border: 0;
    }
    .ct-nav a {
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
        transition: all .15s ease;
        margin: 0;
    }
    .ct-nav a i { font-size: 15px; }
    .ct-nav a:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 35, 80, 0.08);
        text-decoration: none !important;
    }
    .ct-nav a.is-active { color: #fff !important; box-shadow: 0 6px 16px rgba(15, 35, 80, 0.14); }
    .ct-nav a.is-active i { color: #fff !important; }

    .ct-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .ct-nav a.tone-blue i { color: #0b3f90; }
    .ct-nav a.tone-blue.is-active,
    .ct-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .ct-nav a.tone-blue:hover i { color: #fff !important; }

    .ct-nav a.tone-gold { border-color: #c6ab47; color: #8a7424; }
    .ct-nav a.tone-gold i { color: #8a7424; }
    .ct-nav a.tone-gold.is-active,
    .ct-nav a.tone-gold:hover { background: #c6ab47; border-color: #c6ab47; color: #10213d !important; }
    .ct-nav a.tone-gold.is-active i,
    .ct-nav a.tone-gold:hover i { color: #10213d !important; }

    .ct-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .ct-nav a.tone-orange i { color: #c77708; }
    .ct-nav a.tone-orange.is-active,
    .ct-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .ct-nav a.tone-orange.is-active i,
    .ct-nav a.tone-orange:hover i { color: #10213d !important; }

    .ct-nav a.tone-purple { border-color: #7b61ff; color: #7b61ff; }
    .ct-nav a.tone-purple i { color: #7b61ff; }
    .ct-nav a.tone-purple.is-active,
    .ct-nav a.tone-purple:hover { background: #7b61ff; border-color: #7b61ff; color: #fff !important; }
    .ct-nav a.tone-purple:hover i { color: #fff !important; }

    .ct-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .ct-nav a.tone-green i { color: #10b981; }
    .ct-nav a.tone-green.is-active,
    .ct-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .ct-nav a.tone-green:hover i { color: #fff !important; }

    .ct-nav a.tone-teal { border-color: #0ea5a4; color: #0ea5a4; }
    .ct-nav a.tone-teal i { color: #0ea5a4; }
    .ct-nav a.tone-teal.is-active,
    .ct-nav a.tone-teal:hover { background: #0ea5a4; border-color: #0ea5a4; color: #fff !important; }
    .ct-nav a.tone-teal:hover i { color: #fff !important; }

    .ct-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .ct-subtitle { color: #6b7280; margin: 0; }
    .ct-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem; margin-bottom: 1rem;
    }
    .ct-btn {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
    }
    .ct-btn:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .ct-btn-secondary {
        background: #fff; border: 1px solid #0b3f90; color: #0b3f90;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
    }
    .ct-btn-danger {
        background: #fff; border: 1px solid #dc3545; color: #dc3545;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
    }
    .ct-field { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
    .ct-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .ct-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    .ct-badge-success { background: #d1fae5; color: #065f46; }
    .ct-badge-warning { background: #fef3c7; color: #92400e; }
    .ct-badge-danger { background: #fee2e2; color: #991b1b; }
    .ct-badge-info { background: #dbeafe; color: #1e40af; }
    .ct-step-nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem; }
    .ct-step-btn {
        border: 1px solid #d7deea; background: #f8fafc; border-radius: 8px;
        padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer;
    }
    .ct-step-btn.is-active { background: #0b3f90; border-color: #0b3f90; color: #fff; }
    .ct-wizard-step { display: none; }
    .ct-wizard-step.is-visible { display: block; }
</style>
@php
    $ctTab = $ctTab ?? '';
    $tabs = [
        ['contracts.dashboard', 'Dashboard', 'dripicons-graph-bar', 'tone-blue'],
        ['contracts.index', 'Contract List', 'dripicons-document', 'tone-blue'],
        ['contracts.awaiting_client', 'Awaiting Client Signature', 'dripicons-clock', 'tone-orange'],
        ['contracts.awaiting_admin', 'Awaiting Admin Signature', 'dripicons-user-id', 'tone-purple'],
        ['contracts.signed', 'Signed', 'dripicons-checkmark', 'tone-green'],
        ['contracts.templates', 'Templates', 'dripicons-copy', 'tone-teal'],
        ['contracts.clauses', 'Clause Library', 'dripicons-list', 'tone-teal'],
        ['contracts.settings', 'Settings', 'dripicons-gear', 'tone-gold'],
    ];
@endphp
<nav class="ct-nav" aria-label="Contracts">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $ctTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
