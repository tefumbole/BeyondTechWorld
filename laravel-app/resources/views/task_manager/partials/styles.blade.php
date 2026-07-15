<style>
    .tm-shell { max-width: 1200px; margin: 0 auto; }
    .tm-nav {
        display: flex; flex-wrap: wrap; gap: 4px 18px;
        border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem; padding-bottom: 0;
    }
    .tm-nav a {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 12px 4px 14px; color: #64748b; text-decoration: none;
        font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent;
        margin-bottom: -1px; transition: color .15s, border-color .15s;
    }
    .tm-nav a:hover { color: #0b3f90; text-decoration: none; }
    .tm-nav a.is-active { color: #0b3f90; border-bottom-color: #0b3f90; }
    .tm-nav a i { font-size: 15px; }
    .tm-title { color: #0b3f90; font-weight: 800; font-size: 1.75rem; margin: 0 0 4px; }
    .tm-subtitle { color: #6b7280; margin: 0; }
    .tm-stat {
        display: block; text-decoration: none !important; color: inherit;
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); padding: 1.1rem 1.2rem;
        height: 100%; transition: box-shadow .15s, transform .15s, border-color .15s;
    }
    .tm-stat:hover {
        box-shadow: 0 8px 24px rgba(11, 63, 144, 0.12);
        border-color: #c6d6ef; transform: translateY(-1px);
    }
    .tm-stat-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .tm-stat-label { font-size: 13px; color: #6b7280; font-weight: 600; margin: 0; }
    .tm-stat-value { font-size: 2rem; font-weight: 800; color: #111827; margin: 4px 0 0; line-height: 1.1; }
    .tm-stat-icon {
        width: 48px; height: 48px; border-radius: 999px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        font-size: 20px;
    }
    .tm-stat-icon.blue { background: #dbeafe; color: #2563eb; }
    .tm-stat-icon.gray { background: #f1f5f9; color: #64748b; }
    .tm-stat-icon.yellow { background: #fef9c3; color: #ca8a04; }
    .tm-stat-icon.green { background: #dcfce7; color: #16a34a; }
    .tm-stat-icon.red { background: #fee2e2; color: #dc2626; }
    .tm-panel {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); padding: 1.25rem 1.35rem;
        height: 100%;
    }
    .tm-panel h5 {
        color: #0b3f90; font-weight: 700; font-size: 1.05rem;
        margin: 0 0 1rem; display: flex; align-items: center; gap: 8px;
    }
    .tm-chip-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 12px; color: #64748b; }
    .tm-chip-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .tm-chip-legend i {
        width: 10px; height: 10px; border-radius: 2px; display: inline-block;
    }
    .tm-btn-outline {
        border: 1px solid #d1d5db; background: #fff; color: #374151;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .tm-btn-outline:hover { background: #f8fafc; color: #0b3f90; text-decoration: none; }
    .tm-btn-primary {
        background: #0b3f90; border: 1px solid #0b3f90; color: #fff;
        border-radius: 8px; padding: 8px 14px; font-weight: 600; font-size: 14px;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .tm-btn-primary:hover { background: #0a3578; color: #fff; text-decoration: none; }
    .tm-page-card {
        background: #fff; border: 1px solid #eef2f7; border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); padding: 1.25rem;
        margin-bottom: 1rem;
    }
</style>
