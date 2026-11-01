<style>
    .events-shell {
        --ev-navy: #0b3f90;
        --ev-ink: #0f172a;
        --ev-muted: #64748b;
        --ev-line: #e2e8f0;
        --ev-soft: #f4f7fb;
    }
    .events-shell .ev-kicker {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--ev-navy);
        margin-bottom: 4px;
    }
    .events-shell .ev-page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .events-shell .ev-page-head h4 {
        font-weight: 800;
        letter-spacing: -.03em;
        color: var(--ev-ink);
        margin: 0;
    }
    .events-shell .ev-page-head p,
    .events-shell .ev-lead {
        color: var(--ev-muted);
        margin: 4px 0 0;
    }
    .events-shell .card,
    .events-shell .ev-card {
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
        background: #fff;
        overflow: hidden;
    }
    .events-shell .table { margin-bottom: 0; }
    .events-shell .table thead th {
        background: #0b3f90;
        color: #fff;
        border: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .events-shell .table td { vertical-align: middle; border-color: #eef2f7; }
    .events-shell .table-hover tbody tr:hover { background: #f8fbff; }
    .events-shell .form-control,
    .events-shell .btn {
        border-radius: 12px;
    }
    .events-shell .form-control {
        border-color: var(--ev-line);
        min-height: 44px;
    }
    .events-shell .form-control-sm { min-height: 36px; border-radius: 10px; }
    .events-shell .form-control:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 4px rgba(11, 63, 144, .12);
    }
    .events-shell .btn-primary {
        background: linear-gradient(135deg, #0b3f90, #1d4ed8);
        border: 0;
        font-weight: 700;
    }
    .events-shell .ev-combo { position: relative; }
    .events-shell .ev-combo-menu {
        position: absolute;
        z-index: 40;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        max-height: 280px;
        overflow: auto;
        background: #fff;
        border: 1px solid var(--ev-line);
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
        padding: 6px;
    }
    .events-shell .ev-combo-item {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        text-align: left;
        border: 0;
        background: transparent;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
    }
    .events-shell .ev-combo-item strong { display: block; color: var(--ev-ink); font-size: 14px; }
    .events-shell .ev-combo-item small { color: var(--ev-muted); }
    .events-shell .ev-combo-item:hover,
    .events-shell .ev-combo-item.is-active { background: #eff6ff; }
    .events-shell .ev-role-pill,
    .events-shell .ev-source-pill {
        font-size: 11px;
        font-weight: 700;
        border-radius: 999px;
        padding: 3px 8px;
        white-space: nowrap;
    }
    .events-shell .ev-role-pill { color: #1d4ed8; background: #dbeafe; }
    .events-shell .ev-source-pill { color: #475569; background: #f1f5f9; }
    .ev-duration-note {
        background: #eff6ff;
        color: #1e3a8a;
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 700;
        margin: 0 0 14px;
    }
    .ev-role { position: relative; }
    .ev-role-btn {
        width: 100%;
        min-height: 36px;
        text-align: left;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 6px 32px 6px 12px;
        color: #0f172a;
        font-weight: 600;
        position: relative;
    }
    .ev-role-btn:after {
        content: "";
        position: absolute;
        right: 12px;
        top: 50%;
        width: 8px;
        height: 8px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-70%) rotate(45deg);
    }
    .ev-role-btn.is-empty { color: #94a3b8; font-weight: 500; }
    .ev-role-btn.is-invalid { border-color: #dc3545; box-shadow: 0 0 0 3px rgba(220, 53, 69, .12); }
    .ev-role-menu {
        position: fixed;
        z-index: 80;
        min-width: 240px;
        max-height: 280px;
        overflow: auto;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
        padding: 6px;
    }
    .ev-role-option {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        border: 0;
        background: transparent;
        border-radius: 10px;
        padding: 10px 12px;
        text-align: left;
        cursor: pointer;
        color: #0f172a;
    }
    .ev-role-option strong { font-size: 14px; }
    .ev-role-option span { color: #1d4ed8; font-weight: 700; font-size: 13px; white-space: nowrap; }
    .ev-role-option:hover,
    .ev-role-option.is-current { background: #eff6ff; }
    .ev-cal { background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); overflow: hidden; }
    .ev-cal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        background: linear-gradient(135deg, #0b3f90, #1456b8);
        color: #fff;
    }
    .ev-cal-toolbar h3 { margin: 0; font-weight: 800; letter-spacing: -.03em; font-size: 22px; }
    .ev-cal-nav {
        width: 40px; height: 40px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.16); color: #fff; font-size: 22px; text-decoration: none;
    }
    .ev-cal-nav:hover { background: rgba(255,255,255,.28); color: #fff; text-decoration: none; }
    .ev-cal-scroll { overflow-x: auto; }
    .ev-cal-grid { min-width: 760px; }
    .ev-cal-weekdays, .ev-cal-days { display: grid; grid-template-columns: repeat(7, 1fr); }
    .ev-cal-weekdays span {
        padding: 10px 8px; font-size: 11px; font-weight: 800; letter-spacing: .08em;
        text-transform: uppercase; color: #64748b; background: #f8fafc;
    }
    .ev-cal-week { border-top: 1px solid #eef2f7; }
    .ev-cal-day { min-height: 36px; padding: 8px 8px 0; }
    .ev-cal-day .num { font-weight: 700; color: #0f172a; font-size: 13px; }
    .ev-cal-day.is-out .num { color: #cbd5e1; }
    .ev-cal-day.is-today .num {
        background: #0b3f90; color: #fff; border-radius: 999px; padding: 2px 8px;
    }
    .ev-cal-bars { position: relative; margin: 4px 4px 8px; }
    .ev-bar {
        position: absolute; height: 24px; border-radius: 8px; color: #fff;
        font-size: 12px; font-weight: 700; line-height: 24px; padding: 0 8px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none;
    }
    .ev-bar:hover { color: #fff; text-decoration: none; filter: brightness(1.08); }
    .ev-bar.is-continued-before { border-top-left-radius: 2px; border-bottom-left-radius: 2px; }
    .ev-bar.is-continued-after { border-top-right-radius: 2px; border-bottom-right-radius: 2px; }
    .ev-create-hero {
        display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .ev-create-hero h2 { margin: 0; font-weight: 800; letter-spacing: -.04em; color: #0f172a; }
    .ev-section {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 20px;
        padding: 22px; margin-bottom: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, .04);
    }
    .ev-section h3 {
        margin: 0 0 14px; font-size: 13px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: #0b3f90;
    }
    .ev-crew-empty { color: #94a3b8; padding: 14px 4px; }
    .ev-rate-note { display: block; font-size: 11px; color: #64748b; margin-top: 4px; }
    .ev-drop {
        border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 18px; background: #f8fafc; text-align: center;
    }
    .ev-toggle { display: flex; align-items: center; gap: 10px; margin-top: 14px; font-weight: 600; }
    .ev-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .ev-new-person { background: #f8fafc; border-radius: 16px; padding: 14px; margin-bottom: 14px; }
    @media (max-width: 767px) {
        .ev-section { padding: 16px; }
        .ev-cal-toolbar h3 { font-size: 18px; }
    }
</style>
