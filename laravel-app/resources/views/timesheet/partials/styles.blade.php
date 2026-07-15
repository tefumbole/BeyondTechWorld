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
    .ts-field { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 9px 12px; font-size: 14px; }
    .ts-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .ts-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    .ts-activity {
        display: flex; align-items: flex-start; gap: 12px;
        border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
    }
    .ts-activity-icon {
        width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
        background: #ede9fe; color: #7c3aed; flex-shrink: 0;
    }
    .ts-summary {
        background: #0b3f90; color: #fff; border-radius: 14px; padding: 1.25rem;
    }
    .ts-summary .gold { color: #e8b923; font-size: 2rem; font-weight: 800; }
    .ts-day-row {
        display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
        border-bottom: 1px solid #f1f5f9; padding: 12px 0;
    }
    .ts-day-hours {
        background: #eff6ff; color: #1d4ed8; border-radius: 8px; padding: 4px 10px;
        font-weight: 700; font-size: 13px; margin-left: auto;
    }
</style>
