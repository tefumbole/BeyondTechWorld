<style>
    .qt-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.25rem;
        padding: 0;
        border: 0;
    }
    .qt-nav a {
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
        transition: all .15s ease;
    }
    .qt-nav a:hover { transform: translateY(-1px); text-decoration: none !important; }
    .qt-nav a.is-active { color: #fff !important; }
    .qt-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .qt-nav a.tone-orange.is-active, .qt-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .qt-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .qt-nav a.tone-green.is-active, .qt-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .qt-nav a.tone-red { border-color: #ef4444; color: #ef4444; }
    .qt-nav a.tone-red.is-active, .qt-nav a.tone-red:hover { background: #ef4444; border-color: #ef4444; color: #fff !important; }
    .qt-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .qt-nav a.tone-blue.is-active, .qt-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .qt-count {
        display: inline-flex; min-width: 22px; height: 22px; padding: 0 6px;
        align-items: center; justify-content: center; border-radius: 999px;
        background: rgba(15,23,42,.08); font-size: 11px; font-weight: 800;
    }
    .qt-nav a.is-active .qt-count { background: rgba(255,255,255,.25); }
</style>
@php
    $qtTab = $tab ?? 'awaiting';
    $counts = $tabCounts ?? ['awaiting' => 0, 'approved' => 0, 'rejected' => 0, 'draft' => 0];
    $qtTabs = [
        ['awaiting', 'Awaiting Client Approval', 'dripicons-clock', 'tone-orange'],
        ['approved', 'Approved', 'dripicons-checkmark', 'tone-green'],
        ['rejected', 'Rejected', 'dripicons-wrong', 'tone-red'],
        ['draft', 'Drafts', 'dripicons-document', 'tone-blue'],
    ];
@endphp
<nav class="qt-nav" aria-label="Quotation status">
    @foreach($qtTabs as $t)
        <a href="{{ route('quotations.index', ['tab' => $t[0]]) }}" class="{{ $t[3] }} {{ $qtTab === $t[0] ? 'is-active' : '' }}">
            <i class="{{ $t[2] }}"></i> {{ $t[1] }}
            <span class="qt-count">{{ $counts[$t[0]] ?? 0 }}</span>
        </a>
    @endforeach
</nav>
