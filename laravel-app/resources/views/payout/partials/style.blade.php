<style>
    .pay-app { color: #3f2a12; }
    .pay-app a { color: #8a6230; }
    .pay-toolbar { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin: 0 0 16px; }
    .pay-new {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 16px;
        border: 1px solid #C5A059;
        border-radius: 999px;
        background: #fffaf3;
        color: #3f2a12 !important;
        font-weight: 700;
        text-decoration: none !important;
        box-shadow: none;
    }
    .pay-new.is-on, .pay-new:hover { background: #C5A059; color: #1a1208 !important; }
    .pay-balance {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding: 22px 24px;
        border-radius: 16px;
        background: linear-gradient(135deg, #1a1208 0%, #3f2a12 72%);
        color: #fff6df;
        box-shadow: 0 10px 24px rgba(63, 42, 18, .16);
    }
    .pay-kicker { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .75; }
    .pay-total { margin-top: 4px; font-size: 32px; font-weight: 750; letter-spacing: -.03em; line-height: 1.1; }
    .pay-sub { margin-top: 6px; font-size: 13px; opacity: .8; }
    .pay-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .pay-chip { border: 1px solid rgba(255,255,255,.18); border-radius: 999px; background: rgba(255,255,255,.12); padding: 6px 12px; font-size: 13px; }
    .pay-chip strong { margin-left: 6px; color: #f3e3b0; }
    .pay-card { margin-bottom: 16px; overflow: hidden; border: 1px solid #eadcc4; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(63, 42, 18, .04); }
    .pay-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #f3eadc; }
    .pay-card-head h2 { margin: 0; font-size: 16px; font-weight: 700; }
    .pay-card-body { padding: 16px 18px; }
    .pay-help { margin: 0 0 14px; color: #8a7560; }
    .pay-table { width: 100%; margin: 0; border-collapse: collapse; }
    .pay-table th { padding: 10px 12px; border-bottom: 1px solid #eadcc4; background: #fffaf3; color: #8a6230; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .pay-table td { padding: 12px; border-bottom: 1px solid #f6efe4; vertical-align: middle; }
    .pay-table tr:last-child td { border-bottom: 0; }
    .pay-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
    .pay-pill-ok { background: #e7f6ee; color: #146c43; }
    .pay-pill-wait { background: #fff6df; color: #8a6420; }
    .pay-pill-no { background: #fdecec; color: #9b1c1c; }
    .pay-note { margin-top: 4px; color: #9b1c1c; font-size: 12px; }
    .pay-net { display: inline-block; margin-left: 6px; border-radius: 999px; background: #fff6df; color: #8a6230; padding: 1px 8px; font-size: 12px; font-weight: 700; }
    .pay-search { position: relative; max-width: 460px; }
    .pay-app .form-control { min-height: 42px; border-color: #eadcc4; border-radius: 10px; }
    .pay-app #payoutHits, .pay-app #depositHits { margin-top: 6px; overflow: auto; border-radius: 12px; box-shadow: 0 12px 28px rgba(63, 42, 18, .12); }
    .pay-app #payoutHits .list-group-item, .pay-app #depositHits .list-group-item { border-color: #f3eadc; }
    .pay-actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
    .pay-plus { width: 44px; height: 44px; border: 1px solid #C5A059; border-radius: 22px; background: #fffaf3; color: #8a6230; font-size: 24px; line-height: 1; }
    .pay-go { min-height: 40px; padding: 0 22px; border: 0; border-radius: 999px; background: #C5A059; color: #1a1208; font-weight: 700; }
    .pay-go:hover { background: #8a6230; color: #fff6df; }
    .pay-open { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border-radius: 999px; background: #C5A059; color: #1a1208 !important; font-size: 13px; font-weight: 700; text-decoration: none !important; }
    .pay-danger { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border: 0; border-radius: 999px; background: #fdecec; color: #9b1c1c; font-size: 13px; font-weight: 700; cursor: pointer; }
    .pay-danger:hover { background: #9b1c1c; color: #fff; }
    .pay-row-actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
    .pay-linkbox { display: flex; gap: 8px; max-width: 760px; }
    .pay-linkbox input { flex: 1; }
    .pay-muted { color: #8a7560; }
    @media (max-width: 700px) {
        .pay-total { font-size: 26px; }
        .pay-card-body, .pay-card-head { padding-left: 12px; padding-right: 12px; }
        .pay-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
        .pay-tabs .btn { flex: 0 0 auto; min-height: 44px; }
        .pay-open, .pay-danger, .pay-go, .pay-new { min-height: 44px; }
        .pay-app .form-control, .pay-app input, .pay-app select, .pay-app textarea { font-size: 16px; min-height: 44px; }
        .pay-table { font-size: 13px; }
    }
</style>
