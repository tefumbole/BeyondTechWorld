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
        background: linear-gradient(135deg, #0b3f90, #1d4ed8);
        color: #fff;
        border-radius: 16px;
        padding: 14px 18px;
        font-weight: 700;
        margin: 14px 0;
        letter-spacing: -.01em;
    }
    .ev-when-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .ev-when-card {
        display: flex; flex-direction: column; gap: 8px; margin: 0;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px;
    }
    .ev-when-card span { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #0b3f90; }
    .ev-when-card input,
    .ev-when-card textarea {
        width: 100%; border: 0; background: #fff; border-radius: 14px;
        padding: 14px 16px; font-size: 16px; font-weight: 700; color: #0f172a;
        box-shadow: inset 0 0 0 1px #e2e8f0;
    }
    .ev-when-card textarea { font-weight: 500; resize: vertical; min-height: 110px; }
    .ev-date-block {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 20px;
        padding: 16px; margin-bottom: 14px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    }
    .ev-date-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .ev-date-head strong { display: block; font-size: 18px; letter-spacing: -.03em; color: #0f172a; }
    .ev-date-head p { margin: 2px 0 0; color: #64748b; font-size: 13px; }
    .ev-date-head button {
        border: 0; background: #0b3f90; color: #fff; border-radius: 999px;
        padding: 10px 16px; font-weight: 700;
    }
    .ev-date-notes .form-row {
        background: #f8fafc; border-radius: 14px; margin: 8px 0 0; padding: 10px 12px;
    }
    .crew-table { min-width: 1280px; }
    .crew-table th:first-child,
    .crew-table td:first-child { min-width: 320px; width: 340px; }
    .crew-table .crew-name {
        min-width: 300px; width: 100%; font-weight: 700; font-size: 15px;
    }
    .crew-table td:nth-child(2) { min-width: 190px; }
    @media (max-width: 767px) {
        .ev-when-grid { grid-template-columns: 1fr; }
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
    .ev-cal-legend {
        display: flex; flex-wrap: wrap; gap: 14px; padding: 12px 18px;
        background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        font-size: 13px; font-weight: 700; color: #0f172a;
    }
    .ev-cal-legend span { display: inline-flex; align-items: center; gap: 8px; }
    .ev-cal-legend i { width: 18px; height: 10px; border-radius: 999px; display: inline-block; }
    .ev-cal-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
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
        margin-bottom: 18px; padding: 22px 24px; border-radius: 24px;
        background: linear-gradient(135deg, #072f6b, #0b3f90 58%, #1d4ed8);
        box-shadow: 0 16px 40px rgba(11, 63, 144, .22);
    }
    .ev-create-hero .ev-kicker { color: #f3e3a4; }
    .ev-create-hero h2 { margin: 0; font-weight: 800; letter-spacing: -.04em; color: #fff; font-size: 1.8rem; }
    .ev-create-hero .ev-lead { color: rgba(255,255,255,.84); max-width: 640px; }
    .ev-create-hero .btn { border-radius: 999px; font-weight: 800; color: #0b3f90; }
    .ev-section {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 20px;
        padding: 22px; margin-bottom: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, .04);
    }
    .ev-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .ev-section-head h3 { margin: 0; }
    .ev-plus {
        width: 44px; height: 44px; border: 0; border-radius: 14px;
        background: #0b3f90; color: #fff; font-size: 28px; font-weight: 500; line-height: 1;
        box-shadow: 0 8px 18px rgba(11, 63, 144, .22);
    }
    .ev-plus.is-on { background: #072f6b; }
    .ev-comment {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;
        padding: 14px; margin-bottom: 14px;
    }
    .ev-comment label {
        display: block; margin-bottom: 8px; font-size: 12px; font-weight: 800;
        letter-spacing: .08em; text-transform: uppercase; color: #0b3f90;
    }
    .ev-comment textarea {
        width: 100%; border: 0; background: #fff; border-radius: 12px;
        padding: 12px 14px; min-height: 96px; font-size: 16px; color: #0f172a;
        box-shadow: inset 0 0 0 1px #e2e8f0;
    }
    .ev-when-wide { margin-top: 14px; }
    .ev-section h3 {
        margin: 0 0 14px; font-size: 13px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: #0b3f90;
    }
    .ev-section .ev-section-head h3 { margin: 0; }
    .ev-crew-empty { color: #94a3b8; padding: 14px 4px; }
    .ev-offer {
        display: inline-flex; align-items: center; border-radius: 999px;
        padding: 4px 10px; font-size: 12px; font-weight: 800; letter-spacing: .02em;
    }
    .ev-offer.is-yes { background: #dcfce7; color: #166534; }
    .ev-offer.is-wait { background: #fef3c7; color: #92400e; }
    .ev-offer.is-no { background: #fee2e2; color: #991b1b; }
    .ev-offer-filters { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 16px 0; }
    .ev-offer-filters a {
        border-radius: 999px; padding: 8px 14px; font-weight: 800; font-size: 13px;
        background: #f1f5f9; color: #0b3f90; text-decoration: none;
    }
    .ev-offer-filters a.is-on { background: #0b3f90; color: #fff; }
    .ev-labour {
        display: inline-flex; align-items: center; gap: 8px;
        background: #eff6ff; color: #0b3f90; border-radius: 999px;
        padding: 8px 14px; font-weight: 700;
    }
    .ev-day-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
        padding: 16px; margin-bottom: 12px; box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    }
    .crew-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .crew-tools { white-space: nowrap; }
    .crew-table input[type="checkbox"] { width: 22px; height: 22px; }
    .ev-rate-note { display: block; font-size: 11px; color: #64748b; margin-top: 4px; }
    .ev-drop {
        border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 18px; background: #f8fafc; text-align: center;
    }
    .ev-toggle { display: flex; align-items: center; gap: 10px; margin-top: 14px; font-weight: 600; }
    .ev-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .ev-new-person { background: #f8fafc; border-radius: 16px; padding: 14px; margin-bottom: 14px; }
    .ev-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .ev-chip {
        border: 0; background: #eff6ff; color: #0b3f90; border-radius: 999px;
        padding: 4px 10px; font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .ev-popcal {
        position: fixed; z-index: 4000; width: 300px; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 16px; padding: 12px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, .18);
    }
    .ev-popcal[hidden] { display: none; }
    .ev-popcal-head { display: flex; align-items: center; justify-content: space-between; font-weight: 800; margin-bottom: 8px; }
    .ev-popcal-head button { border: 0; background: #f1f5f9; border-radius: 8px; width: 32px; height: 32px; font-weight: 800; }
    .ev-popcal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
    .ev-popcal-grid span { font-size: 10px; font-weight: 800; color: #94a3b8; padding: 4px 0; }
    .ev-popcal-grid button { border: 0; background: #f8fafc; border-radius: 8px; padding: 7px 0; font-weight: 700; font-size: 13px; }
    .ev-popcal-grid button.is-on { background: #0b3f90; color: #fff; }
    .ev-popcal-grid button.is-anchor { box-shadow: inset 0 0 0 2px #c6ab47; }
    .ev-popcal-grid button.is-event { box-shadow: inset 0 0 0 1px #93c5fd; }
    .ev-popcal-grid button.is-out { color: #cbd5e1; background: transparent; }
    @media (max-width: 767px) {
        .events-shell { padding-bottom: 24px; }
        .ev-section { padding: 16px; }
        .ev-create-hero { padding: 16px; align-items: stretch; }
        .ev-create-hero h2 { font-size: 1.45rem; }
        .ev-create-hero .btn { width: 100%; }
        .ev-page-head { align-items: stretch; }
        .ev-page-head .btn { width: 100%; }
        .ev-date-head { align-items: stretch; }
        .ev-date-head button { width: 100%; min-height: 44px; }
        .events-shell .form-inline { display: flex; flex-direction: column; align-items: stretch; }
        .events-shell .form-inline .form-control,
        .events-shell .form-inline select,
        .events-shell .form-inline .btn { width: 100%; margin-right: 0 !important; min-height: 44px; font-size: 16px; }
        .events-shell .table-responsive { margin: 0 -4px; }
        .crew-scroll { overflow: visible; }
        .crew-table { min-width: 0; width: 100%; }
        .crew-table thead { display: none; }
        .crew-table,
        .crew-table tbody,
        .crew-table tr,
        .crew-table td { display: block; width: 100%; min-width: 0 !important; }
        .crew-table tr {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
            padding: 12px 14px; margin-bottom: 12px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .04);
        }
        .crew-table td { border: 0; padding: 8px 0; }
        .crew-table td:before {
            content: attr(data-label);
            display: block; margin-bottom: 4px;
            font-size: 11px; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase; color: #0b3f90;
        }
        .crew-table .crew-name { min-width: 0; width: 100%; font-size: 16px; }
        .crew-tools { display: flex; flex-wrap: wrap; gap: 8px; white-space: normal; }
        .crew-tools .btn { flex: 1 1 120px; }
        .ev-popcal { width: min(340px, calc(100vw - 16px)); }
        .ev-popcal-grid button { min-height: 40px; font-size: 15px; }
        .ev-cal-toolbar { flex-direction: column; align-items: stretch; }
        .ev-cal-toolbar h3 { font-size: 18px; }
        .ev-actions {
            position: sticky; bottom: 0; z-index: 20;
            background: #fff; margin: 0 -16px; padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
            border-top: 1px solid #e2e8f0; box-shadow: 0 -8px 24px rgba(15, 23, 42, .08);
        }
        .ev-actions .btn { width: 100%; }
        .ev-board { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 380px) {
        .ev-section { padding: 12px; }
        .events-shell .ev-page-head h4 { font-size: 1.15rem; }
    }
</style>
