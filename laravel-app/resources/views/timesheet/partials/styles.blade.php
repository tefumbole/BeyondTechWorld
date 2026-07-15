<style>
    .ts-shell { max-width: 1100px; margin: 0 auto; }
    .ts-nav {
        display: flex; flex-wrap: wrap; gap: 4px 16px;
        border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem;
    }
    .ts-nav a {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 12px 4px 14px; color: #64748b; text-decoration: none;
        font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px;
    }
    .ts-nav a:hover { color: #0b3f90; text-decoration: none; }
    .ts-nav a.is-active { color: #0b3f90; border-bottom-color: #0b3f90; }
    .ts-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .ts-subtitle { color: #6b7280; margin: 0; }
    .ts-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem; margin-bottom: 1rem;
    }
    .ts-card-accent { border-top: 3px solid #0b3f90; }
    .ts-btn {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 10px 16px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; width: 100%;
    }
    .ts-btn:hover { background: #0a3578; color: #fff; }
    .ts-btn-sm { width: auto; padding: 8px 14px; }
    .ts-field { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 9px 12px; font-size: 14px; background: #fff; }
    .ts-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .ts-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    .ts-badge-dot {
        width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0;
    }
    .ts-activity {
        display: flex; align-items: flex-start; gap: 12px;
        border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
    }
    .ts-activity-icon {
        width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
        color: #fff; flex-shrink: 0; font-size: 15px;
    }
    .ts-cat-select-wrap { position: relative; }
    .ts-cat-select-wrap .ts-cat-dot {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        width: 10px; height: 10px; border-radius: 50%; pointer-events: none; z-index: 1;
        background: #94a3b8; display: none;
    }
    .ts-cat-select-wrap.has-color .ts-cat-dot { display: block; }
    .ts-cat-select-wrap.has-color select.ts-field { padding-left: 28px; }
    .ts-summary {
        background: #0b3f90; color: #fff; border-radius: 14px; padding: 1.35rem 1.4rem;
        position: sticky; top: 1rem;
    }
    .ts-summary .gold { color: #e8b923; font-size: 2.1rem; font-weight: 800; line-height: 1.1; }
    .ts-day-row {
        display: flex; align-items: center; flex-wrap: wrap; gap: 10px 14px;
        border-bottom: 1px solid #f1f5f9; padding: 14px 0;
    }
    .ts-day-row:last-child { border-bottom: 0; }
    .ts-day-row .day-label { color: #0f172a; min-width: 96px; }
    .ts-day-row.is-off .day-label { color: #94a3b8; }
    .ts-day-row input[type="checkbox"] {
        width: 16px; height: 16px; accent-color: #0b3f90; cursor: pointer;
    }
    .ts-day-times {
        display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
    }
    .ts-day-times .ts-field { width: auto; min-width: 120px; }
    .ts-day-hours {
        background: #eff6ff; color: #1d4ed8; border-radius: 8px; padding: 5px 11px;
        font-weight: 700; font-size: 13px; margin-left: auto; white-space: nowrap;
    }
    .ts-lunch-box {
        background: #eff6ff; border-radius: 10px; padding: 14px 16px; margin-bottom: 8px;
    }
</style>
