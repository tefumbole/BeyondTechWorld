<style>
    .jb-shell { max-width: 1100px; margin: 0 auto; }

    /* Rental-module style colored tabs */
    .jb-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.5rem;
        padding: 0;
        border: 0;
    }
    .jb-nav a {
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
    .jb-nav a i { font-size: 15px; }
    .jb-nav a:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 35, 80, 0.08);
        text-decoration: none !important;
    }
    .jb-nav a.is-active { color: #fff !important; box-shadow: 0 6px 16px rgba(15, 35, 80, 0.14); }
    .jb-nav a.is-active i { color: #fff !important; }

    .jb-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .jb-nav a.tone-blue i { color: #0b3f90; }
    .jb-nav a.tone-blue.is-active,
    .jb-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .jb-nav a.tone-blue:hover i { color: #fff !important; }

    .jb-nav a.tone-gold { border-color: #c6ab47; color: #8a7424; }
    .jb-nav a.tone-gold i { color: #8a7424; }
    .jb-nav a.tone-gold.is-active,
    .jb-nav a.tone-gold:hover { background: #c6ab47; border-color: #c6ab47; color: #10213d !important; }
    .jb-nav a.tone-gold.is-active i,
    .jb-nav a.tone-gold:hover i { color: #10213d !important; }

    .jb-nav a.tone-purple { border-color: #7b61ff; color: #7b61ff; }
    .jb-nav a.tone-purple i { color: #7b61ff; }
    .jb-nav a.tone-purple.is-active,
    .jb-nav a.tone-purple:hover { background: #7b61ff; border-color: #7b61ff; color: #fff !important; }
    .jb-nav a.tone-purple:hover i { color: #fff !important; }

    .jb-nav a.tone-pink { border-color: #e91e8c; color: #e91e8c; }
    .jb-nav a.tone-pink i { color: #e91e8c; }
    .jb-nav a.tone-pink.is-active,
    .jb-nav a.tone-pink:hover { background: #e91e8c; border-color: #e91e8c; color: #fff !important; }
    .jb-nav a.tone-pink:hover i { color: #fff !important; }

    .jb-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .jb-nav a.tone-green i { color: #10b981; }
    .jb-nav a.tone-green.is-active,
    .jb-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .jb-nav a.tone-green:hover i { color: #fff !important; }

    .jb-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .jb-nav a.tone-orange i { color: #c77708; }
    .jb-nav a.tone-orange.is-active,
    .jb-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .jb-nav a.tone-orange.is-active i,
    .jb-nav a.tone-orange:hover i { color: #10213d !important; }

    .jb-nav a.tone-teal { border-color: #0ea5a4; color: #0ea5a4; }
    .jb-nav a.tone-teal i { color: #0ea5a4; }
    .jb-nav a.tone-teal.is-active,
    .jb-nav a.tone-teal:hover { background: #0ea5a4; border-color: #0ea5a4; color: #fff !important; }
    .jb-nav a.tone-teal:hover i { color: #fff !important; }

    .jb-nav a.tone-red { border-color: #ef4444; color: #ef4444; }
    .jb-nav a.tone-red i { color: #ef4444; }
    .jb-nav a.tone-red.is-active,
    .jb-nav a.tone-red:hover { background: #ef4444; border-color: #ef4444; color: #fff !important; }
    .jb-nav a.tone-red:hover i { color: #fff !important; }

    .jb-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .jb-subtitle { color: #6b7280; margin: 0; }
    .jb-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem; margin-bottom: 1rem;
    }
    .jb-btn {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
    }
    .jb-btn:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .jb-btn-secondary {
        background: #fff; border: 1px solid #0b3f90; color: #0b3f90;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none;
    }
    .jb-field { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
    .jb-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .jb-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    tr.jb-row-click { cursor: pointer; }
    tr.jb-row-click:hover { background: #f8fafc; }
    .jb-doc-thumb {
        max-width: 100%; max-height: 320px; border-radius: 10px; border: 1px solid #e5e7eb;
        background: #f8fafc;
    }
    .jb-sig-box {
        background: #fff; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px;
        display: inline-block; max-width: 100%;
    }
    .jb-sig-box img { max-width: 360px; max-height: 140px; }
</style>
@php
    $jbTab = $jbTab ?? '';
    $tabs = [
        ['jobs.index', 'Job Postings', 'dripicons-briefcase', 'tone-blue'],
        ['jobs.create', 'Add Job', 'dripicons-plus', 'tone-gold'],
        ['jobs.createInternship', 'Add Internship', 'dripicons-user', 'tone-purple'],
        ['jobs.applications', 'All Applications', 'dripicons-user-group', 'tone-teal'],
        ['jobs.awaiting', 'Awaiting Approval', 'dripicons-clock', 'tone-orange'],
        ['jobs.selected', 'Selected', 'dripicons-checkmark', 'tone-green'],
        ['jobs.rejected', 'Rejected', 'dripicons-wrong', 'tone-red'],
    ];
@endphp
<nav class="jb-nav" aria-label="Job Board">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $jbTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
