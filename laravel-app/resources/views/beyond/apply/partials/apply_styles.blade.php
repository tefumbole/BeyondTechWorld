<style>
    .apply-shell { background:
        radial-gradient(900px 280px at 10% -10%, rgba(11,63,144,.12), transparent 55%),
        radial-gradient(700px 240px at 90% 0%, rgba(198,171,71,.14), transparent 50%),
        #f4f7fb; }
    .apply-panel {
        background: #fff; border: 1px solid rgba(15,23,42,.06);
        border-radius: 1.15rem; box-shadow: 0 10px 40px rgba(15,23,42,.05);
    }
    @media (max-width: 640px) {
        .apply-panel { border-radius: 1rem; }
        .apply-shell { padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)); }
    }

    .apply-prog {
        display:block; width:100%; text-align:left; cursor:pointer;
        border: 1.5px solid #e2e8f0; border-radius: 1rem; padding: .85rem 1rem;
        background: #fff; transition: border-color .15s, background .15s, box-shadow .15s, transform .15s;
        -webkit-tap-highlight-color: transparent;
        min-height: 3.25rem;
    }
    .apply-prog:hover { border-color: #0b3f90; }
    .apply-prog:active { transform: scale(.99); }
    .apply-prog.is-on { border-color: #0b3f90; background: #eef4ff; box-shadow: 0 0 0 3px rgba(11,63,144,.12); }
    .apply-prog input { position:absolute; opacity:0; pointer-events:none; }

    .apply-field {
        width: 100%; margin-top: .35rem; border: 1.5px solid #e2e8f0; border-radius: .9rem;
        padding: .8rem 1rem; background: #fff; outline: none; font-size: 1rem;
        transition: border-color .15s, box-shadow .15s;
        -webkit-appearance: none; appearance: none;
    }
    .apply-field:focus { border-color: #0b3f90; box-shadow: 0 0 0 4px rgba(11,63,144,.12); }

    .apply-avail {
        display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .6rem;
    }
    .apply-avail label {
        cursor: pointer; border: 1.5px solid #e2e8f0; background: #fff; color: #334155;
        border-radius: 999px; padding: .65rem 1rem; font-size: .88rem; font-weight: 700;
        transition: all .15s ease; user-select: none;
        -webkit-tap-highlight-color: transparent;
        min-height: 2.75rem; display: inline-flex; align-items: center;
    }
    .apply-avail label:hover { border-color: #0b3f90; color: #0b3f90; }
    .apply-avail label.is-on {
        background: #0b3f90; border-color: #0b3f90; color: #fff;
        box-shadow: 0 6px 16px rgba(11,63,144,.18);
    }
    .apply-avail input { position: absolute; opacity: 0; pointer-events: none; }

    .apply-days {
        display: flex; align-items: center; gap: .5rem; margin-top: .5rem;
        max-width: 100%; width: 100%; border: 1.5px solid #e2e8f0; border-radius: .9rem;
        background: #fff; padding: .4rem; transition: border-color .15s, box-shadow .15s;
    }
    @media (min-width: 640px) { .apply-days { max-width: 280px; } }
    .apply-days:focus-within { border-color: #0b3f90; box-shadow: 0 0 0 4px rgba(11,63,144,.12); }
    .apply-days button {
        width: 2.75rem; height: 2.75rem; border: 0; border-radius: .7rem;
        background: #eef4ff; color: #0b3f90; font-size: 1.35rem; font-weight: 800;
        line-height: 1; cursor: pointer; flex-shrink: 0;
    }
    .apply-days button:active { background: #0b3f90; color: #fff; }
    .apply-days input {
        flex: 1; min-width: 0; border: 0; text-align: center; font-weight: 800;
        font-size: 1.1rem; color: #0b3f90; outline: none; background: transparent;
        -moz-appearance: textfield;
    }
    .apply-days input::-webkit-outer-spin-button,
    .apply-days input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .apply-phone-row {
        display: flex;
        align-items: stretch;
        gap: .5rem;
        margin-top: .35rem;
        width: 100%;
        max-width: 100%;
    }
    .apply-phone-cc {
        flex: 0 0 5.75rem;
        width: 5.75rem;
        max-width: 5.75rem;
        min-width: 5.75rem;
        margin-top: 0;
        border: 1.5px solid #e2e8f0;
        border-radius: .9rem;
        padding: .8rem .5rem;
        background-color: #fff;
        outline: none;
        font-size: 1rem;
        appearance: none;
        -webkit-appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, #0b3f90 50%),
            linear-gradient(135deg, #0b3f90 50%, transparent 50%);
        background-position: calc(100% - 14px) calc(50% - 3px), calc(100% - 9px) calc(50% - 3px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 1.4rem;
    }
    .apply-phone-cc:focus,
    .apply-phone-local:focus,
    .apply-field:focus {
        border-color: #0b3f90;
        box-shadow: 0 0 0 4px rgba(11,63,144,.12);
    }
    .apply-phone-local {
        flex: 1 1 0%;
        min-width: 0;
        width: auto;
        margin-top: 0;
        border: 1.5px solid #e2e8f0;
        border-radius: .9rem;
        padding: .8rem 1rem;
        background: #fff;
        outline: none;
        font-size: 1rem;
        -webkit-appearance: none;
        appearance: none;
    }

    .apply-step {
        scroll-margin-top: 4.5rem;
    }
    .apply-step-head {
        display: flex; align-items: center; gap: .65rem;
        margin-bottom: .85rem; padding-bottom: .65rem;
        border-bottom: 1px solid #eef2f7;
    }
    .apply-step-num {
        flex-shrink: 0; width: 1.85rem; height: 1.85rem; border-radius: 999px;
        background: #0b3f90; color: #fff; font-size: .75rem; font-weight: 800;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .apply-step-title {
        margin: 0; font-size: .95rem; font-weight: 800; color: #0b3f90;
        letter-spacing: .02em; text-transform: uppercase;
    }
    .apply-step-sub { margin: .15rem 0 0; font-size: .8rem; color: #64748b; font-weight: 500; text-transform: none; letter-spacing: 0; }

    .apply-progress {
        position: sticky; top: 0; z-index: 30;
        background: rgba(255,255,255,.92);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(15,23,42,.06);
        padding: .65rem 1rem;
    }
    .apply-progress-track {
        height: .35rem; background: #e2e8f0; border-radius: 999px; overflow: hidden;
    }
    .apply-progress-bar {
        height: 100%; width: 0; background: linear-gradient(90deg, #0b3f90, #c6ab47);
        border-radius: 999px; transition: width .25s ease;
    }
    .apply-progress-meta {
        display: flex; justify-content: space-between; gap: .5rem;
        margin-top: .4rem; font-size: .72rem; font-weight: 700; color: #64748b;
    }

    .apply-doc-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
        margin-top: .55rem;
    }
    .apply-doc-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        min-height: 2.85rem; padding: .65rem .75rem; border-radius: .75rem;
        font-size: .8rem; font-weight: 800; cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        border: 1.5px solid #0b3f90; color: #0b3f90; background: #fff;
        text-align: center;
    }
    .apply-doc-btn.primary {
        background: #0b3f90; color: #fff; border-color: #0b3f90;
    }
    .apply-doc-btn:active { transform: scale(.98); }
    .apply-doc-card {
        border: 1.5px solid #d1fae5; background: #fff; border-radius: .9rem;
        padding: .9rem; margin-top: .65rem;
    }
    .apply-doc-card.has-file { border-color: #059669; background: #ecfdf5; }

    .apply-sticky-submit {
        display: none;
    }
    @media (max-width: 640px) {
        .apply-sticky-submit {
            display: block;
            position: fixed;
            left: 0; right: 0; bottom: 0; z-index: 40;
            padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom, 0px));
            background: rgba(255,255,255,.96);
            border-top: 1px solid rgba(15,23,42,.08);
            box-shadow: 0 -8px 24px rgba(15,23,42,.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .apply-inline-submit { display: none !important; }
        .apply-form-pad { padding-left: 1rem !important; padding-right: 1rem !important; }
        #apply-signature-pad { height: 180px !important; touch-action: none; }
    }

    .apply-chip-row { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: .5rem; }
    .apply-chip {
        border: 1.5px solid #e2e8f0; background: #fff; color: #334155;
        border-radius: 999px; padding: .55rem .95rem; font-size: .85rem; font-weight: 700;
        cursor: pointer; min-height: 2.6rem;
        -webkit-tap-highlight-color: transparent;
    }
    .apply-chip.is-on {
        background: #0b3f90; border-color: #0b3f90; color: #fff;
    }

    .apply-toggle {
        display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: .45rem;
    }
    .apply-toggle label {
        display: flex; align-items: center; justify-content: center; text-align: center;
        min-height: 3rem; padding: .65rem .75rem; border-radius: .85rem;
        border: 1.5px solid #e2e8f0; background: #fff; color: #334155;
        font-size: .85rem; font-weight: 700; cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    .apply-toggle label.is-on {
        border-color: #0b3f90; background: #eef4ff; color: #0b3f90;
        box-shadow: 0 0 0 3px rgba(11,63,144,.1);
    }
    .apply-toggle input { position: absolute; opacity: 0; pointer-events: none; }

    select.apply-field {
        background-image: linear-gradient(45deg, transparent 50%, #0b3f90 50%),
            linear-gradient(135deg, #0b3f90 50%, transparent 50%);
        background-position: calc(100% - 16px) calc(50% - 3px), calc(100% - 11px) calc(50% - 3px);
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 2rem;
    }
</style>
