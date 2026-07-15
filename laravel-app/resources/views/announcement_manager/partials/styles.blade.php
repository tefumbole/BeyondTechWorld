<style>
    .an-shell { max-width: 1100px; margin: 0 auto; }
    .an-nav {
        display: flex; flex-wrap: wrap; gap: 4px 18px;
        border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem;
    }
    .an-nav a {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 12px 4px 14px; color: #64748b; text-decoration: none;
        font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent;
        margin-bottom: -1px;
    }
    .an-nav a:hover { color: #0b3f90; text-decoration: none; }
    .an-nav a.is-active { color: #0b3f90; border-bottom-color: #0b3f90; }
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
