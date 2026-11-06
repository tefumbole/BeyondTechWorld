<style>
    .pay-app { color: #1f2a44; }
    .pay-app a { color: #0b3f90; }
    .pay-toolbar { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin: 0 0 16px; }
    .pay-new {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 16px;
        border: 1px solid #c6ab47;
        border-radius: 999px;
        background: #f3f6fb;
        color: #0b3f90 !important;
        font-weight: 700;
        text-decoration: none !important;
        box-shadow: none;
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
        background: linear-gradient(135deg, #072f6b 0%, #0b3f90 72%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(7, 47, 107, .16);
    }
    .pay-kicker { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; opacity: .75; }
    .pay-total { margin-top: 4px; font-size: 32px; font-weight: 750; letter-spacing: -.03em; line-height: 1.1; }
    .pay-sub { margin-top: 6px; font-size: 13px; opacity: .8; }
    .pay-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .pay-chip { border: 1px solid rgba(255,255,255,.18); border-radius: 999px; background: rgba(255,255,255,.12); padding: 6px 12px; font-size: 13px; }
    .pay-chip strong { margin-left: 6px; color: #c6ab47; }
    .pay-card { margin-bottom: 16px; overflow: hidden; border: 1px solid #d5deee; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(11, 63, 144, .04); }
    .pay-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e8eef8; }
    .pay-card-head h2 { margin: 0; font-size: 16px; font-weight: 700; }
    .pay-card-body { padding: 16px 18px; }
    .pay-help { margin: 0 0 14px; color: #6f7b91; }
    .pay-table { width: 100%; margin: 0; border-collapse: collapse; }
    .pay-table th { padding: 10px 12px; border-bottom: 1px solid #d5deee; background: #f3f6fb; color: #0b3f90; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .pay-table td { padding: 12px; border-bottom: 1px solid #e8eef8; vertical-align: middle; }
    .pay-table tr:last-child td { border-bottom: 0; }
    .pay-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
    .pay-pill-ok { background: #e7f6ee; color: #146c43; }
    .pay-pill-wait { background: #e8eef8; color: #0b3f90; }
    .pay-pill-no { background: #fdecec; color: #9b1c1c; }
    .pay-note { margin-top: 4px; color: #9b1c1c; font-size: 12px; }
    .pay-net { display: inline-block; margin-left: 6px; border-radius: 999px; background: #e8eef8; color: #0b3f90; padding: 1px 8px; font-size: 12px; font-weight: 700; }
    .pay-search { position: relative; max-width: 460px; }
    .pay-app .form-control { min-height: 42px; border-color: #d5deee; border-radius: 10px; }
    .pay-app #payoutHits, .pay-app #depositHits { margin-top: 6px; overflow: auto; border-radius: 12px; box-shadow: 0 12px 28px rgba(11, 63, 144, .12); }
    .pay-app #payoutHits .list-group-item, .pay-app #depositHits .list-group-item { border-color: #e8eef8; }
    .pay-actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
    .pay-plus { width: 44px; height: 44px; border: 1px solid #c6ab47; border-radius: 22px; background: #f3f6fb; color: #0b3f90; font-size: 24px; line-height: 1; }
    .pay-go { min-height: 48px; padding: 0 22px; border: 0; border-radius: 999px; background: #c6ab47; color: #072f6b; font-weight: 800; }
    .pay-app .pay-card { border-radius: 18px; }
    .pay-app .table thead th, .pay-app table.dataTable thead th { background: #f3f6fb; color: #0b3f90; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; border-bottom: 1px solid #d5deee; }
    .pay-app .btn-success, .pay-app .add-payment { border: 0; border-radius: 999px; background: #146c43; }
    .pay-app .btn-info, .pay-app .btn-warning { border: 0; border-radius: 999px; }
    .pay-go:hover { background: #072f6b; color: #fff; }
    .pay-open { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border-radius: 999px; background: #c6ab47; color: #072f6b !important; font-size: 13px; font-weight: 700; text-decoration: none !important; }
    .pay-danger { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border: 0; border-radius: 999px; background: #fdecec; color: #9b1c1c; font-size: 13px; font-weight: 700; cursor: pointer; }
    .pay-danger:hover { background: #9b1c1c; color: #fff; }
    .pay-row-actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
    .pay-linkbox { display: flex; gap: 8px; max-width: 760px; }
    .pay-linkbox input { flex: 1; }
    .pay-muted { color: #6f7b91; }
    .pay-modal { position: fixed; z-index: 4000; top: 0; right: 0; bottom: 0; left: 0; display: flex; align-items: flex-start; justify-content: center; padding: 24px 16px; overflow: auto; background: rgba(7, 47, 107, .45); }
    .pay-modal[hidden] { display: none; }
    .pay-modal-card { width: min(920px, 100%); margin-top: 8px; border-radius: 16px; }
    .pay-modal-close { border: 0; background: transparent; color: #fff; font-weight: 700; cursor: pointer; }
    .pay-modal .pay-card-head { background: #0b3f90; color: #fff; border-bottom: 0; }
    .pay-modal .pay-card-head h2, .pay-modal .pay-card-head a { color: #fff; }
    .pay-amount { max-width: 140px; }
    @media (max-width: 700px) {
        .pay-total { font-size: 26px; }
        .pay-balance { padding: 16px; }
        .pay-card-body, .pay-card-head { padding-left: 12px; padding-right: 12px; }
        .pay-card-head { flex-direction: column; align-items: stretch; }
        .pay-toolbar { justify-content: stretch; }
        .pay-toolbar .pay-new, .pay-card-head .pay-danger, .await-actions { width: 100%; }
        .await-actions .pay-go, .await-actions .pay-danger { flex: 1 1 140px; justify-content: center; }
        .pay-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
        .pay-tabs .btn { flex: 0 0 auto; min-height: 44px; }
        .pay-open, .pay-danger, .pay-go, .pay-new { min-height: 44px; justify-content: center; }
        .pay-row-actions { justify-content: stretch; }
        .pay-row-actions .pay-open, .pay-row-actions form, .pay-row-actions form .pay-open { width: 100%; }
        .pay-actions { flex-wrap: wrap; }
        .pay-actions .pay-go, .pay-actions .pay-open, .pay-actions .pay-danger { flex: 1 1 140px; justify-content: center; }
        .pay-app .form-control, .pay-app input, .pay-app select, .pay-app textarea { font-size: 16px; min-height: 44px; }
        .pay-app .dataTables_wrapper .dataTables_length,
        .pay-app .dataTables_wrapper .dataTables_filter { float: none; width: 100%; text-align: left; }
        .pay-app .dataTables_filter input { width: 100% !important; margin: 6px 0 0 !important; }
        .pay-app .add-payment, .pay-app .get-payment { display: flex; width: 100%; margin: 4px 0; justify-content: center; min-height: 40px; align-items: center; }
        .pay-table { font-size: 14px; }
        .pay-stack thead { display: none; }
        .pay-stack, .pay-stack tbody, .pay-stack tr, .pay-stack td { display: block; width: 100%; }
        .pay-stack tr { padding: 8px 0; border-bottom: 8px solid #e8eef8; }
        .pay-stack td { border: 0; padding: 4px 12px; }
        .pay-stack td[data-label]:not([data-label=""]) { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .pay-stack td[data-label]:not([data-label=""]):before {
            content: attr(data-label);
            flex: 0 0 72px;
            color: #0b3f90;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .pay-stack .pay-people { display: block; }
        .pay-amount, .payout-amount { max-width: none; width: 100%; }
    }
    .pay-sheet-modal .modal-dialog { max-width: 520px; margin: 16px auto; }
    .pay-sheet-modal .modal-content { border: 0; border-radius: 22px; overflow: hidden; max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px); display: flex; flex-direction: column; }
    .pay-sheet-modal .modal-header { display: block; background: #0b3f90; color: #fff; border: 0; position: relative; padding: 16px 48px 16px 18px; }
    .pay-sheet-modal .modal-title { color: #c6ab47; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; margin: 0; }
    .pay-sheet-modal .pay-sheet-who { display: block; margin-top: 4px; font-size: 22px; font-weight: 800; line-height: 1.15; }
    .pay-sheet-modal .pay-sheet-ref { color: rgba(255,255,255,.75); font-size: 13px; }
    .pay-sheet-modal .close { position: absolute; top: 12px; right: 14px; color: #fff; opacity: 1; text-shadow: none; }
    .pay-sheet-modal .modal-body { background: #f3f6fb; overflow-y: auto; }
    .pay-sheet-modal .modal-footer { display: block; border: 0; background: #fff; padding: 12px 16px 16px; }
    .pay-sheet-due { background: #fff; border-radius: 16px; padding: 14px 16px; margin-bottom: 12px; }
    .pay-sheet-due span { display: block; color: #c6ab47; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .pay-sheet-due strong { display: block; font-size: 32px; line-height: 1.1; color: #0b3f90; }
    .pay-sheet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 12px 0; }
    .pay-choice { min-height: 72px; border-radius: 16px; border: 1px solid #d5deee; background: #fff; color: #1f2a44; text-align: center; padding: 10px; }
    .pay-choice strong, .pay-choice small { display: block; }
    .pay-choice small { color: #6f7b91; font-weight: 600; }
    .pay-choice.is-on { background: #0b3f90; border-color: #0b3f90; color: #fff; }
    .pay-choice.is-on small { color: #c6ab47; }
    .pay-choice.is-wide { grid-column: 1 / -1; }
    .pay-sheet-label { display: block; margin: 8px 0 6px; color: #6f7b91; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .pay-sheet-holder { min-height: 22px; margin: 6px 0 0; font-weight: 700; color: #1f2a44; }
    .pay-sheet-left, .pay-sheet-hint { color: #6f7b91; margin: 8px 0 0; }
    .pay-sheet-submit { width: 100%; min-height: 48px; border: 0; border-radius: 999px; background: #c6ab47; color: #072f6b; font-weight: 800; }
    .pay-split { margin-top: 8px; }
    .pay-split table { width: 100%; background: #fff; border-radius: 12px; }
    .pay-split th, .pay-split td { padding: 6px; }
    .pay-legacy { display: none !important; }
</style>
