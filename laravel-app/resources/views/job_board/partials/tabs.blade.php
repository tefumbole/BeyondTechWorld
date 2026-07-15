<style>
    .jb-shell { max-width: 1100px; margin: 0 auto; }
    .jb-nav {
        display: flex; flex-wrap: wrap; gap: 4px 16px;
        border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem;
    }
    .jb-nav a {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 12px 4px 14px; color: #64748b; text-decoration: none;
        font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px;
    }
    .jb-nav a:hover { color: #0b3f90; text-decoration: none; }
    .jb-nav a.is-active { color: #0b3f90; border-bottom-color: #0b3f90; }
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
    .jb-field { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
    .jb-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .jb-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
</style>
@php
    $jbTab = $jbTab ?? '';
    $tabs = [
        ['jobs.index', 'Job Postings', 'dripicons-briefcase'],
        ['jobs.create', 'Add Job', 'dripicons-plus'],
        ['jobs.applications', 'Applications', 'dripicons-user-group'],
    ];
@endphp
<nav class="jb-nav" aria-label="Job Board">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $jbTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>
