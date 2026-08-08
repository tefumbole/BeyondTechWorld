<style>
    .apply-shell { background:
        radial-gradient(1200px 400px at 10% -10%, rgba(11,63,144,.12), transparent 55%),
        radial-gradient(900px 320px at 90% 0%, rgba(198,171,71,.14), transparent 50%),
        #f4f7fb; }
    .apply-panel {
        background: #fff; border: 1px solid rgba(15,23,42,.06);
        border-radius: 1.25rem; box-shadow: 0 10px 40px rgba(15,23,42,.05);
    }
    .apply-prog {
        display:block; width:100%; text-align:left; cursor:pointer;
        border: 1.5px solid #e2e8f0; border-radius: 1rem; padding: .9rem 1rem;
        background: #fff; transition: border-color .15s, background .15s, box-shadow .15s, transform .15s;
    }
    .apply-prog:hover { border-color: #0b3f90; transform: translateY(-1px); }
    .apply-prog.is-on { border-color: #0b3f90; background: #eef4ff; box-shadow: 0 0 0 3px rgba(11,63,144,.12); }
    .apply-prog input { position:absolute; opacity:0; pointer-events:none; }
    .apply-field {
        width: 100%; margin-top: .35rem; border: 1.5px solid #e2e8f0; border-radius: .9rem;
        padding: .75rem 1rem; background: #fff; outline: none; font-size: .95rem;
        transition: border-color .15s, box-shadow .15s;
    }
    .apply-field:focus { border-color: #0b3f90; box-shadow: 0 0 0 4px rgba(11,63,144,.12); }
    .apply-avail {
        display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem;
    }
    .apply-avail label {
        cursor: pointer; border: 1.5px solid #e2e8f0; background: #fff; color: #334155;
        border-radius: 999px; padding: .55rem 1rem; font-size: .85rem; font-weight: 700;
        transition: all .15s ease; user-select: none;
    }
    .apply-avail label:hover { border-color: #0b3f90; color: #0b3f90; }
    .apply-avail label.is-on {
        background: #0b3f90; border-color: #0b3f90; color: #fff;
        box-shadow: 0 6px 16px rgba(11,63,144,.18);
    }
    .apply-avail input { position: absolute; opacity: 0; pointer-events: none; }
    .apply-days {
        display: flex; align-items: center; gap: .5rem; margin-top: .5rem;
        max-width: 280px; border: 1.5px solid #e2e8f0; border-radius: .9rem;
        background: #fff; padding: .35rem; transition: border-color .15s, box-shadow .15s;
    }
    .apply-days:focus-within { border-color: #0b3f90; box-shadow: 0 0 0 4px rgba(11,63,144,.12); }
    .apply-days button {
        width: 2.4rem; height: 2.4rem; border: 0; border-radius: .7rem;
        background: #eef4ff; color: #0b3f90; font-size: 1.25rem; font-weight: 800;
        line-height: 1; cursor: pointer;
    }
    .apply-days button:hover { background: #0b3f90; color: #fff; }
    .apply-days input {
        flex: 1; min-width: 0; border: 0; text-align: center; font-weight: 800;
        font-size: 1.05rem; color: #0b3f90; outline: none; background: transparent;
        -moz-appearance: textfield;
    }
    .apply-days input::-webkit-outer-spin-button,
    .apply-days input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    /* Country + local number: avoid width:100% flex collapse on iOS Safari */
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
        padding: .75rem .5rem;
        background: #fff;
        outline: none;
        font-size: .95rem;
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
    .apply-phone-local:focus {
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
        padding: .75rem 1rem;
        background: #fff;
        outline: none;
        font-size: 1rem;
        -webkit-appearance: none;
        appearance: none;
    }
</style>
