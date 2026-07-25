<style>
    .an-shell { max-width: 1100px; margin: 0 auto; }

    /* Colored pill tabs (same pattern as Quotations / Job Board) */
    .an-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 1.5rem;
        padding: 0;
        border: 0;
    }
    .an-nav a {
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
    }
    .an-nav a i { font-size: 15px; }
    .an-nav a:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 35, 80, 0.08);
        text-decoration: none !important;
    }
    .an-nav a.is-active { color: #fff !important; box-shadow: 0 6px 16px rgba(15, 35, 80, 0.14); }
    .an-nav a.is-active i { color: #fff !important; }

    .an-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .an-nav a.tone-blue i { color: #0b3f90; }
    .an-nav a.tone-blue.is-active,
    .an-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .an-nav a.tone-blue:hover i { color: #fff !important; }

    .an-nav a.tone-gold { border-color: #c6ab47; color: #8a7424; }
    .an-nav a.tone-gold i { color: #8a7424; }
    .an-nav a.tone-gold.is-active,
    .an-nav a.tone-gold:hover { background: #c6ab47; border-color: #c6ab47; color: #10213d !important; }
    .an-nav a.tone-gold.is-active i,
    .an-nav a.tone-gold:hover i { color: #10213d !important; }

    .an-nav a.tone-purple { border-color: #7b61ff; color: #7b61ff; }
    .an-nav a.tone-purple i { color: #7b61ff; }
    .an-nav a.tone-purple.is-active,
    .an-nav a.tone-purple:hover { background: #7b61ff; border-color: #7b61ff; color: #fff !important; }
    .an-nav a.tone-purple:hover i { color: #fff !important; }

    .an-nav a.tone-teal { border-color: #0ea5a4; color: #0ea5a4; }
    .an-nav a.tone-teal i { color: #0ea5a4; }
    .an-nav a.tone-teal.is-active,
    .an-nav a.tone-teal:hover { background: #0ea5a4; border-color: #0ea5a4; color: #fff !important; }
    .an-nav a.tone-teal:hover i { color: #fff !important; }

    .an-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .an-nav a.tone-orange i { color: #c77708; }
    .an-nav a.tone-orange.is-active,
    .an-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .an-nav a.tone-orange.is-active i,
    .an-nav a.tone-orange:hover i { color: #10213d !important; }

    .an-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .an-nav a.tone-green i { color: #10b981; }
    .an-nav a.tone-green.is-active,
    .an-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .an-nav a.tone-green:hover i { color: #fff !important; }

    .an-nav a.tone-red { border-color: #ef4444; color: #ef4444; }
    .an-nav a.tone-red i { color: #ef4444; }
    .an-nav a.tone-red.is-active,
    .an-nav a.tone-red:hover { background: #ef4444; border-color: #ef4444; color: #fff !important; }
    .an-nav a.tone-red:hover i { color: #fff !important; }

    .an-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .an-subtitle { color: #6b7280; margin: 0; }
    .an-page-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem; margin-bottom: 1rem;
    }
    .an-btn-primary {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
    }
    .an-btn-primary:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .an-btn-outline {
        border: 1px solid #d1d5db; background: #fff; color: #374151;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
    }
    .an-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    .an-badge.sent { background: #ecfdf5; color: #047857; }
    .an-badge.scheduled { background: #eff6ff; color: #1d4ed8; }
    .an-badge.partial { background: #fff7ed; color: #c2410c; }
    .an-badge.draft { background: #f8fafc; color: #64748b; }
</style>
