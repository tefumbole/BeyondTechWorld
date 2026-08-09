<style>
    .ip-shell { max-width: 1100px; }
    .ip-title { color:#0b3f90; font-weight:800; font-size:1.5rem; margin:0 0 4px; }
    .ip-card { background:#fff; border:1px solid #eef2f7; border-radius:14px; box-shadow:0 1px 3px rgba(15,23,42,.06); padding:1.25rem; margin-bottom:1rem; }
    .ip-btn { display:inline-flex; align-items:center; gap:6px; border-radius:8px; padding:8px 14px; font-weight:600; font-size:14px; text-decoration:none; border:1px solid #0b3f90; background:#0b3f90; color:#fff; }
    .ip-btn:hover { color:#fff; background:#0a3578; text-decoration:none; }
    .ip-btn-outline { background:#fff; color:#0b3f90; }
    .ip-btn-outline:hover { background:#eef4ff; color:#0b3f90; }
    .ip-pending { border:2px solid #0b3f90; background:linear-gradient(180deg,#f0f6ff,#fff); }
    .ip-meta { color:#6b7280; font-size:13px; }
    .ip-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:#f1f5f9; color:#334155; }
    .ip-badge.active { background:#ecfdf5; color:#047857; }
    .ip-badge.warn { background:#fff7ed; color:#c2410c; }
    .ip-badge.blue { background:#eff6ff; color:#1d4ed8; }
    .ip-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:1rem; }
    .ip-table { width:100%; }
    .ip-table th { font-size:12px; text-transform:uppercase; color:#64748b; }
    .ip-ol { padding-left:1.2rem; }
    .ip-ol li { margin-bottom:6px; }
    .ip-day {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: .65rem;
        background: #fff;
        overflow: hidden;
    }
    .ip-day > summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .65rem 1rem;
        padding: .85rem 1rem;
        font-weight: 600;
        color: #0b3f90;
        user-select: none;
    }
    .ip-day > summary::-webkit-details-marker { display: none; }
    .ip-day[open] > summary { background: #eef4ff; border-bottom: 1px solid #dbe7ff; }
    .ip-day-num {
        flex-shrink: 0;
        min-width: 4.5rem;
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #fff;
        background: #0b3f90;
        border-radius: 999px;
        padding: .25rem .65rem;
        text-align: center;
    }
    .ip-day-title { flex: 1 1 220px; font-size: .95rem; }
    .ip-day-body { padding: 1rem 1.1rem 1.15rem; background: #fafbfc; }
    .ip-study-note {
        white-space: pre-wrap;
        line-height: 1.55;
        font-size: 14px;
        color: #1e293b;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: .9rem 1rem;
        margin: 0 0 1rem;
        max-height: 32rem;
        overflow: auto;
    }
</style>
