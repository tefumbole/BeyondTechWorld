<style>
    .pay-app { color: #1f2a44; }
    .pay-app a { color: #0b3f90; }
    .pay-toolbar { display: flex; justify-content: flex-end; margin: 0 0 16px; }
    .pay-new {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 16px;
        border: 1px solid #c9d6ee;
        border-radius: 999px;
        background: #fff;
        color: #0b3f90 !important;
        font-weight: 700;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(11, 63, 144, .06);
    }
    .pay-new.is-on, .pay-new:hover { background: #0b3f90; color: #fff !important; }
    .pay-balance {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding: 22px 24px;
        border-radius: 16px;
        background: linear-gradient(135deg, #0b3f90 0%, #072f6b 72%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(11, 63, 144, .16);
    }
    .pay-kicker { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .75; }
    .pay-total { margin-top: 4px; font-size: 32px; font-weight: 750; letter-spacing: -.03em; line-height: 1.1; }
    .pay-sub { margin-top: 6px; font-size: 13px; opacity: .8; }
    .pay-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .pay-chip { border: 1px solid rgba(255,255,255,.18); border-radius: 999px; background: rgba(255,255,255,.12); padding: 6px 12px; font-size: 13px; }
    .pay-chip strong { margin-left: 6px; color: #f3e3b0; }
    .pay-card { margin-bottom: 16px; overflow: hidden; border: 1px solid #e3e9f4; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(16, 33, 61, .04); }
    .pay-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #eef2f8; }
    .pay-card-head h2 { margin: 0; font-size: 16px; font-weight: 700; }
    .pay-card-body { padding: 16px 18px; }
    .pay-help { margin: 0 0 14px; color: #6f7b91; }
    .pay-table { width: 100%; margin: 0; border-collapse: collapse; }
    .pay-table th { padding: 10px 12px; border-bottom: 1px solid #eef2f8; background: #f8fafc; color: #6f7b91; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .pay-table td { padding: 12px; border-bottom: 1px solid #f1f4f9; vertical-align: middle; }
    .pay-table tr:last-child td { border-bottom: 0; }
    .pay-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
    .pay-pill-ok { background: #e7f6ee; color: #146c43; }
    .pay-pill-wait { background: #fff6df; color: #8a6420; }
    .pay-pill-no { background: #fdecec; color: #9b1c1c; }
    .pay-note { margin-top: 4px; color: #9b1c1c; font-size: 12px; }
    .pay-net { display: inline-block; margin-left: 6px; border-radius: 999px; background: #e7eef8; color: #0b3f90; padding: 1px 8px; font-size: 12px; font-weight: 700; }
    .pay-search { position: relative; max-width: 460px; }
    .pay-app .form-control { min-height: 42px; border-color: #d5deee; border-radius: 10px; }
    .pay-app #payoutHits { margin-top: 6px; overflow: auto; border-radius: 12px; box-shadow: 0 12px 28px rgba(16, 33, 61, .12); }
    .pay-app #payoutHits .list-group-item { border-color: #eef2f8; }
    .pay-actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
    .pay-plus { width: 44px; height: 44px; border: 1px solid #d5deee; border-radius: 22px; background: #fff; color: #0b3f90; font-size: 24px; line-height: 1; }
    .pay-go { min-height: 40px; padding: 0 22px; border: 0; border-radius: 999px; background: #0b3f90; color: #fff; font-weight: 700; }
    .pay-go:hover { background: #072f6b; color: #fff; }
    .pay-open { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border-radius: 999px; background: #0b3f90; color: #fff !important; font-size: 13px; font-weight: 700; text-decoration: none !important; }
    .pay-linkbox { display: flex; gap: 8px; max-width: 760px; }
    .pay-linkbox input { flex: 1; }
    .pay-muted { color: #6f7b91; }
    @media (max-width: 700px) {
        .pay-total { font-size: 26px; }
        .pay-card-body, .pay-card-head { padding-left: 12px; padding-right: 12px; }
    }
</style>
