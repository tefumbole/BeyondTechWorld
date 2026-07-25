<style>
    .cm-shell { max-width: 1200px; margin: 0 auto; }
    .cm-nav {
        display: flex; flex-wrap: wrap; gap: 10px;
        margin: 0 0 1.5rem; padding: 0; border: 0;
    }
    .cm-nav a {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 16px; border-radius: 10px; border: 2px solid #cbd5e1;
        background: #fff; color: #64748b; text-decoration: none !important;
        font-weight: 700; font-size: 13px; line-height: 1.2; white-space: nowrap;
        transition: all .15s ease;
    }
    .cm-nav a i { font-size: 15px; }
    .cm-nav a:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,35,80,.08); text-decoration: none !important; }
    .cm-nav a.is-active { color: #fff !important; box-shadow: 0 6px 16px rgba(15,35,80,.14); }
    .cm-nav a.is-active i { color: #fff !important; }
    .cm-nav a.tone-blue { border-color: #0b3f90; color: #0b3f90; }
    .cm-nav a.tone-blue i { color: #0b3f90; }
    .cm-nav a.tone-blue.is-active, .cm-nav a.tone-blue:hover { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .cm-nav a.tone-blue:hover i { color: #fff !important; }
    .cm-nav a.tone-gold { border-color: #c6ab47; color: #8a7424; }
    .cm-nav a.tone-gold i { color: #8a7424; }
    .cm-nav a.tone-gold.is-active, .cm-nav a.tone-gold:hover { background: #c6ab47; border-color: #c6ab47; color: #10213d !important; }
    .cm-nav a.tone-gold.is-active i, .cm-nav a.tone-gold:hover i { color: #10213d !important; }
    .cm-nav a.tone-purple { border-color: #7b61ff; color: #7b61ff; }
    .cm-nav a.tone-purple i { color: #7b61ff; }
    .cm-nav a.tone-purple.is-active, .cm-nav a.tone-purple:hover { background: #7b61ff; border-color: #7b61ff; color: #fff !important; }
    .cm-nav a.tone-purple:hover i { color: #fff !important; }
    .cm-nav a.tone-teal { border-color: #0ea5a4; color: #0ea5a4; }
    .cm-nav a.tone-teal i { color: #0ea5a4; }
    .cm-nav a.tone-teal.is-active, .cm-nav a.tone-teal:hover { background: #0ea5a4; border-color: #0ea5a4; color: #fff !important; }
    .cm-nav a.tone-teal:hover i { color: #fff !important; }
    .cm-nav a.tone-orange { border-color: #f59e0b; color: #c77708; }
    .cm-nav a.tone-orange i { color: #c77708; }
    .cm-nav a.tone-orange.is-active, .cm-nav a.tone-orange:hover { background: #f59e0b; border-color: #f59e0b; color: #10213d !important; }
    .cm-nav a.tone-orange.is-active i, .cm-nav a.tone-orange:hover i { color: #10213d !important; }
    .cm-nav a.tone-green { border-color: #10b981; color: #10b981; }
    .cm-nav a.tone-green i { color: #10b981; }
    .cm-nav a.tone-green.is-active, .cm-nav a.tone-green:hover { background: #10b981; border-color: #10b981; color: #fff !important; }
    .cm-nav a.tone-green:hover i { color: #fff !important; }
    .cm-nav a.tone-red { border-color: #ef4444; color: #ef4444; }
    .cm-nav a.tone-red i { color: #ef4444; }
    .cm-nav a.tone-red.is-active, .cm-nav a.tone-red:hover { background: #ef4444; border-color: #ef4444; color: #fff !important; }
    .cm-nav a.tone-red:hover i { color: #fff !important; }
    .cm-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .cm-subtitle { color: #6b7280; margin: 0; }
    .cm-page-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem; margin-bottom: 1rem;
    }
    .cm-btn-primary {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
    }
    .cm-btn-primary:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .cm-btn-gold {
        background: #e8b923; border: 1px solid #e8b923; color: #111;
        border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .cm-btn-gold:hover { background: #d4a820; color: #111; text-decoration: none; }
    .cm-btn-outline {
        border: 1px solid #d1d5db; background: #fff; color: #374151;
        border-radius: 8px; padding: 6px 12px; font-weight: 600; font-size: 13px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
    }
    .cm-stat {
        background: #fff; border: 1px solid #eef2f7; border-radius: 12px;
        padding: 1rem 1.1rem; box-shadow: 0 1px 3px rgba(15,23,42,.05);
    }
    .cm-stat .label { font-size: 12px; color: #6b7280; font-weight: 600; margin: 0; }
    .cm-stat .value { font-size: 1.6rem; font-weight: 800; color: #111; margin: 4px 0 0; }
    .cm-stat .value.green { color: #16a34a; }
    .cm-badge {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600; background: #f1f5f9; color: #334155;
    }
    .cm-price { color: #16a34a; font-weight: 800; }
    .cm-order form { display: inline; }
    .cm-order button {
        border: 0; background: #f1f5f9; color: #64748b; border-radius: 4px;
        width: 22px; height: 22px; line-height: 1; padding: 0; margin: 1px; cursor: pointer; font-size: 10px;
    }
</style>
