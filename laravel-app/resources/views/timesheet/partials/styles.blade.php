<style>
    .ts-shell { max-width: 1100px; margin: 0 auto; }

    /* Rental-module style colored tabs */
    .ts-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.5rem;
        padding: 0;
        border: 0;
    }
    .ts-nav a {
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
    .ts-nav a i {
        font-size: 15px;
    }
    .ts-nav a:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 35, 80, 0.08);
        text-decoration: none !important;
    }
    .ts-nav a.is-active {
        color: #fff !important;
        box-shadow: 0 6px 16px rgba(15, 35, 80, 0.14);
    }
    .ts-nav a.is-active i { color: #fff !important; }

    .ts-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .ts-nav a.tone-blue i { color: #0b3f90; }
    .ts-nav a.tone-blue.is-active,
    .ts-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .ts-nav a.tone-blue:hover i { color: #fff !important; }

    .ts-nav a.tone-gold { border-color: #c6ab47; color: #8a7424; }
    .ts-nav a.tone-gold i { color: #8a7424; }
    .ts-nav a.tone-gold.is-active,
    .ts-nav a.tone-gold:hover { background: #c6ab47; border-color: #c6ab47; color: #10213d !important; }
    .ts-nav a.tone-gold:hover i,
    .ts-nav a.tone-gold.is-active i { color: #10213d !important; }

    .ts-nav a.tone-purple { border-color: #7b61ff; color: #7b61ff; }
    .ts-nav a.tone-purple i { color: #7b61ff; }
    .ts-nav a.tone-purple.is-active,
    .ts-nav a.tone-purple:hover { background: #7b61ff; border-color: #7b61ff; color: #fff !important; }
    .ts-nav a.tone-purple:hover i { color: #fff !important; }

    .ts-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .ts-nav a.tone-green i { color: #10b981; }
    .ts-nav a.tone-green.is-active,
    .ts-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .ts-nav a.tone-green:hover i { color: #fff !important; }

    .ts-nav a.tone-teal { border-color: #0ea5a4; color: #0ea5a4; }
    .ts-nav a.tone-teal i { color: #0ea5a4; }
    .ts-nav a.tone-teal.is-active,
    .ts-nav a.tone-teal:hover { background: #0ea5a4; border-color: #0ea5a4; color: #fff !important; }
    .ts-nav a.tone-teal:hover i { color: #fff !important; }

    .ts-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .ts-nav a.tone-orange i { color: #c77708; }
    .ts-nav a.tone-orange.is-active,
    .ts-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .ts-nav a.tone-orange:hover i,
    .ts-nav a.tone-orange.is-active i { color: #10213d !important; }

    .ts-nav a.tone-pink { border-color: #e91e8c; color: #e91e8c; }
    .ts-nav a.tone-pink i { color: #e91e8c; }
    .ts-nav a.tone-pink.is-active,
    .ts-nav a.tone-pink:hover { background: #e91e8c; border-color: #e91e8c; color: #fff !important; }
    .ts-nav a.tone-pink:hover i { color: #fff !important; }

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
    .ts-hours-hint {
        margin-top: 8px;
        font-size: 13px;
        line-height: 1.4;
        font-weight: 600;
        min-height: 1.2em;
    }
    .ts-hours-hint.is-remain { color: #b45309; }
    .ts-hours-hint.is-ot { color: #b91c1c; }
    .ts-hours-hint.is-ok { color: #047857; }
    .ts-week-bar {
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 1.25rem;
        font-size: 14px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px 16px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1e3a8a;
    }
    .ts-week-bar.is-met { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .ts-week-bar.is-ot { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
    .ts-week-bar.is-short { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
</style>
