<style>
.wa-shell, .wa-shell * { letter-spacing: normal !important; }
.wa-shell { max-width: 1280px; }
.wa-title { font-size: 1.4rem; margin: 0 0 4px; }
.wa-sub { color: #6c757d; margin-bottom: 1rem; }
.wa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.wa-stat { font-size: 1.6rem; font-weight: 700; margin: 0; }
.wa-stat-label { color: #6c757d; font-size: .85rem; }
.wa-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.wa-badge-ok { background: #d4edda; color: #155724; }
.wa-badge-warn { background: #fff3cd; color: #856404; }
.wa-badge-bad { background: #f8d7da; color: #721c24; }
.wa-badge-info { background: #e8f0fe; color: #1a56db; }
.wa-thread { max-height: 62vh; overflow-y: auto; padding: 12px; background: #f6f7f9; border-radius: 8px; }
.wa-bubble { max-width: 78%; padding: 8px 12px; border-radius: 12px; margin-bottom: 10px; }
.wa-in { background: #fff; border: 1px solid #e5e7eb; margin-right: auto; }
.wa-out { background: #dcf8c6; margin-left: auto; }
.wa-ai { background: #e8f0fe; margin-left: auto; border: 1px solid #c5d4f5; }
.wa-meta { font-size: 11px; color: #6c757d; margin-top: 4px; }
.wa-ticks-sent { color: #6c757d; }
.wa-ticks-delivered { color: #6c757d; }
.wa-ticks-read { color: #34b7f1; }
.wa-ticks-failed { color: #c0392b; }
.wa-list a { color: inherit; }
.wa-list .unread { font-weight: 700; }
.wa-note { background:#fff8e1;border:1px dashed #f0ad4e;max-width:100%; }
.wa-recent { display: flex; gap: 8px; overflow-x: auto; padding: 4px 0 12px; }
.wa-recent-item { display: flex; flex-direction: column; min-width: 180px; max-width: 220px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 8px 10px; color: #111; text-decoration: none; }
.wa-recent-item span { color: #667781; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-recent-item.is-active { background: #d9fdd3; border-color: #25d366; }
.wa-thread { background: #efeae2 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Cpath fill='%23d9d2c5' fill-opacity='.35' d='M0 0h40v40H0z'/%3E%3C/svg%3E"); border-radius: 12px; padding: 12px; }
.wa-inbox { display: grid; grid-template-columns: 280px 1fr 260px; gap: 12px; align-items: start; }
@media (max-width: 991px) {
    .wa-inbox { grid-template-columns: 1fr; }
    .wa-inbox-side, .wa-inbox-contact { display: none; }
}
</style>
