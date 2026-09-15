<style>
    .wm-shell { width:100%; max-width:none; }
    .wm-title { color:#0b3f90; font-weight:800; font-size:1.4rem; margin:0 0 6px; }
    .wm-card { background:#fff; border:1px solid #eef2f7; border-radius:12px; box-shadow:0 1px 3px rgba(15,23,42,.06); padding:.85rem 1rem; margin-bottom:.75rem; }
    .wm-nav { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 .75rem; }
    .wm-nav a { display:inline-flex; align-items:center; gap:4px; padding:5px 9px; border-radius:7px; border:1.5px solid #cbd5e1; background:#fff; color:#64748b; font-weight:700; font-size:12px; text-decoration:none; }
    .wm-nav a.is-active, .wm-nav a:hover { background:#0b3f90; border-color:#0b3f90; color:#fff; text-decoration:none; }
    .wm-btn { display:inline-flex; align-items:center; gap:5px; border-radius:7px; padding:6px 11px; font-weight:600; font-size:13px; border:1px solid #0b3f90; background:#0b3f90; color:#fff; cursor:pointer; text-decoration:none; }
    .wm-btn:hover { color:#fff; background:#0a3578; text-decoration:none; }
    .wm-btn-out { background:#fff; color:#0b3f90; }
    .wm-btn-out:hover { background:#eef4ff; color:#0b3f90; }
    .wm-field { width:100%; border:1px solid #d7deea; border-radius:7px; padding:6px 10px; font-size:13px; }
    .wm-label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
    .wm-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; }
    .wm-kpi { background:#fff; border:1px solid #eef2f7; border-radius:12px; padding:12px 14px; }
    .wm-kpi .lbl { color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; }
    .wm-kpi .val { color:#0b3f90; font-size:1.25rem; font-weight:800; margin-top:2px; }
    .wm-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; }
    .wm-badge-ops { background:#dbeafe; color:#1d4ed8; }
    .wm-badge-inv { background:#fef3c7; color:#92400e; }
    .wm-badge-cha { background:#dcfce7; color:#166534; }
    .wm-badge-none { background:#f1f5f9; color:#64748b; }
    .wm-health { display:flex; flex-wrap:wrap; gap:16px; align-items:center; }
    .wm-score { font-size:2.2rem; font-weight:800; color:#0b3f90; line-height:1; }
    .wm-table { width:100%; font-size:13px; }
    .wm-table th { white-space:nowrap; color:#64748b; font-size:12px; }
    .wm-table td, .wm-table th { padding:7px 8px; vertical-align:middle; }
    .wm-warn-CRITICAL { color:#b91c1c; }
    .wm-warn-WARNING { color:#c2410c; }
    .wm-warn-INFO { color:#1d4ed8; }
    .wm-status-EXCELLENT,.wm-status-VERY_GOOD { color:#047857; }
    .wm-status-FAIR { color:#b45309; }
    .wm-status-NEEDS_ATTENTION,.wm-status-CRITICAL { color:#b91c1c; }
    .wm-chart-title { font-size:14px; font-weight:700; color:#0b3f90; margin:0 0 10px; }
    .wm-gauge { position:relative; height:220px; }
    .wm-gauge-center {
        position:absolute; left:0; right:0; top:42px; bottom:28px;
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        pointer-events:none; text-align:center;
    }
    .wm-gauge-center strong { font-size:1.8rem; color:#0b3f90; line-height:1; }
    .wm-gauge-center span { font-size:12px; color:#64748b; font-weight:700; margin-top:4px; }

</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
