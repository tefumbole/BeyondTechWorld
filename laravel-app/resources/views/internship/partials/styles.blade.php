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
    .ip-nav-count {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:18px; height:18px; padding:0 5px; border-radius:999px;
        font-size:11px; font-weight:800; line-height:1;
        background:rgba(255,255,255,.22); color:inherit;
    }
    .ip-btn-outline .ip-nav-count { background:rgba(11,63,144,.1); color:#0b3f90; }
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
    .ip-progress-wrap { width: 100%; }
    .ip-progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .ip-progress-bar-lg { height: 12px; }
    .ip-progress-bar > span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #0b3f90, #2563eb);
        border-radius: 999px;
        transition: width .25s ease;
    }
    .ip-progress-label { font-size: 12px; color: #64748b; margin-top: 4px; }
    .ip-btn-sm { padding: 5px 10px; font-size: 12px; }
    .ip-checklist { list-style: none; padding: 0; margin: 0; }
    .ip-check-item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 8px;
        background: #fff;
        transition: background .15s ease, border-color .15s ease;
    }
    .ip-check-item.is-done {
        background: #ecfdf5;
        border-color: #a7f3d0;
    }
    .ip-check-item label {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 14px;
        margin: 0;
        cursor: pointer;
        font-weight: 500;
        color: #1e293b;
    }
    .ip-check-item input[type="checkbox"] {
        margin-top: 3px;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }
    .ip-check-num {
        flex-shrink: 0;
        min-width: 1.6rem;
        height: 1.6rem;
        border-radius: 999px;
        background: #0b3f90;
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .ip-check-item.is-done .ip-check-num { background: #047857; }
    .ip-check-text { flex: 1; line-height: 1.45; }
    .ip-check-item.is-done .ip-check-text { text-decoration: line-through; color: #64748b; }
    .ip-hub-card {
        display: block;
        height: 100%;
        text-decoration: none !important;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.35rem 1.25rem 1.2rem;
        box-shadow: 0 1px 3px rgba(15,23,42,.06);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        color: inherit;
    }
    .ip-hub-card:hover {
        border-color: #0b3f90;
        box-shadow: 0 8px 24px rgba(11,63,144,.12);
        transform: translateY(-2px);
        color: inherit;
    }
    .ip-hub-icon {
        width: 48px; height: 48px; border-radius: 12px;
        background: #eef4ff; color: #0b3f90;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem; margin-bottom: .85rem;
    }
    .ip-hub-card h3 {
        margin: 0 0 .35rem; color: #0b3f90; font-weight: 800; font-size: 1.2rem;
    }
    .ip-hub-card p {
        margin: 0 0 .85rem; color: #64748b; font-size: 13px; line-height: 1.45; min-height: 2.6em;
    }
    .ip-hub-stat { color: #0b3f90; font-size: 13px; }
    .ip-hub-teal { border-top: 4px solid #14b8a6; }
    .ip-hub-teal .ip-hub-icon { background: #ccfbf1; color: #0f766e; }
    .ip-hub-amber { border-top: 4px solid #f59e0b; }
    .ip-hub-amber .ip-hub-icon { background: #fef3c7; color: #b45309; }
    .ip-hub-violet { border-top: 4px solid #8b5cf6; }
    .ip-hub-violet .ip-hub-icon { background: #ede9fe; color: #6d28d9; }
    .ip-stat-tile {
        border-radius: 14px;
        padding: 1.1rem 1.2rem;
        color: #fff;
        min-height: 96px;
        box-shadow: 0 8px 20px rgba(15,23,42,.08);
    }
    .ip-stat-tile .ip-meta { color: rgba(255,255,255,.85); margin-bottom: .25rem; }
    .ip-stat-tile strong { font-size: 2rem; line-height: 1.1; display: block; }
    .ip-stat-blue { background: linear-gradient(135deg, #0b3f90, #2563eb); }
    .ip-stat-green { background: linear-gradient(135deg, #047857, #22c55e); }
    .ip-stat-orange { background: linear-gradient(135deg, #c2410c, #f59e0b); }
    .ip-chart-title { color: #0b3f90; font-weight: 800; font-size: 1rem; margin: 0 0 .75rem; }
    .ip-chart-box { position: relative; height: 240px; }
</style>
