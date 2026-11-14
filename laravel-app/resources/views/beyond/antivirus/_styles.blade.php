<style>
.av-page{min-height:70vh;background:radial-gradient(1200px 480px at 10% -10%, rgba(198,171,71,.18), transparent 50%), linear-gradient(180deg,#f4f7fb 0%,#eef2f8 100%);padding:28px 0 64px;color:#1e293b}
.av-wrap{max-width:640px;margin:0 auto;padding:0 16px}
.av-kicker{letter-spacing:.16em;text-transform:uppercase;color:#0b3f90;font-size:12px;font-weight:700;margin:0 0 6px}
.av-page h1{font-size:34px;line-height:1.1;color:#0b3f90;margin:0 0 8px}
.av-lead{color:#475569;margin:0 0 18px}
.av-progress{margin:0 0 16px}
.av-progress p{margin:6px 0 0;font-size:13px;font-weight:800;color:#0b3f90}
.av-progress-bar{height:8px;border-radius:99px;background:#e2e8f0;overflow:hidden}
.av-progress-bar span{display:block;height:100%;background:linear-gradient(90deg,#1d4ed8,#0b3f90);border-radius:99px}
.av-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:0 0 16px}
.av-step{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:8px 6px;text-align:center;font-size:12px;font-weight:700;color:#64748b}
.av-step span{display:block;width:22px;height:22px;line-height:22px;border-radius:99px;margin:0 auto 4px;background:#e2e8f0;color:#334155}
.av-step.is-on{border-color:#0b3f90;color:#0b3f90}
.av-step.is-on span{background:#0b3f90;color:#fff}
.av-step.is-done{border-color:#c6ab47;color:#0b3f90}
.av-step.is-done span{background:#c6ab47;color:#0b3f90}
.av-card{background:#fff;border-radius:28px;padding:22px 18px;box-shadow:0 20px 50px rgba(11,63,144,.08)}
.av-card h2{margin:0 0 6px;font-size:22px;color:#0f172a}
.av-card p{color:#475569}
.av-field{display:block;font-weight:700;margin:14px 0 6px}
.av-input,.av-select{width:100%;min-height:52px;border:1px solid #dbe3ef;border-radius:14px;padding:12px 14px;font-size:16px;background:#fff}
.av-input:focus,.av-select:focus{outline:3px solid rgba(11,63,144,.2);border-color:#0b3f90}
.av-btn{display:block;width:100%;min-height:52px;border:0;border-radius:14px;background:#c6ab47;color:#0b3f90;font-weight:800;font-size:16px;margin-top:16px}
.av-btn.secondary{background:#fff;color:#0b3f90;border:1px solid #dbe3ef}
.av-btn.danger{background:#fff;color:#9f1239;border:1px solid #fecdd3}
.av-row{display:flex;gap:8px;flex-wrap:wrap}
.av-row .av-btn{width:auto;flex:1}
.av-choice{display:flex;align-items:center;gap:10px;border:1px solid #dbe3ef;border-radius:14px;padding:14px;margin:8px 0;min-height:52px;font-weight:700}
.av-choice:has(input:checked){border-color:#0b3f90;background:#f8fafc}
.av-qty{font-size:56px;line-height:1;font-weight:800;color:#0b3f90;margin:8px 0}
.av-note{background:#f8fafc;border-radius:14px;padding:12px 14px;margin:10px 0}
.av-request-title{font-size:18px;font-weight:800;color:#0b3f90;margin-bottom:4px}
.av-fact{display:flex;justify-content:space-between;gap:12px;align-items:baseline;padding:10px 0;border-top:1px solid #e2e8f0}
.av-fact span{color:#64748b;font-weight:700}
.av-fact strong{color:#0f172a;font-size:18px;text-align:right}
.av-after{margin:8px 0 0;color:#64748b;font-size:14px}
.av-alert{border-radius:14px;padding:12px 14px;margin:0 0 12px}
.av-alert.ok{background:#ecfdf3;color:#166534}
.av-alert.bad{background:#fff1f2;color:#9f1239}
.av-region{position:relative}
.av-region ul{list-style:none;margin:6px 0 0;padding:6px;border:1px solid #dbe3ef;border-radius:14px;max-height:220px;overflow:auto;background:#fff}
.av-region ul[hidden]{display:none}
.av-combo{position:relative}
.av-menu{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:20;background:#fff;border:1px solid #dbe3ef;border-radius:14px;box-shadow:0 16px 40px rgba(15,23,42,.12);max-height:240px;overflow:auto}
.av-menu[hidden]{display:none}
.av-menu button{display:block;width:100%;text-align:left;border:0;background:#fff;padding:14px 16px;min-height:48px;font-weight:700;color:#0f172a}
.av-menu button.is-on,.av-menu button:hover{background:#eff6ff;color:#0b3f90}
.is-hidden{display:none}
.av-file{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.av-plus{display:grid;place-items:center;width:56px;height:56px;margin:16px auto 0;border-radius:999px;border:1px solid #dbe3ef;background:#fff;color:#0b3f90;font-size:32px;font-weight:500;line-height:1}
.av-person{margin-top:16px;padding-top:8px;border-top:1px solid #e2e8f0}
.av-qr-slot{display:none}
.av-qr-slot.is-open{display:block}
.av-qr{margin-top:10px;background:#fff;border-radius:16px;padding:8px;width:fit-content;box-shadow:inset 0 0 0 1px #e2e8f0}
.av-qr svg{display:block;width:168px;height:168px}
.av-region li{padding:10px 12px;border-radius:10px;cursor:pointer}
.av-region li.is-active,.av-region li:hover{background:#eff6ff}
.av-dept-row{display:grid;grid-template-columns:1fr 96px 40px;gap:8px;align-items:center;margin-top:8px}
.av-dept-row .av-input{min-height:48px}
.av-dept-remove{width:40px;height:48px;border:1px solid #dbe3ef;border-radius:14px;background:#fff;color:#9f1239;font-size:22px;line-height:1}
.av-dept-summary{margin-top:12px;padding:12px 14px;border-radius:14px;background:#eff6ff;color:#0b3f90;font-weight:800}
.av-frame{position:relative;width:100%;height:340px;border-radius:28px;overflow:hidden;background:linear-gradient(165deg,#dbeafe 0%,#f8fbff 48%,#eef2ff 100%);display:flex;align-items:center;justify-content:center;margin:4px 0 12px;box-shadow:inset 0 0 0 1px #d5e3f5}
.av-frame-hint{color:#0b3f90;font-weight:800;text-align:center;padding:0 28px;font-size:18px}
.av-frame.has-photo .av-frame-hint{display:none}
.av-selfie{width:100%;height:100%;object-fit:cover;background:#e2e8f0;display:block;margin:0;border-radius:0}
.av-selfie[hidden]{display:none !important}
.av-sign-well{position:relative;margin-top:6px;padding:10px;border-radius:24px;background:linear-gradient(180deg,#f8fbff,#e8f1ff)}
.av-sign-hint{position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);text-align:center;color:#94a3b8;font-weight:700;pointer-events:none}
.av-faces{display:flex;gap:16px;justify-content:center;align-items:flex-start;margin:4px 0 8px}
.av-faces figure{margin:0;text-align:center}
.av-faces img{width:148px;height:148px;object-fit:cover;border-radius:18px;background:#e2e8f0;display:block}
.av-faces img.is-sign{object-fit:contain;background:#fff;border:1px solid #e2e8f0}
.av-faces figcaption{margin-top:6px;font-size:12px;font-weight:700;color:#64748b}
.av-modal{position:fixed;inset:0;background:#0f172a;display:flex;align-items:stretch;justify-content:center;z-index:40;padding:0}
.av-modal[hidden]{display:none}
.av-modal .sheet{background:#0f172a;border-radius:0;padding:16px;width:min(520px,100%);min-height:100%;display:flex;flex-direction:column;justify-content:flex-end}
.av-modal video{width:100%;flex:1;min-height:68vh;object-fit:cover;border-radius:28px;background:#020617;transform:scaleX(-1)}
.av-sign{border:0;border-radius:18px;background:#fff;box-shadow:inset 0 0 0 1px #d5e3f5;touch-action:none;width:100%;height:260px}
.av-meta{display:flex;justify-content:space-between;gap:8px;font-size:13px;color:#64748b}
.av-ref{font-size:28px;font-weight:800;color:#0b3f90;letter-spacing:.02em}
@media (max-width:640px){.av-page{padding-top:16px}.av-page h1{font-size:26px}.av-card{padding:18px 14px;border-radius:22px}.av-row{display:grid;grid-template-columns:1fr}.av-row .av-btn{width:100%}.av-qr,.av-qr svg{max-width:100%}}
</style>
