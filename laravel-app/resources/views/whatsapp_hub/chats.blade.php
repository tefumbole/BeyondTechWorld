@extends('layout.main')

@section('content')
<style>
    #content { display: block !important; height: auto !important; max-height: none !important; overflow: visible !important; padding: 0 !important; background: var(--beyond-bg); }
    #content > .container-fluid { flex: none; }
    .main-footer { display: none !important; }
    .chats-app, .chats-app * { box-sizing: border-box; }
    .chats-app {
        --wa-green: #25d366; --wa-teal: #128c7e; --wa-ink: #0f1f3d; --wa-muted: #6b7a99; --wa-line: #e6ebf5;
        --wa-grad: linear-gradient(135deg, #0b3d91 0%, #1565c0 45%, #12a4a4 100%);
        display: flex; flex-wrap: nowrap; align-items: stretch; height: calc(100vh - 220px); min-height: 640px; color: var(--wa-ink); overflow: hidden;
        background: #fff; border-radius: 18px; margin: 10px 14px 14px; box-shadow: 0 18px 48px rgba(15, 31, 61, .12), 0 2px 6px rgba(15, 31, 61, .06);
        font-feature-settings: "tnum" 1;
    }
    .chat-rail { display: none; }
    .inbox-bar { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 12px 2px; background: #fff; flex: none; }
    .inbox-brand { min-width: 0; }
    .inbox-brand h1 { margin: 0; font-size: 28px; line-height: 1.05; font-weight: 800; letter-spacing: -.03em; color: #111b21; }
    .inbox-status { display: flex; align-items: center; gap: 6px; margin: 3px 0 0; color: #667781; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .inbox-status i { width: 8px; height: 8px; border-radius: 50%; background: #25d366; flex: none; }
    .inbox-status.is-off i { background: #e5484d; }
    .inbox-tools { display: flex; gap: 2px; flex: none; }
    .inbox-icon { width: 44px; height: 44px; border: 1.5px solid #d7e0ea; border-radius: 50%; background: #fff; color: #3b4a68; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; }
    .inbox-icon svg { width: 20px; height: 20px; }
    .more-menu, .new-chat { position: absolute; z-index: 40; top: 62px; right: 10px; width: min(240px, calc(100% - 20px)); background: #fff; border: 1px solid #e6ebf5; border-radius: 14px; box-shadow: 0 12px 32px rgba(15, 31, 61, .16); }
    .more-menu[hidden], .new-chat[hidden] { display: none !important; }
    .more-menu { display: flex; flex-direction: column; padding: 6px; }
    .more-menu button, .more-menu form { margin: 0; }
    .more-menu button { width: 100%; min-height: 44px; border: 0; border-radius: 10px; background: transparent; color: #111b21; text-align: left; padding: 0 12px; font-weight: 700; cursor: pointer; }
    .more-menu button:hover { background: #f4f7fb; }
    .new-chat { left: 10px; right: 10px; width: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
    .new-chat label { margin: 0; font-size: 12px; font-weight: 800; color: #667781; }
    .new-chat input { width: 100%; min-height: 44px; border: 1px solid #d7e0ea; border-radius: 12px; padding: 0 12px; font-size: 16px; }
    .new-chat button { min-height: 44px; border: 0; border-radius: 12px; background: #2f6bff; color: #fff; font-weight: 800; cursor: pointer; }
    .hub-gear, .chat-gear { flex: none; width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e7edf5; background: #fff; color: #1565c0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(15,31,61,.08); }
    .hub-install { width: auto; min-width: 52px; padding: 0 12px; border-radius: 999px; font-weight: 800; font-size: 13px; }
    .hub-gear svg, .chat-gear svg { width: 18px; height: 18px; }
    .reply-bar { display: flex; align-items: center; gap: 10px; margin: 0; padding: 10px 14px 0; background: rgba(255,255,255,.94); }
    .reply-pick { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 44px; padding: 0 14px; border: 0; border-radius: 16px; background: linear-gradient(135deg, #f4f7ff, #eef8f6); color: #16315c; cursor: pointer; text-align: left; box-shadow: inset 0 0 0 1px #d7e3f5; }
    .reply-pick span { font-size: 12px; font-weight: 700; color: #6b7a99; }
    .reply-pick strong { font-size: 15px; }
    .reply-pick em { font-style: normal; font-size: 11px; font-weight: 800; color: #1565c0; background: #fff; border-radius: 999px; padding: 2px 8px; }
    .lang-sheet { position: fixed; inset: 0; z-index: 80; display: none; align-items: flex-end; justify-content: center; background: rgba(10, 22, 48, .46); padding: 0; }
    .lang-sheet.is-on { display: flex; }
    .lang-card { width: min(520px, 100%); max-height: min(86vh, 720px); overflow: auto; background: #fff; border-radius: 24px 24px 0 0; padding: 16px 16px calc(16px + env(safe-area-inset-bottom)); box-shadow: 0 -16px 50px rgba(15,31,61,.2); }
    .lang-card h3 { margin: 0; font-size: 20px; }
    .lang-card header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
    .lang-card p { margin: 0 0 12px; color: #5d6d8c; font-size: 13px; }
    .lang-card label { display: block; margin: 12px 0 6px; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #6b7a99; }
    .lang-close { width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f1f4fa; color: #16315c; font-size: 22px; cursor: pointer; }
    .lang-filter { width: 100%; border: 1px solid #d7e3f5; border-radius: 14px; min-height: 44px; padding: 0 14px; background: #f7f9fd; font-size: 15px; }
    .lang-list { display: flex; flex-direction: column; gap: 6px; max-height: min(46vh, 520px); overflow: auto; margin-top: 8px; -webkit-overflow-scrolling: touch; }
    .lang-option { flex: none; width: 100%; border: 0; background: #f4f7fb; color: #16315c; border-radius: 12px; min-height: 42px; padding: 0 14px; text-align: left; font-weight: 700; cursor: pointer; }
    .lang-option.is-on { background: var(--wa-grad); color: #fff; }
    .lang-save { width: 100%; margin-top: 14px; border: 0; border-radius: 14px; min-height: 46px; background: var(--wa-grad); color: #fff; font-weight: 800; cursor: pointer; }
    .settings-link { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 52px; margin-top: 8px; padding: 0 14px; border: 0; border-radius: 14px; background: #f4f7fb; color: #16315c; font-weight: 700; text-decoration: none; cursor: pointer; text-align: left; }
    .settings-link span { color: #6b7a99; font-size: 13px; font-weight: 700; }
    .theme-swatch { width: 18px; height: 18px; border-radius: 50%; margin-right: 10px; box-shadow: inset 0 0 0 1px rgba(0,0,0,.15); }
    .chats-app.theme-black { --wa-ink: #e9edef; --wa-muted: #8696a0; --wa-line: #2a3942; background: #111b21; color: #e9edef; }
    .chats-app.theme-black .chats-side,
    .chats-app.theme-black .inbox-bar,
    .chats-app.theme-black .hub-screens,
    .chats-app.theme-black .chats-search,
    .chats-app.theme-black .chat-filters,
    .chats-app.theme-black .chats-head,
    .chats-app.theme-black .chats-compose,
    .chats-app.theme-black .reply-bar { background: #111b21; color: #e9edef; }
    .chats-app.theme-black .chats-thread { background: #0b141a; }
    .chats-app.theme-black .chat-row:hover,
    .chats-app.theme-black .chat-row.is-on { background: #202c33; }
    .chats-app.theme-black .hub-screens a,
    .chats-app.theme-black .hub-screens form.hub-signout button,
    .chats-app.theme-black .chat-filters button:not(.is-on),
    .chats-app.theme-black .hub-gear,
    .chats-app.theme-black .chat-gear { background: #202c33; color: #e9edef; border-color: #2a3942; }
    .chats-app.theme-black .chats-search input,
    .chats-app.theme-black .chats-compose textarea { background: #202c33; color: #e9edef; }
    .chats-app.theme-black .chat-name,
    .chats-app.theme-black .inbox-brand h1,
    .chats-app.theme-black .chats-head h2 { color: #e9edef; }
    .chats-app.theme-black .inbox-icon,
    .chats-app.theme-black .more-menu,
    .chats-app.theme-black .new-chat { background: #202c33; color: #e9edef; border-color: #2a3942; }
    .chats-app.theme-navy { --wa-ink: #e8eefc; --wa-muted: #b7c4e0; --wa-line: #243864; background: #0b1b3a; color: #e8eefc; }
    .chats-app.theme-navy .chats-side,
    .chats-app.theme-navy .inbox-bar,
    .chats-app.theme-navy .hub-screens,
    .chats-app.theme-navy .chats-search,
    .chats-app.theme-navy .chat-filters,
    .chats-app.theme-navy .chats-head,
    .chats-app.theme-navy .chats-compose,
    .chats-app.theme-navy .reply-bar { background: #0b1b3a; color: #e8eefc; }
    .chats-app.theme-navy .chats-thread { background: #071226; }
    .chats-app.theme-navy .chat-row:hover,
    .chats-app.theme-navy .chat-row.is-on { background: #13284f; }
    .chats-app.theme-navy .hub-screens a,
    .chats-app.theme-navy .hub-screens form.hub-signout button,
    .chats-app.theme-navy .chat-filters button:not(.is-on),
    .chats-app.theme-navy .hub-gear,
    .chats-app.theme-navy .chat-gear { background: #13284f; color: #e8eefc; border-color: #243864; }
    .chats-app.theme-navy .chats-search input,
    .chats-app.theme-navy .chats-compose textarea { background: #13284f; color: #e8eefc; }
    .chats-app.theme-navy .chat-name,
    .chats-app.theme-navy .inbox-brand h1,
    .chats-app.theme-navy .chats-head h2 { color: #e8eefc; }
    .chats-app.theme-navy .inbox-icon,
    .chats-app.theme-navy .more-menu,
    .chats-app.theme-navy .new-chat { background: #13284f; color: #e8eefc; border-color: #243864; }
    @media (min-width: 801px) {
        .lang-sheet { align-items: center; padding: 24px; }
        .lang-card { border-radius: 24px; }
    }
    .hub-label { padding: 14px 16px 4px; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--wa-muted); }
    .chats-side { position: relative; z-index: 1; width: 400px; max-width: 42vw; flex: 0 0 400px; display: flex; flex-direction: column; min-width: 0; min-height: 0; height: 100%; overflow: hidden; border-right: 1px solid var(--wa-line); background: #fff; }
    .call-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; min-height: 34px; padding: 0 14px; background: linear-gradient(135deg, #25d366, #128c7e); color: #fff !important; text-decoration: none; font-weight: 700; box-shadow: 0 4px 12px rgba(18, 140, 126, .3); }
    .chats-search { flex: none; width: 100%; padding: 10px 12px 8px; background: #fff; }
    .chats-search input { width: 100%; max-width: 100%; min-width: 0; border: 1px solid transparent; border-radius: 999px; background: #f1f4fa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2.4' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E") no-repeat 14px center; color: var(--wa-ink); min-height: 40px; padding: 0 14px 0 40px; transition: border-color .18s, box-shadow .18s, background-color .18s; }
    .chats-search input:focus { outline: none; background-color: #fff; border-color: #9cc2ff; box-shadow: 0 0 0 4px rgba(21, 101, 192, .12); }
    .chats-search input::placeholder { color: var(--wa-muted); }
    .chat-filters { flex: none; display: flex; gap: 6px; width: 100%; overflow: hidden; padding: 4px 12px 10px; background: #fff; border-bottom: 1px solid var(--wa-line); }
    .chat-filters button { flex: 1 1 0; min-width: 0; border: 0; background: #eef2f7; color: #3b4a68; border-radius: 999px; min-height: 34px; padding: 0 4px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; text-align: center; }
    .chat-filters button span { display: none; }
    .chat-filters button.is-on { background: #2f6bff; color: #fff; box-shadow: none; }
    .chats-list { overflow-x: hidden; overflow-y: auto; flex: 1 1 auto; min-height: 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 0 0 calc(28px + env(safe-area-inset-bottom)); scroll-padding-bottom: calc(28px + env(safe-area-inset-bottom)); }
    .chats-list::-webkit-scrollbar, .chats-stream::-webkit-scrollbar { width: 8px; }
    .chats-list::-webkit-scrollbar-thumb, .chats-stream::-webkit-scrollbar-thumb { background: rgba(15,31,61,.14); border-radius: 8px; }
    .chat-none { display: none; padding: 36px 16px; text-align: center; color: var(--wa-muted); }
    .chat-row { position: relative; display: flex; gap: 12px; align-items: center; width: 100%; min-width: 0; padding: 10px 14px; margin: 0; color: var(--wa-ink); text-decoration: none; border-radius: 0; border-bottom: 1px solid #f0f2f5; overflow: hidden; }
    .chat-row:hover { background: #f4f7fc; color: var(--wa-ink); text-decoration: none; }
    .chat-row.is-on { background: linear-gradient(90deg, rgba(21,101,192,.12), rgba(18,164,164,.08)); }
    .chat-row.is-on::before { content: ""; position: absolute; left: 0; top: 12px; bottom: 12px; width: 4px; border-radius: 4px; background: var(--wa-grad); }
    .chat-avatar { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 17px; color: #fff; flex: none; background: linear-gradient(135deg, #1565c0, #0b3d91); box-shadow: 0 4px 10px rgba(15,31,61,.15); }
    .chat-avatar[data-tone="0"] { background: linear-gradient(135deg, #ff7a59, #e8445a); }
    .chat-avatar[data-tone="1"] { background: linear-gradient(135deg, #7c5cff, #4f46e5); }
    .chat-avatar[data-tone="2"] { background: linear-gradient(135deg, #22c1c3, #128c7e); }
    .chat-avatar[data-tone="3"] { background: linear-gradient(135deg, #f7b733, #fc6a03); }
    .chat-avatar[data-tone="4"] { background: linear-gradient(135deg, #2bd47d, #0f9b58); }
    .chat-avatar[data-tone="5"] { background: linear-gradient(135deg, #3fa9f5, #1565c0); }
    .chat-avatar[data-tone="6"] { background: linear-gradient(135deg, #ff5fa2, #c2185b); }
    .chat-avatar[data-tone="7"] { background: linear-gradient(135deg, #8e9eab, #4b5d7a); }
    .chat-avatar.is-group { background: linear-gradient(135deg, #ffd166, #f4a261); color: #3b2300; }
    .chat-main { min-width: 0; flex: 1; }
    .chat-top, .chat-bottom { display: flex; justify-content: space-between; align-items: center; gap: 8px; min-width: 0; }
    .chat-bottom { margin-top: 2px; }
    .chat-name { font-weight: 700; font-size: 16px; color: #111b21; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1; }
    .chat-time { color: #8696a0; font-size: 12px; flex: none; margin-left: auto; }
    .chat-preview { color: #667781; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1; }
    .chat-state { display: none; }
    .chat-state { flex: none; border-radius: 999px; background: #18a957; color: #fff; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; padding: 2px 7px; }
    .chats-side.is-personal .chat-state,
    .chats-side.is-personal .chat-badge,
    .chats-side.is-personal .chat-filters button[data-filter="unread"] span { background: #d92d20; color: #fff; box-shadow: none; }
    .chats-side.is-personal .chat-row[data-read="0"] { background: #fde8e8; box-shadow: inset 4px 0 0 #d92d20; }
    .chats-side.is-personal .chat-row[data-read="0"] .chat-name,
    .chats-side.is-personal .chat-row[data-read="0"] .chat-preview { color: #7a1212; }
    .chats-side.is-personal .chat-row[data-read="0"] .chat-time { color: #d92d20; }
    .chat-row[data-read="0"] { background: #e7f8ee; box-shadow: inset 4px 0 0 #18a957; }
    .chat-row[data-read="0"] .chat-name { font-weight: 800; color: #0c2416; }
    .chat-row[data-read="0"] .chat-preview { color: #14301f; font-weight: 700; }
    .chat-row[data-read="0"] .chat-time { color: #0d8a45; font-weight: 800; }
    .chat-badge { background: #18a957; color: #fff; border-radius: 999px; min-width: 24px; height: 24px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; padding: 0 7px; flex: none; box-shadow: 0 3px 8px rgba(24, 169, 87, .45); }
    .chat-ai { display: inline-block; color: #fff; background: linear-gradient(135deg, #7c5cff, #4f46e5); font-size: 10px; font-weight: 800; border-radius: 6px; padding: 1px 6px; margin-right: 4px; letter-spacing: .3px; }
    .chats-thread { position: relative; z-index: 2; flex: 1 1 auto; min-width: 0; min-height: 0; height: 100%; overflow: hidden; display: flex; flex-direction: column;
        background-color: #eef3fb;
        background-image: radial-gradient(circle at 12px 12px, rgba(21,101,192,.07) 2px, transparent 2.5px), radial-gradient(circle at 36px 36px, rgba(18,164,164,.07) 2px, transparent 2.5px), linear-gradient(180deg, #f4f8ff 0%, #e9f1fb 100%);
        background-size: 48px 48px, 48px 48px, 100% 100%; }
    .chats-head { min-height: 66px; background: rgba(255,255,255,.92); backdrop-filter: saturate(1.4) blur(10px); -webkit-backdrop-filter: saturate(1.4) blur(10px); border-bottom: 1px solid var(--wa-line); display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 18px; }
    .chat-back { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; min-width: 44px; padding: 0; border-radius: 50%; background: transparent; color: #111b21 !important; text-decoration: none; flex: none; line-height: 1; box-shadow: none; }
    .chat-back svg { width: 22px; height: 22px; }
    .chat-back:hover { color: #111b21; text-decoration: none; background: #f4f7fb; }
    .chats-head-person { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .chats-head-person .chat-avatar { width: 42px; height: 42px; font-size: 15px; }
    .chats-head-person h2, .chats-head-person p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .load-earlier { align-self: center; border: 0; background: #fff; color: #1565c0; border-radius: 999px; min-height: 32px; padding: 0 16px; font-weight: 700; cursor: pointer; margin: 10px 0; box-shadow: 0 3px 10px rgba(15,31,61,.1); }
    .chat-live { display: none; background: linear-gradient(90deg, #fff4d6, #ffe7b3); color: #7a5b10; text-align: center; font-size: 13px; font-weight: 700; padding: 6px 10px; }
    .typing-line, .chat-preview.is-typing { color: #128c7e; font-weight: 700; }
    .typing-line { margin: 0 18px 4px; font-size: 13px; }
    .typing-line[hidden] { display: none !important; }
    .chat-live.is-on { display: block; }
    .chats-head h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--wa-ink); }
    .chats-head p { margin: 2px 0 0; color: var(--wa-muted); font-size: 12px; }
    .mode-pill { display: inline-block; border-radius: 999px; padding: 0 8px; font-size: 11px; font-weight: 800; line-height: 18px; color: #fff; background: linear-gradient(135deg, #7c5cff, #4f46e5); }
    .mode-pill.is-human { background: linear-gradient(135deg, #f7b733, #fc6a03); }
    .mode-switch { display: flex; gap: 8px; align-items: center; }
    .mode-switch form { margin: 0; }
    .mode-switch button, .announce-btn { border: 0; border-radius: 999px; min-height: 34px; padding: 0 14px; font-weight: 700; cursor: pointer; background: #eef2f9; color: #3b4a68; transition: background .18s, box-shadow .18s; }
    .mode-switch button:hover { background: #e2e9f6; }
    .mode-switch button.is-on { background: var(--wa-grad); color: #fff; box-shadow: 0 6px 16px rgba(21, 101, 192, .28); }
    .mode-switch button.is-translate.is-on { background: linear-gradient(135deg, #7c5cff, #4f46e5); box-shadow: 0 6px 16px rgba(79, 70, 229, .35); }
    .announce-btn { background: var(--wa-grad); color: #fff; text-decoration: none; display: inline-flex; align-items: center; box-shadow: 0 6px 16px rgba(21, 101, 192, .28); }
    .announce-btn:hover { color: #fff; text-decoration: none; }
    .chats-stream { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 12px 7% 18px; display: flex; flex-direction: column; gap: 4px; }
    .chats-head, .chats-compose, .chat-note, .chat-live { flex: none; }
    .bubble { position: relative; flex: none; align-self: flex-start; max-width: min(680px, 78%); margin: 0; padding: 7px 32px 5px 11px; border-radius: 4px 16px 16px 16px; line-height: 1.35; white-space: pre-wrap; word-break: break-word; color: var(--wa-ink); font-size: 14px; box-shadow: 0 1px 2px rgba(15,31,61,.08), 0 2px 8px rgba(15,31,61,.05); animation: bubbleIn .18s ease-out; }
    .bubble .aside { display: block; margin-top: 4px; font-size: 11px; line-height: 1.3; opacity: .72; }
    .bubble.in { background: #fff; }
    .bubble.out { align-self: flex-end; background: #e4e7ee; color: #5c6573; border-radius: 16px 4px 16px 16px; margin-left: auto; }
    .bubble.out .aside, .bubble.out time { color: #8b93a3; }
    @keyframes bubbleIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .bubble time { float: right; margin: 8px 0 0 10px; color: #7c8aa5; font-size: 11px; line-height: 1; white-space: nowrap; }
    .bubble .tick { margin-left: 6px; font-weight: 800; letter-spacing: 0; }
    .bubble .tick.sent, .bubble .tick.queued, .bubble .tick.delivered { color: #5c6b84; }
    .bubble .tick.read, .bubble .tick.played { color: #fff; background: #0b86d0; border-radius: 999px; padding: 2px 6px; }
    .bubble .tick.failed { color: #e5484d; }
    .bubble img.chat-photo { display: block; max-width: 280px; width: 100%; border-radius: 10px; margin-bottom: 5px; }
    .bubble audio, .bubble video { display: block; max-width: 280px; width: 100%; margin-bottom: 5px; border-radius: 10px; }
    .bubble a.chat-file, .bubble a.chat-link { color: #0b3f90; font-weight: 700; text-decoration: underline; word-break: break-all; }
    .bubble .quote { display: block; margin: 0 0 4px; padding: 4px 8px; border-left: 3px solid #c6ab47; border-radius: 8px; background: rgba(11, 63, 144, .06); color: #3b4a68; font-size: 12px; }
    .bubble .forwarded, .bubble .edited { font-size: 11px; font-weight: 800; color: #8b93a3; }
    .bubble .forwarded { display: block; margin-bottom: 2px; }
    .bubble.is-deleted { font-style: italic; color: #8b93a3; }
    .bubble.is-star span.star { color: #c6ab47; }
    .quote-bar { display: flex; align-items: center; gap: 8px; margin: 0 14px; padding: 8px 12px; border-left: 3px solid #c6ab47; border-radius: 12px; background: #f4f7ff; color: #16315c; font-size: 13px; font-weight: 700; }
    .quote-bar[hidden] { display: none !important; }
    .quote-bar span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .quote-bar button { border: 0; background: transparent; color: #6b7a99; font-size: 20px; cursor: pointer; }
    .msg-menu { position: fixed; z-index: 90; display: none; width: 220px; padding: 6px 0; background: #202c33; color: #e9edef; border-radius: 14px; box-shadow: 0 12px 32px rgba(0, 0, 0, .38); }
    .msg-menu.is-on { display: block; }
    .msg-menu .msg-actions { display: flex; flex-direction: column; gap: 0; }
    .msg-menu .msg-actions[hidden], .msg-menu .msg-edit[hidden], .msg-menu .msg-react[hidden], .msg-menu #msgDelete[hidden], .msg-menu #msgInfo[hidden], .msg-menu button[hidden] { display: none !important; }
    .msg-menu button { display: block; width: 100%; min-height: 40px; border: 0; background: transparent; color: #e9edef; text-align: left; padding: 8px 16px; font-size: 15px; font-weight: 600; cursor: pointer; }
    .msg-menu button:hover { background: rgba(255, 255, 255, .06); }
    .msg-menu button.danger { color: #ff8a8a; }
    .msg-menu hr { width: 100%; border: 0; border-top: 1px solid rgba(255, 255, 255, .12); margin: 4px 0; }
    .msg-menu .msg-react { display: flex; flex-wrap: wrap; gap: 2px; padding: 4px 8px; }
    .msg-menu .msg-react button { width: auto; min-height: 36px; padding: 4px 6px; font-size: 22px; }
    .msg-menu .msg-edit, .msg-menu #msgInfo { display: flex; flex-direction: column; gap: 8px; padding: 8px 12px 12px; }
    .msg-menu textarea { width: 100%; min-height: 72px; border: 0; border-radius: 10px; padding: 8px; background: #111b21; color: #e9edef; }
    .msg-menu #msgInfoText { margin: 0; font-size: 13px; line-height: 1.4; }
    .bubble.is-picked { outline: 2px solid #25d366; }
    .bubble .react { margin-right: 4px; }
    #pinnedBar { display: flex; flex-direction: column; gap: 4px; padding: 8px 12px 0; }
    #pinnedBar[hidden] { display: none !important; }
    #pinnedBar button { text-align: left; border: 0; border-radius: 10px; background: #fff8e6; color: #072f6b; font-weight: 700; padding: 8px 10px; cursor: pointer; }
    #selectBar { position: fixed; z-index: 70; left: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: 8px; padding: 12px 14px calc(12px + env(safe-area-inset-bottom)); background: #111b21; color: #fff; }
    #selectBar[hidden] { display: none !important; }
    #selectBar button { border: 0; border-radius: 999px; min-height: 38px; padding: 0 14px; font-weight: 800; cursor: pointer; }
    #selectDelete { background: #fde8e8; color: #9b1c1c; }
    #selectCancel { background: #2a3942; color: #fff; }
    .msg-edit textarea, #transcribePrompt { width: 100%; min-height: 90px; border: 1px solid #d5deee; border-radius: 14px; padding: 10px; font: inherit; }
    #transcribeResult { white-space: pre-wrap; margin-top: 12px; padding: 12px 14px; border-radius: 16px; border: 1px solid #ead89a; border-left: 4px solid #c6ab47; background: #fffdf6; color: #16315c; }
    #transcribeResult[hidden], #transcribeUse[hidden] { display: none !important; }
    #transcribeUse { min-height: 44px; width: 100%; margin-top: 8px; border: 0; border-radius: 14px; background: #0b3f90; color: #fff; font-weight: 800; cursor: pointer; }
    #transcribeAsk { min-height: 44px; border: 0; border-radius: 14px; color: #fff; font-weight: 800; cursor: pointer; }
    .ask-card { display: flex; flex-direction: column; overflow: hidden; min-height: min(70vh, 560px); padding: 0; background: linear-gradient(180deg, #f4f7fd 0%, #fff 140px); }
    .ask-card > header { align-items: center; padding: 14px 16px 8px; margin: 0; }
    .ask-brand { display: flex; align-items: center; gap: 10px; }
    .ask-mark, .mai-open { position: relative; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 14px; background: linear-gradient(145deg, #0b3f90, #072f6b); color: #fff; font-size: 11px; font-weight: 800; letter-spacing: .04em; box-shadow: 0 8px 18px rgba(11, 63, 144, .28); }
    .ask-mark { width: 40px; height: 40px; flex: none; }
    .ask-mark i, .mai-open i { position: absolute; top: 6px; right: 6px; width: 7px; height: 7px; border-radius: 50%; background: #c6ab47; }
    .ask-scope { margin: 1px 0 0; color: #6b7a99; font-size: 12px; font-weight: 700; }
    .ask-chips { display: flex; gap: 8px; flex: none; overflow-x: auto; padding: 4px 16px 10px; background: linear-gradient(180deg, #f4f7fd, #fff); border-bottom: 1px solid #e7eef8; }
    .ask-chips button { flex: none; border: 0; border-radius: 999px; min-height: 34px; padding: 0 14px; background: #fff; color: #0b3f90; font-weight: 800; cursor: pointer; box-shadow: 0 1px 2px rgba(15, 31, 61, .06), inset 0 0 0 1px #d7e2f4; }
    .ask-thread { flex: 1 1 auto; min-height: 0; overflow: auto; padding: 12px 16px; }
    .ask-note { margin: 0 0 10px; color: #6b7a99; font-size: 12px; font-weight: 700; }
    .ask-preview { display: flex; flex-direction: column; gap: 8px; }
    .ask-empty { margin: 0; color: #6b7a99; font-size: 13px; }
    .ask-line { max-width: 88%; padding: 8px 12px; }
    .ask-line span { display: block; margin-bottom: 2px; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .ask-line p { margin: 0; font-size: 14px; line-height: 1.35; }
    .ask-line img.ask-photo { display: block; width: 100%; max-width: 280px; margin-top: 6px; border-radius: 10px; background: #fff; }
    .ask-line.is-them { align-self: flex-start; background: #fff; color: #16315c; border: 1px solid #e3ebf7; border-radius: 6px 16px 16px 16px; }
    .ask-line.is-them span { color: #0b3f90; }
    .ask-line.is-mine { align-self: flex-end; background: linear-gradient(145deg, #0b3f90, #124eaa); color: #fff; border-radius: 16px 6px 16px 16px; }
    .ask-line.is-mine span { color: #c6ab47; }
    .ask-dock { flex: none; padding: 10px 12px calc(12px + env(safe-area-inset-bottom)); background: #fff; box-shadow: 0 -8px 24px rgba(15, 31, 61, .05); }
    .ask-file { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; padding: 8px 10px; border-radius: 14px; background: #fff8e6; color: #0b3f90; font-size: 13px; font-weight: 800; box-shadow: inset 0 0 0 1px #c6ab47; }
    .ask-file[hidden] { display: none !important; }
    .ask-file img { width: 72px; height: 72px; object-fit: cover; border-radius: 10px; background: #fff; flex: none; }
    .ask-file img[hidden] { display: none !important; }
    .ask-file span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ask-file button { border: 0; background: transparent; color: #0b3f90; font-size: 20px; font-weight: 800; cursor: pointer; }
    .ask-row { display: flex; align-items: flex-end; gap: 8px; padding: 8px; border-radius: 22px; background: #f4f7fd; box-shadow: inset 0 0 0 1px #e1e8f4; }
    .ask-card .ask-clip { display: flex; align-items: center; justify-content: center; width: auto; min-width: 76px; height: 40px; margin: 0; padding: 0 12px; border-radius: 14px; background: #c6ab47; color: #072f6b; font-size: 13px; font-weight: 800; letter-spacing: 0; text-transform: none; cursor: pointer; flex: none; overflow: hidden; }
    .ask-clip input, .ask-clip > div { display: none !important; }
    .ask-row #transcribePrompt { flex: 1 1 auto; width: auto; min-width: 0; min-height: 96px; max-height: 180px; margin: 0; padding: 10px 8px; border: 0; border-radius: 14px; background: #fff; resize: vertical; box-shadow: inset 0 0 0 1px #e1e8f4; }
    .ask-row #transcribeAsk { width: auto; min-width: 64px; height: 40px; margin: 0; padding: 0 14px; border-radius: 16px; background: linear-gradient(145deg, #0b3f90, #072f6b); }
    .mai-open { width: 46px; height: 46px; flex: none; cursor: pointer; }
    .msg-edit { display: flex; flex-direction: column; gap: 8px; }
    .forward-list { max-height: 320px; overflow: auto; display: flex; flex-direction: column; gap: 6px; }
    .forward-list button { text-align: left; min-height: 42px; border: 0; border-radius: 12px; background: #f3f6fb; color: #16315c; font-weight: 700; padding: 0 12px; cursor: pointer; }
    .bubble .who { display: block; font-size: 12px; font-weight: 800; color: #7c5cff; margin-bottom: 2px; }
    .bubble .choices { clear: both; display: flex; flex-direction: column; gap: 5px; margin-top: 7px; }
    .bubble .choice { display: block; background: rgba(255,255,255,.8); border: 1px solid #d8e2f2; border-radius: 10px; padding: 7px 10px; font-size: 13px; line-height: 1.3; color: #1565c0; font-weight: 600; }
    .day-chip { align-self: center; background: rgba(255,255,255,.92); color: #4b5d7a; border-radius: 999px; font-size: 12px; font-weight: 700; padding: 4px 14px; margin: 12px 0 6px; box-shadow: 0 2px 8px rgba(15,31,61,.08); }
    .clip { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; border: 0; background: #eef2f9; color: #1565c0; font-size: 24px; font-weight: 600; cursor: pointer; flex: none; overflow: hidden; position: relative; transition: background .18s, transform .18s; }
    .clip:hover { background: #e2e9f6; transform: rotate(90deg); }
    .clip input { display: none; }
    .paste-preview { width: 44px; height: 44px; object-fit: cover; border-radius: 12px; flex: none; border: 1px solid var(--wa-line); }
    .file-name { align-self: center; color: #0b3f90; font-size: 12px; font-weight: 700; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .file-name:not(:empty) { padding: 4px 10px; border: 1px solid #c6ab47; border-radius: 999px; background: #f3f6fb; }
    .picked { display: inline-flex; align-items: center; gap: 4px; flex: none; max-width: 240px; }
    .picked[hidden] { display: none !important; }
    .file-clear { width: 22px; height: 22px; border: 0; border-radius: 50%; background: #9b1c1c; color: #fff; font-size: 14px; font-weight: 800; line-height: 1; cursor: pointer; flex: none; }
    .bubble { position: relative; }
    .bubble-more { position: absolute; top: 4px; right: 4px; z-index: 2; width: 26px; height: 26px; border: 0; border-radius: 8px; background: rgba(255,255,255,.9); color: #16315c; font-size: 18px; font-weight: 900; line-height: 1; cursor: pointer; box-shadow: 0 1px 3px rgba(15,31,61,.15); }
    .chats-compose { display: flex; align-items: flex-end; flex-wrap: nowrap; gap: 8px; width: 100%; max-width: 100%; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.94); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-top: 1px solid var(--wa-line); position: relative; }
    .chats-compose textarea { flex: 1 1 auto; min-width: 0; order: 0; border: 1px solid transparent; border-radius: 18px; background: #f1f4fa; color: var(--wa-ink); min-height: 44px; max-height: 120px; padding: 12px 14px; resize: none; font-size: 16px; line-height: 1.4; transition: border-color .18s, box-shadow .18s, background-color .18s; }
    .chats-compose textarea:focus { outline: none; background: #fff; border-color: #9cc2ff; box-shadow: 0 0 0 4px rgba(21, 101, 192, .12); }
    .chats-compose button[type="submit"] { border: 0; border-radius: 50%; width: 46px; height: 46px; background: linear-gradient(135deg, #25d366, #128c7e); color: #fff; font-size: 18px; font-weight: 800; cursor: pointer; flex: none; box-shadow: 0 6px 16px rgba(18, 140, 126, .35); transition: transform .15s, box-shadow .15s; }
    .chats-compose button[type="submit"]:hover { transform: scale(1.06); box-shadow: 0 8px 20px rgba(18, 140, 126, .45); }
    .chats-compose .tool { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 0; border-radius: 50%; background: #eef2f9; color: #1565c0; cursor: pointer; flex: none; }
    .chats-compose .tool.is-on { background: #e5484d; color: #fff; }
    .emoji-box { position: fixed; z-index: 40; right: 18px; bottom: 84px; width: min(360px, calc(100vw - 24px)); max-height: 280px; overflow: auto; background: #fff; border: 1px solid #d5deee; border-radius: 16px; box-shadow: 0 16px 40px rgba(15, 31, 61, .16); padding: 10px; }
    .emoji-box[hidden] { display: none; }
    .emoji-box h4 { margin: 8px 4px 4px; color: #0b3f90; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; }
    .emoji-box button { border: 0; background: transparent; font-size: 22px; line-height: 1; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; }
    .emoji-box button:hover { background: #f3f6fb; }
    .enter-choice { display: flex; align-items: center; gap: 8px; margin: 8px 0 14px; color: #1f2a44; font-weight: 700; }
    .letter-card { margin: 24px auto; width: min(640px, calc(100% - 28px)); background: #fff; border: 1px solid #d5deee; border-radius: 18px; padding: 18px; box-shadow: 0 1px 2px rgba(11, 63, 144, .04); }
    .letter-card h2 { margin: 0 0 6px; color: #072f6b; }
    .letter-card p { margin: 0 0 14px; color: #6f7b91; }
    .letter-card label { display: block; margin: 12px 0 6px; color: #0b3f90; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .letter-card input, .letter-card textarea, .letter-card select { width: 100%; border: 1px solid #d5deee; border-radius: 12px; min-height: 42px; padding: 10px 12px; color: #1f2a44; background: #fff; }
    .letter-card textarea { min-height: 140px; resize: vertical; }
    .letter-row { display: flex; gap: 8px; }
    .letter-row button { flex: 1; min-height: 40px; border: 1px solid #d5deee; border-radius: 999px; background: #f3f6fb; color: #0b3f90; font-weight: 800; cursor: pointer; }
    .letter-row button.is-on { background: #0b3f90; color: #fff; border-color: #0b3f90; }
    .letter-card .send-letter { margin-top: 14px; min-height: 46px; border: 0; border-radius: 999px; background: #c6ab47; color: #072f6b; font-weight: 800; padding: 0 18px; cursor: pointer; }
    .send-lang { display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 18px 0; background: rgba(255,255,255,.94); color: #3b4a68; font-size: 13px; font-weight: 700; }
    .send-lang select { border: 1px solid #d5deee; border-radius: 999px; min-height: 34px; padding: 0 12px; background: #fff; color: #1d2a44; font-weight: 700; }
    .chats-empty { margin: auto; text-align: center; color: var(--wa-muted); max-width: 440px; padding: 28px; }
    .chats-empty strong { display: block; color: var(--wa-ink); font-size: 26px; font-weight: 800; margin-bottom: 8px; }
    .chats-thread > .chats-empty::before { content: ""; display: block; width: 96px; height: 96px; margin: 0 auto 18px; border-radius: 28px; background: var(--wa-grad) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 11.5a8.4 8.4 0 0 1-12.3 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z'/%3E%3C/svg%3E") no-repeat center; box-shadow: 0 16px 36px rgba(21, 101, 192, .3); transform: rotate(-6deg); }
    .group-card { margin: auto; background: #fff; border: 0; border-radius: 20px; padding: 28px 24px; max-width: 460px; text-align: center; color: var(--wa-ink); box-shadow: 0 12px 32px rgba(15,31,61,.1); }
    .group-card h2 { margin: 8px 0; color: var(--wa-ink); }
    .chat-note { background: linear-gradient(90deg, #fff4d6, #ffe7b3); color: #7a5b10; padding: 9px 16px; font-size: 13px; font-weight: 600; }
    @media (max-width: 800px) {
        .chats-app { width: 100%; max-width: 100%; height: 100dvh; min-height: 0; margin: 0; border-radius: 0; box-shadow: none; overflow: hidden; }
        .chats-side, .chats-thread { width: 100%; max-width: 100%; flex: 1 1 auto; }
        .chats-thread { display: none; }
        .chats-app.has-open .chat-rail, .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
        .chat-back { display: inline-flex; }
        .chats-stream { padding: 8px 12px 12px; }
        .bubble { max-width: 90%; }
        .chats-compose { flex-wrap: nowrap; padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); }
        .chats-compose textarea { flex: 1 1 auto; min-width: 0; min-height: 44px; max-height: 96px; }
        .chats-head { padding: 8px 10px; flex-direction: row; align-items: center; flex-wrap: wrap; gap: 8px; }
        .chats-head-person { width: 100%; flex: none; }
        .chats-head-person h2, .chats-head-person p { white-space: normal; }
        .mode-switch { width: 100%; max-width: 100%; display: flex; flex-wrap: nowrap; gap: 6px; overflow-x: auto; }
        .mode-switch > .call-btn { grid-column: span 2; }
        .mode-switch > .chat-gear { grid-column: span 1; }
        .mode-switch > #threadTranslate { grid-column: span 3; }
        .mode-switch > #threadAi, .mode-switch > #threadHuman { grid-column: span 3; }
        .mode-switch > .announce-btn { grid-column: 1 / -1; }
        .mode-switch form { display: flex; flex: none; }
        .mode-switch button, .announce-btn, .call-btn { width: auto; flex: none; justify-content: center; padding: 0 10px; white-space: nowrap; min-height: 36px; }
        .chats-search input, .chats-compose textarea { font-size: 16px; }
        .hub-install { flex: none; }
        .beyond-module-tabs { margin-bottom: 0; }
        .beyond-module-tabs-label { display: none; }
        .beyond-module-tabs-nav { flex-wrap: nowrap !important; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 8px; }
        .beyond-module-tab { padding: 8px 12px; font-size: 12px; }
        .chat-row { padding: 12px; }
        .hub-screens { padding-top: 8px; }
        .chats-app.is-announce { height: 100dvh; min-height: 0; overflow: hidden; }
        .chats-app.is-announce .chats-thread { overflow: auto; -webkit-overflow-scrolling: touch; }
        .chats-app.is-announce .an-compose.is-embedded { width: 100%; margin: 0; }
        .letter-card { width: calc(100% - 16px); margin: 12px auto; padding: 14px; }
        .letter-card input, .letter-card textarea, .letter-card select { font-size: 16px; }
        .letter-row { flex-direction: column; }
        .chats-head-person h2 { font-size: 16px; }
    }
    body.whatsapp-hub-app { margin: 0; overflow-x: hidden; }
    body.whatsapp-hub-app .header,
    body.whatsapp-hub-app nav.side-navbar,
    body.whatsapp-hub-app .sidebar-backdrop,
    body.whatsapp-hub-app .main-footer,
    body.whatsapp-hub-app #beyond-module-tabs { display: none !important; }
    body.whatsapp-hub-app .page { margin: 0 !important; width: 100% !important; min-height: 100vh; }
    body.whatsapp-hub-app #content,
    body.whatsapp-hub-app #content > .container-fluid { padding: 0 !important; margin: 0 !important; max-width: none !important; }
    body.whatsapp-hub-app .chats-app {
        height: 100vh;
        height: 100dvh;
        min-height: 0;
        margin: 0;
        border-radius: 0;
        box-shadow: none;
        padding-top: env(safe-area-inset-top);
        box-sizing: border-box;
    }
</style>
@php
    $screen = $pane === 'calls' ? 'calls' : ($pane === 'chats' ? 'chats' : 'more');
@endphp
<div class="chats-app {{ ($open || $group || in_array($pane, ['announcements', 'help', 'briefing', 'apps', 'speech', 'updates'], true)) ? 'has-open' : '' }} {{ (!empty($personalHub) && $pane === 'announcements') ? 'is-announce' : '' }}" id="chatsApp">
<script>
try {
    var savedTheme = localStorage.getItem('mai-theme');
    if (savedTheme === 'black' || savedTheme === 'navy') document.getElementById('chatsApp').classList.add('theme-' + savedTheme);
} catch (e) {}
</script>
    <aside class="chats-side {{ !empty($personalHub) ? 'is-personal' : '' }}">
        @php
            $statusDigits = preg_replace('/\D/', '', (string) $sessionPhone);
            $statusPhone = $sessionPhone;
            if (strlen($statusDigits) > 9) {
                $statusRest = substr($statusDigits, -9);
                $statusPhone = '+'.substr($statusDigits, 0, -9).' '.substr($statusRest, 0, 3).' '.substr($statusRest, 3, 3).' '.substr($statusRest, 6, 3);
            } elseif ($statusDigits !== '') {
                $statusPhone = '+'.$statusDigits;
            }
            $statusOn = empty($phoneLink['disconnected']);
        @endphp
        <header class="inbox-bar">
            <div class="inbox-brand">
                <h1>Whats MAI</h1>
                <p class="inbox-status {{ $statusOn ? 'is-on' : 'is-off' }}"><i></i> {{ $statusOn ? 'Connected' : 'Not linked' }} · {{ $statusPhone }}</p>
            </div>
            <div class="inbox-tools">
                <button type="button" class="inbox-icon" id="newChatOpen" aria-label="New chat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5z"/></svg>
                </button>
                <button type="button" class="inbox-icon" id="moreOpen" aria-label="More" aria-expanded="false" aria-controls="moreMenu">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="6" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="18" cy="12" r="1.7"/></svg>
                </button>
            </div>
            <div class="more-menu" id="moreMenu" hidden>
                <button type="button" id="hubInstall">App</button>
                <button type="button" class="js-hub-settings">Settings</button>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            </div>
            <form class="new-chat" id="newChat" method="POST" action="{{ route('whatsapp.chats.start') }}" hidden>
                @csrf
                <label for="newChatPhone">WhatsApp number</label>
                <input id="newChatPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="250794006160" required>
                <button type="submit">Open chat</button>
            </form>
        </header>
        @if(session('not_permitted') && ! $open)
            <div class="chat-note">{{ session('not_permitted') }}</div>
        @endif
        @if(!empty($phoneLink['disconnected']))
            <div class="chat-note">WhatsApp on the phone is not linked{{ !empty($phoneLink['at']) ? ' since '.$phoneLink['at'] : '' }}. Reconnect that number in Wasender. New chats will then stay up to date here.</div>
        @endif
        @php
            $openId = $open ? (int) $open->id : 0;
        @endphp
        <div class="chats-search">
            <input type="search" id="chatSearch" placeholder="{{ $pane === 'chats' ? 'Search chats' : 'Search this list' }}" autocomplete="off">
        </div>
        @if($pane === 'chats')
        <div class="chat-filters" id="chatFilters">
            <button type="button" class="is-on" data-filter="all">All</button>
            <button type="button" data-filter="unread">Unread</button>
            <button type="button" data-filter="read">Read</button>
            <button type="button" data-filter="groups">Groups</button>
        </div>
        @endif
        <div class="chats-list" id="chatList">
            <div class="chat-none" id="chatNone" @if($pane === 'chats' && $conversations->isEmpty() && count($groups) === 0) style="display:block" @endif>@if($pane === 'chats' && $conversations->isEmpty() && count($groups) === 0)No conversations yet. {{ !empty($personalHub) ? 'Chats from your WhatsApp appear here.' : 'Chats from the Wasender number appear here.' }}@else Nothing in this list.@endif</div>
            @if($pane === 'chats')
            @php
                $conversations = $conversations->sort(function ($a, $b) use ($personalHub) {
                    $aWhen = !empty($personalHub) ? $a->last_activity_at : ($a->last_activity_at ?: $a->updated_at);
                    $bWhen = !empty($personalHub) ? $b->last_activity_at : ($b->last_activity_at ?: $b->updated_at);
                    $aTime = $aWhen ? $aWhen->getTimestamp() : 0;
                    $bTime = $bWhen ? $bWhen->getTimestamp() : 0;
                    if ($aTime === $bTime) {
                        return (int) $b->id - (int) $a->id;
                    }

                    return $aTime < $bTime ? 1 : -1;
                })->values();
                $chatList = [];
                foreach ($conversations as $row) {
                    $when = !empty($personalHub) ? $row->last_activity_at : ($row->last_activity_at ?: $row->updated_at);
                    $chatList[] = ['kind' => 'person', 'at' => $when ? $when->getTimestamp() : 0, 'row' => $row];
                }
                foreach ($groups as $row) {
                    $chatList[] = ['kind' => 'group', 'at' => isset($row['at']) ? (int) $row['at'] : 0, 'row' => $row];
                }
                usort($chatList, function ($a, $b) {
                    if ($a['at'] === $b['at']) {
                        return 0;
                    }

                    return $a['at'] < $b['at'] ? 1 : -1;
                });
            @endphp
            @foreach($chatList as $entry)
                @php $row = $entry['row']; @endphp
                @if($entry['kind'] === 'group')
                    @php
                        $groupAt = !empty($row['at']) ? \Carbon\Carbon::createFromTimestamp((int) $row['at']) : null;
                        $groupPreview = \App\WhatsApp\WhatsAppMessage::listPreview(isset($row['preview']) ? $row['preview'] : '');
                        if ($groupPreview === '') {
                            $groupPreview = $row['members'] === null ? 'WhatsApp group' : number_format($row['members']).' contacts';
                        }
                        $groupUnread = isset($row['unread']) ? (int) $row['unread'] : 0;
                        $groupLive = $groupAt || trim((string) (isset($row['preview']) ? $row['preview'] : '')) !== '';
                    @endphp
                    <a class="chat-row {{ $group && $group['jid'] === $row['jid'] ? 'is-on' : '' }}" data-kind="group" data-jid="{{ $row['jid'] }}" data-live="{{ $groupLive ? '1' : '0' }}" data-read="{{ $groupUnread > 0 ? '0' : '1' }}" data-name="{{ strtolower($row['name'].' '.$groupPreview) }}" href="{{ route('whatsapp.chats', ['group' => $row['jid']]) }}">
                        <span class="chat-avatar is-group">G</span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $row['name'] }}</span>
                                @if($groupUnread > 0)<span class="chat-state">Unread</span>@endif
                                <span class="chat-time" @if($groupAt) data-at="{{ $groupAt->toIso8601String() }}" @endif>{{ $groupAt ? $groupAt->format($groupAt->isToday() ? 'H:i' : 'M j') : 'Group' }}</span>
                            </span>
                            <span class="chat-bottom">
                                <span class="chat-preview">{{ $groupPreview }}</span>
                                @if($groupUnread > 0)<span class="chat-badge">{{ $groupUnread }}</span>@endif
                            </span>
                        </span>
                    </a>
                @else
                @php
                    $person = !empty($personalHub)
                        ? app(\App\Services\Mai\PersonalHub::class)->label($row->contact, $row)
                        : (optional($row->contact)->displayName() ?: 'WhatsApp');
                    $rowPhone = !empty($personalHub)
                        ? app(\App\Services\Mai\PersonalHub::class)->phoneLabel($row->contact)
                        : (string) optional($row->contact)->display_phone;
                    $when = !empty($personalHub) ? $row->last_activity_at : ($row->last_activity_at ?: $row->updated_at);
                    $preview = \App\WhatsApp\WhatsAppMessage::listPreview($row->last_message);
                    if ($preview === '' && !empty($personalHub)) {
                        $preview = $rowPhone;
                    }
                    $rowUnread = (int) $row->id === $openId ? 0 : (int) $row->unread_count;
                    $tone = sprintf('%u', crc32(strtolower($person))) % 8;
                @endphp
                <a class="chat-row {{ (int) $row->id === $openId ? 'is-on' : '' }}" data-id="{{ $row->id }}" data-kind="person" data-read="{{ $rowUnread > 0 ? '0' : '1' }}" data-name="{{ strtolower($person.' '.$rowPhone) }}" href="{{ route('whatsapp.chats', ['chat' => $row->id]) }}">
                    <span class="chat-avatar" data-tone="{{ $tone }}">{{ mb_strtoupper(mb_substr($person, 0, 1)) }}</span>
                    <span class="chat-main">
                        <span class="chat-top">
                            <span class="chat-name">{{ $person }}</span>
                            @if($rowUnread > 0)<span class="chat-state">Unread</span>@endif
                            <span class="chat-time" @if($when) data-at="{{ $when->toIso8601String() }}" @endif>{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                        </span>
                        <span class="chat-bottom">
                            <span class="chat-preview">
                                @if($row->mode === 'AI')<span class="chat-ai">AI </span>@endif
                                {{ $preview }}
                            </span>
                            @if($rowUnread > 0)
                                <span class="chat-badge">{{ $rowUnread }}</span>
                            @endif
                        </span>
                    </span>
                </a>
                @endif
            @endforeach
            @if(!empty($personalHub) && empty($historyReady))
                <div class="chat-note">Your chats are still syncing.@if($historyProgress !== null) {{ (int) $historyProgress }}% received.@endif</div>
            @endif
            @elseif($pane === 'calls' && !empty($personalHub))
                <div class="chats-empty"><strong>Calls are not available</strong>Whats MAI does not place calls. Status, Communities, and Channels are not in this app.</div>
            @elseif($pane === 'calls')
                @forelse($calls as $call)
                    @php
                        $person = !empty($personalHub)
                            ? app(\App\Services\Mai\PersonalHub::class)->label($call->contact, isset($callChats[$call->contact_id]) ? $conversations->firstWhere('id', $callChats[$call->contact_id]) : null)
                            : (optional($call->contact)->displayName() ?: ($call->caller_phone ?: 'Unknown'));
                        $when = $call->called_at ?: $call->created_at;
                        $chatId = $call->contact_id && isset($callChats[$call->contact_id]) ? $callChats[$call->contact_id] : null;
                    @endphp
                    <a class="chat-row" data-name="{{ strtolower($person.' '.$call->caller_phone) }}" @if($chatId) href="{{ route('whatsapp.chats', ['chat' => $chatId]) }}" @else href="tel:+{{ preg_replace('/\D/', '', (string) $call->caller_phone) }}" @endif>
                        <span class="chat-avatar"><i class="fa fa-phone"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $person }}</span>
                                <span class="chat-time" @if($when) data-at="{{ $when->toIso8601String() }}" @endif>{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ ucfirst(strtolower(str_replace('_', ' ', (string) $call->status))) }}{{ $call->call_type ? ' · '.$call->call_type : '' }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No calls yet</strong>When someone calls the WhatsApp line, it shows here.</div>
                @endforelse
            @elseif($pane === 'updates')
                <div class="hub-label">Updates</div>
                @forelse($updates as $update)
                    @php $when = $update->created_at; $title = $update->subject ?: 'Announcement'; @endphp
                    <a class="chat-row" data-name="{{ strtolower($title) }}" href="{{ !empty($personalHub) ? route('whatsapp.chats', ['pane' => 'announcements']) : route('announcements.index') }}">
                        <span class="chat-avatar"><i class="fa fa-bell"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $title }}</span>
                                <span class="chat-time" @if($when) data-at="{{ $when->toIso8601String() }}" @endif>{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $update->body)), 80) }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No updates yet</strong>Announcements you send show up here.</div>
                @endforelse
            @else
                @php
                    $more = [
                        ['Help', 'How to use Whats MAI', route('whatsapp.chats', ['pane' => 'help'])],
                    ];
                    $more[] = ['AI briefing', 'Event and reply instructions for AI mode', route('whatsapp.chats', ['pane' => 'briefing'])];
                    $more[] = ['Linked apps', 'Connect an app so AI can use its data', route('whatsapp.chats', ['pane' => 'apps'])];
                    $more[] = ['Speech translation', 'Translated calls from this line', route('whatsapp.chats', ['pane' => 'speech'])];
                    if (empty($personalHub)) {
                        $more[] = ['People', 'Names and numbers', route('whatsapp.people')];
                    }
                    $more[] = ['Groups', 'WhatsApp groups', route('whatsapp.groups')];
                    $more[] = ['Leads', 'Enquiries from chats', route('whatsapp.leads')];
                    $more[] = ['Announcements', 'Send a letter to a group or a person', route('whatsapp.chats', ['pane' => 'announcements'])];
                @endphp
                @foreach($more as $item)
                    <a class="chat-row" data-name="{{ strtolower($item[0].' '.$item[1]) }}" href="{{ $item[2] }}">
                        <span class="chat-avatar">{{ strtoupper(substr($item[0], 0, 1)) }}</span>
                        <span class="chat-main">
                            <span class="chat-name">{{ $item[0] }}</span>
                            <span class="chat-preview">{{ $item[1] }}</span>
                        </span>
                    </a>
                @endforeach
                <div class="hub-label">Updates</div>
                @forelse($updates as $update)
                    @php $when = $update->created_at; $title = $update->subject ?: 'Announcement'; @endphp
                    <a class="chat-row" data-name="{{ strtolower($title) }}" href="{{ !empty($personalHub) ? route('whatsapp.chats', ['pane' => 'announcements']) : route('announcements.index') }}">
                        <span class="chat-avatar"><i class="fa fa-bell"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $title }}</span>
                                <span class="chat-time" @if($when) data-at="{{ $when->toIso8601String() }}" @endif>{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $update->body)), 80) }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No updates yet</strong>Announcements you send show up here.</div>
                @endforelse
                <div class="hub-label">Media</div>
                @forelse($mediaItems as $item)
                    @php
                        $person = !empty($personalHub)
                            ? app(\App\Services\Mai\PersonalHub::class)->label($item->contact, $conversations->firstWhere('id', $item->conversation_id))
                            : (optional($item->contact)->displayName() ?: 'WhatsApp');
                        $labels = ['IMAGE' => 'Photo', 'AUDIO' => 'Voice message', 'VIDEO' => 'Video', 'DOCUMENT' => 'Document'];
                        $label = isset($labels[$item->type]) ? $labels[$item->type] : $item->type;
                    @endphp
                    <a class="chat-row" data-name="{{ strtolower($person.' '.$label.' '.$item->body) }}" href="{{ route('whatsapp.chats', ['chat' => $item->conversation_id]) }}">
                        <span class="chat-avatar"><i class="fa fa-image"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $person }}</span>
                                <span class="chat-time" @if($item->created_at) data-at="{{ $item->created_at->toIso8601String() }}" @endif>{{ $item->created_at ? $item->created_at->format($item->created_at->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ $label }}{{ $item->body ? ' · '.$item->body : '' }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No media yet</strong>Photos, voice messages, videos, and documents show here.</div>
                @endforelse
            @endif
        </div>
    </aside>
    <section class="chats-thread">
        @if($open)
            @php
                $title = !empty($personalHub) ? app(\App\Services\Mai\PersonalHub::class)->label($open->contact, $open) : (optional($open->contact)->displayName() ?: 'WhatsApp');
                $threadPhone = !empty($personalHub)
                    ? app(\App\Services\Mai\PersonalHub::class)->phoneLabel($open->contact)
                    : (string) optional($open->contact)->display_phone;
                if (!empty($personalHub) && strlen(preg_replace('/\D/', '', $threadPhone)) > 13) {
                    $threadPhone = '';
                }
            @endphp
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', array_filter(['filter' => $listFilter !== 'all' ? $listFilter : null])) }}" aria-label="Back to chats"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <span class="chat-avatar" id="threadAvatar" data-tone="{{ sprintf('%u', crc32(strtolower($title))) % 8 }}">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</span>
                    <div>
                        <h2 id="threadName">{{ $title }}</h2>
                        <p id="threadPhone">{{ $threadPhone }} <span class="mode-pill {{ $open->mode === 'AI' ? '' : 'is-human' }}">{{ $open->mode === 'AI' ? 'AI' : 'Human' }}</span></p>
                    </div>
                </div>
                <div class="mode-switch">
                    @php $callDigits = preg_replace('/\D/', '', (string) optional($open->contact)->normalized_phone); @endphp
                    <a class="call-btn" id="threadCall" href="{{ $callDigits !== '' ? 'tel:+'.$callDigits : '#' }}" @if($callDigits === '') hidden @endif><i class="fa fa-phone"></i> Call</a>
                    @if(preg_match('/^(237)?6[0-9]{8}$/', (string) $callDigits))
                        <button type="button" class="call-btn" id="threadUnmask" data-chat="{{ (int) $open->id }}">Unmask</button>
                    @endif
                    <button type="button" class="chat-gear js-hub-settings" aria-label="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></button>
                    <form method="POST" id="threadTranslate" action="{{ route('whatsapp.chats.translator', $open->id) }}">
                        @csrf
                        <button type="submit" class="is-translate {{ !empty($destination['enabled']) ? 'is-on' : '' }}" title="Translation for this chat only. Other chats keep their own language.">Translate</button>
                    </form>
                    <form method="POST" id="threadAi" action="{{ route('whatsapp.conversation.enable_ai', $open->id) }}">
                        @csrf
                        <button type="submit" class="{{ $open->mode === 'AI' ? 'is-on' : '' }}">AI</button>
                    </form>
                    <form method="POST" id="threadHuman" action="{{ route('whatsapp.conversation.takeover', $open->id) }}">
                        @csrf
                        <button type="submit" class="{{ $open->mode !== 'AI' ? 'is-on' : '' }}">Human</button>
                    </form>
                    <a class="announce-btn" href="{{ !empty($personalHub) ? route('whatsapp.chats', ['pane' => 'announcements']) : route('announcements.compose') }}">Announcements</a>
                </div>
            </header>
            @php
                $liveNote = session('message') ?: session('not_permitted');
                if (! $liveNote && ! empty($destination['enabled']) && empty($destination['off']) && ! empty($destination['locked']) && ! empty($destination['name'])) {
                    $staffWrite = ! empty($staffLanguage['name']) ? $staffLanguage['name'] : 'your language';
                    $liveNote = 'Everything you send in this chat goes out in '.$destination['name'].', until you change it. If they write in '.$staffWrite.', you still see it that way.';
                } elseif (! $liveNote && ! empty($destination['enabled']) && empty($destination['off']) && empty($destination['code'])) {
                    $staffWrite = ! empty($staffLanguage['name']) ? $staffLanguage['name'] : 'your language';
                    $liveNote = 'This chat detects their language. If they write in '.$staffWrite.', you still see it that way.';
                }
            @endphp
            @if($liveNote)<div class="chat-note" id="chatNote">{{ $liveNote }}</div>@endif
            <div class="chat-live" id="chatLive">Reconnecting…</div>
            <div class="chats-stream" id="chatStream">
                @if(!empty($hasEarlier) && $messages->first())
                    <button type="button" class="load-earlier" id="loadEarlier" data-before="{{ $messages->first()->id }}">Earlier messages</button>
                @endif
                @php $prevDay = null; @endphp
                @forelse($messages as $message)
                    @php
                        $day = $message->created_at ? $message->created_at->copy()->startOfDay() : null;
                        $dayKey = $day ? $day->format('Y-m-d') : '';
                    @endphp
                    @if($dayKey !== $prevDay)
                        @php
                            $prevDay = $dayKey;
                            $dayLabel = 'Earlier';
                            if ($day && $day->isToday()) $dayLabel = 'Today';
                            elseif ($day && $day->isYesterday()) $dayLabel = 'Yesterday';
                            elseif ($day) $dayLabel = $day->format('M j, Y');
                        @endphp
                        <div class="day-chip">{{ $dayLabel }}</div>
                    @endif
                    @include('whatsapp_hub.partials.chat_bubble', [
                        'id' => $message->id,
                        'day' => $message->created_at ? $message->created_at->format('Y-m-d') : '',
                        'at' => $message->created_at ? $message->created_at->toIso8601String() : '',
                        'out' => $message->direction === 'OUTGOING',
                        'text' => $message->hubText(),
                        'aside' => $message->hubAside(),
                        'time' => $message->created_at ? $message->created_at->format('H:i') : '',
                        'tick' => $message->ticks(),
                        'type' => $message->type,
                        'mediaUrl' => $message->chatMediaUrl(),
                        'mediaName' => $message->chatMediaName(),
                        'mediaReason' => $message->mediaReason(),
                        'who' => null,
                        'choices' => app(\App\Services\Event\EventOptionPresentation::class)->displayChoices($message->body, $message->media_json),
                        'voicePending' => $message->direction === \App\WhatsApp\WhatsAppMessage::DIR_IN && strtoupper((string) $message->type) === 'AUDIO' && trim((string) (isset($message->translationMeta()['english']) ? $message->translationMeta()['english'] : '')) === '',
                        'quote' => $message->quoteText(),
                        'starred' => $message->isStarred(),
                        'edited' => $message->wasEdited(),
                        'deleted' => $message->wasDeleted(),
                        'forwarded' => $message->isForwarded(),
                        'canEdit' => $message->canEditOnWhatsApp(),
                        'canDrop' => $message->canDeleteForEveryone(),
                    ])
                @empty
                    <div class="chats-empty"><strong>No messages yet</strong>Send the first message below.</div>
                @endforelse
            </div>
            <div class="typing-line" id="typingLine" hidden></div>
            @if($canReply)
                @php
                    $chatOn = !empty($destination['enabled']);
                    $replyCode = 'off';
                    $replyName = 'Off';
                    $replyHint = '';
                    if (!empty($destination['off'])) {
                        $replyCode = 'off';
                        $replyName = 'Off';
                    } elseif (!empty($destination['locked']) && !empty($destination['code'])) {
                        $replyCode = $destination['code'];
                        $replyName = $destination['name'] ?: $destination['code'];
                    } elseif ($chatOn && empty($destination['code'])) {
                        $replyCode = 'auto';
                        $replyName = 'Detect';
                    } elseif (!empty($defaultReply)) {
                        $replyCode = $defaultReply['code'];
                        $replyName = $defaultReply['name'];
                        $replyHint = 'Default';
                    }
                    $writeName = !empty($staffLanguage['name']) ? $staffLanguage['name'] : 'English';
                @endphp
                <div class="reply-bar">
                    <button type="button" class="reply-pick" id="openReplyLang">
                        <span>Reply in</span>
                        <strong id="threadReplyName">{{ $replyName }}</strong>
                        @if($replyHint !== '')<em>{{ $replyHint }}</em>@endif
                    </button>
                </div>
                <div class="quote-bar" id="quoteBar" hidden><span id="quoteText"></span><button type="button" id="quoteClear" aria-label="Cancel reply">×</button></div>
                <form id="replyLangForm" method="POST" action="{{ route('whatsapp.chats.language', $open->id) }}" hidden>
                    @csrf
                    <input type="hidden" name="language" id="replyLangValue" value="{{ $replyCode }}">
                </form>
                <form class="chats-compose" id="chatCompose" method="POST" action="{{ route('whatsapp.chats.reply', $open->id) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="clip" title="Photo, file, or paste an image">+<input type="file" name="file" id="chatFile" data-skip-image-paste="1" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"></label>
                    <span class="picked" id="chatPicked" hidden>
                        <img class="paste-preview" id="chatPaste" alt="" hidden>
                        <span class="file-name" id="chatFileName"></span>
                        <button type="button" class="file-clear" aria-label="Remove attachment">×</button>
                    </span>
                    <button type="button" class="tool js-emoji" aria-label="Emoji">☺</button>
                    <textarea name="body" id="chatBody" rows="4" autocomplete="off" aria-label="Message" placeholder=""></textarea>
                    <button type="button" id="openAsk" class="mai-open" aria-label="MAI, Mbole AI" title="MAI"><i></i>MAI</button>
                    <button type="button" class="tool js-mic" aria-label="Record a voice note"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @elseif($group)
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'chats']) }}" aria-label="Back to chats"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>{{ $group['name'] }}</h2>
                        <p>{{ $group['members'] === null ? 'WhatsApp group' : number_format($group['members']).' contacts' }}</p>
                    </div>
                </div>
                <a class="announce-btn" href="{{ !empty($personalHub) ? route('whatsapp.chats', ['pane' => 'announcements', 'group' => $group['jid']]) : route('announcements.compose', ['group' => $group['jid']]) }}">Create announcement</a>
            </header>
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
            <div class="chats-stream" id="chatStream">
                @php $prevDay = null; @endphp
                @forelse($groupMessages as $message)
                    @php
                        $media = [];
                        if ($message->media_json) {
                            $decoded = json_decode((string) $message->media_json, true);
                            $media = is_array($decoded) ? $decoded : [];
                        }
                        $when = $message->message_at ?: $message->created_at;
                        $day = $when ? $when->copy()->startOfDay() : null;
                        $dayKey = $day ? $day->format('Y-m-d') : '';
                        $mediaUrl = isset($media['url']) ? (string) $media['url'] : '';
                        if ($mediaUrl !== '' && strpos($mediaUrl, 'whatsapp-chat/') === 0) {
                            $mediaUrl = asset('public/'.$mediaUrl);
                        } elseif ($mediaUrl !== '' && ! preg_match('#^https?://#i', $mediaUrl)) {
                            $mediaUrl = '';
                        }
                    @endphp
                    @if($dayKey !== $prevDay)
                        @php
                            $prevDay = $dayKey;
                            $dayLabel = 'Earlier';
                            if ($day && $day->isToday()) $dayLabel = 'Today';
                            elseif ($day && $day->isYesterday()) $dayLabel = 'Yesterday';
                            elseif ($day) $dayLabel = $day->format('M j, Y');
                        @endphp
                        <div class="day-chip">{{ $dayLabel }}</div>
                    @endif
                    @include('whatsapp_hub.partials.chat_bubble', [
                        'id' => $message->id,
                        'day' => $dayKey,
                        'at' => $when ? $when->toIso8601String() : '',
                        'out' => ! empty($media['outgoing']),
                        'text' => $message->body,
                        'time' => $when ? $when->format('H:i') : '',
                        'tick' => ! empty($media['outgoing']) ? 'sent' : '',
                        'type' => isset($media['type']) ? $media['type'] : 'TEXT',
                        'mediaUrl' => $mediaUrl,
                        'mediaName' => isset($media['file_name']) ? $media['file_name'] : '',
                        'mediaReason' => isset($media['reason']) ? $media['reason'] : '',
                        'who' => empty($media['outgoing']) ? ($message->participant_name ?: 'Member') : null,
                    ])
                @empty
                    <div class="chats-empty"><strong>{{ $group['name'] }}</strong>Send a message to this group, or create an announcement for its members.</div>
                @endforelse
            </div>
            @if($canReply)
                <form class="chats-compose" id="chatCompose" method="POST" action="{{ route('whatsapp.chats.group') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="jid" value="{{ $group['jid'] }}">
                    <label class="clip" title="Photo, file, or paste an image">+<input type="file" name="file" id="chatFile" data-skip-image-paste="1" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"></label>
                    <span class="picked" id="chatPicked" hidden>
                        <img class="paste-preview" id="chatPaste" alt="" hidden>
                        <span class="file-name" id="chatFileName"></span>
                        <button type="button" class="file-clear" aria-label="Remove attachment">×</button>
                    </span>
                    <button type="button" class="tool js-emoji" aria-label="Emoji">☺</button>
                    <textarea name="body" id="chatBody" rows="4" autocomplete="off" aria-label="Message" placeholder=""></textarea>
                    <button type="button" class="tool js-mic" aria-label="Record a voice note"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @elseif($pane === 'briefing')
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'more']) }}" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>AI briefing</h2>
                        <p>Updates AI uses when a chat is on AI mode</p>
                    </div>
                </div>
            </header>
            @if(session('message'))<div class="chat-note">{{ session('message') }}</div>@endif
            <div class="letter-card" style="overflow:auto;">
                <form method="post" action="{{ route('whatsapp.brief.store') }}">
                    @csrf
                    <input type="hidden" name="brief_form" value="1">
                    <label>What is this about?</label>
                    <input type="text" name="title" required maxlength="191" placeholder="Conference, product launch, office hours">
                    <label class="mt-2"><input type="checkbox" name="timed" value="1" class="brief-timed"> Only true for a period</label>
                    <div class="brief-times" hidden>
                        <label>Starts</label>
                        <input type="datetime-local" name="starts_at">
                        <label>Ends</label>
                        <input type="datetime-local" name="ends_at">
                    </div>
                    <label class="mt-2">Details AI may tell people</label>
                    <textarea name="details" rows="5" required maxlength="4000" placeholder="Date, place, price, what to say when someone asks…"></textarea>
                    <button class="mt-3" type="submit">Save briefing</button>
                </form>
                @foreach(($briefs ?? []) as $brief)
                    <form method="post" action="{{ route('whatsapp.brief.update', $brief->id) }}" class="mt-4" style="border-top:1px solid #d7e3f4;padding-top:1rem;">
                        @csrf
                        <input type="hidden" name="brief_form" value="1">
                        <input type="text" name="title" required maxlength="191" value="{{ $brief->title }}">
                        <textarea name="details" rows="4" required maxlength="4000" class="mt-2">{{ $brief->details }}</textarea>
                        <label class="mt-2"><input type="checkbox" name="enabled" value="1" {{ $brief->enabled ? 'checked' : '' }}> Use in AI replies</label>
                        <button type="submit">Update</button>
                    </form>
                    <form method="post" action="{{ route('whatsapp.brief.delete', $brief->id) }}" onsubmit="return confirm('Remove this briefing?');">
                        @csrf
                        <button type="submit">Remove</button>
                    </form>
                @endforeach
            </div>
        @elseif($pane === 'apps')
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'more']) }}" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>Linked apps</h2>
                        <p>AI can read data from your application</p>
                    </div>
                </div>
            </header>
            @if(session('message'))<div class="chat-note">{{ session('message') }}</div>@endif
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
            <form class="letter-card" method="POST" action="{{ route('whatsapp.mai.app_link') }}">
                @csrf
                <p>Paste an https URL that returns JSON or text your AI may use. Optional bearer token for private APIs.</p>
                <label>Application URL</label>
                <input type="url" name="url" value="{{ $maiAppLink['url'] ?? '' }}" placeholder="https://your-app.example/api/mai-data">
                <label class="mt-2">Bearer token (optional)</label>
                <input type="text" name="token" value="{{ $maiAppLink['token'] ?? '' }}" autocomplete="off">
                <button class="mt-3" type="submit">Save link</button>
            </form>
        @elseif($pane === 'speech')
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'more']) }}" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>Speech translation</h2>
                        <p>Call, then translate what is said</p>
                    </div>
                </div>
            </header>
            <form id="speechForm" class="letter-card">
                @csrf
                <p>Tap Call in a chat to dial. Then listen here. Speech is translated into simple language on this device.</p>
                <textarea id="speechText" maxlength="1000" rows="3" placeholder="Speak, or type what was said"></textarea>
                <label class="mt-2">Translate into</label>
                <input id="speechLanguage" value="English" maxlength="40">
                <button type="button" id="speechListen" class="mt-3">Listen</button>
                <button type="submit">Translate</button>
                <p id="speechOut" class="mt-3"></p>
            </form>
            <script>
            (function () {
                var form = document.getElementById('speechForm');
                if (!form) return;
                var field = document.getElementById('speechText');
                var out = document.getElementById('speechOut');
                var listen = document.getElementById('speechListen');
                var rec = null;
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var body = new FormData(form);
                    body.append('text', field ? field.value : '');
                    body.append('language', (document.getElementById('speechLanguage') || {}).value || 'English');
                    fetch('{{ route('whatsapp.chats.speech') }}', { method: 'POST', body: body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (response) { return response.json(); })
                        .then(function (payload) {
                            if (out) out.textContent = payload && payload.text ? payload.text : (payload && payload.error ? payload.error : 'That line could not be translated.');
                            if (payload && payload.text && window.speechSynthesis) {
                                window.speechSynthesis.cancel();
                                window.speechSynthesis.speak(new SpeechSynthesisUtterance(payload.text));
                            }
                        })
                        .catch(function () { if (out) out.textContent = 'That line could not be translated.'; });
                });
                if (listen) listen.addEventListener('click', function () {
                    var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (!Rec) { if (out) out.textContent = 'This browser cannot listen. Type the line instead.'; return; }
                    if (rec) { rec.stop(); rec = null; listen.textContent = 'Listen'; return; }
                    rec = new Rec();
                    rec.continuous = true;
                    rec.onresult = function (event) {
                        var said = '';
                        for (var i = event.resultIndex; i < event.results.length; i++) said += event.results[i][0].transcript;
                        if (field) field.value = (field.value ? field.value + ' ' : '') + said.trim();
                    };
                    rec.onend = function () { listen.textContent = 'Listen'; rec = null; };
                    rec.start();
                    listen.textContent = 'Stop';
                });
            })();
            </script>
        @elseif($pane === 'help')
            @include('whatsapp_hub.partials.help')
        @elseif($pane === 'announcements' && !empty($personalHub) && !empty($announce))
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats') }}" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>Announcements</h2>
                        <p>Write a message, choose people or a group, then send</p>
                    </div>
                </div>
            </header>
            @if(session('message'))<div class="chat-note">{{ session('message') }}</div>@endif
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
            <div class="an-shell an-compose is-mai is-embedded">
                @include('announcement_manager.partials.composer', $announce)
            </div>
        @elseif($pane === 'announcements')
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'more']) }}" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
                    <div>
                        <h2>Announcements</h2>
                        <p>Send a letter to one group or one person</p>
                    </div>
                </div>
            </header>
            @if(session('message'))<div class="chat-note">{{ session('message') }}</div>@endif
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
            <form class="letter-card" method="POST" action="{{ route('whatsapp.chats.letter') }}" enctype="multipart/form-data">
                @csrf
                <h2>Letter</h2>
                <p>It goes out on WhatsApp to the group or the person you pick. A file is sent with the letter.</p>
                <input type="hidden" name="audience" id="letterAudience" value="group">
                <div class="letter-row">
                    <button type="button" class="is-on" data-audience="group">Group</button>
                    <button type="button" data-audience="person">Person</button>
                </div>
                <div id="letterGroup">
                    <label>WhatsApp group</label>
                    <select name="group">
                        <option value="">Choose a group</option>
                        @foreach($groups as $row)
                            <option value="{{ $row['jid'] }}">{{ $row['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="letterPerson" hidden>
                    <label>Person</label>
                    <input type="search" id="letterSearch" placeholder="Search a name" autocomplete="off">
                    <select name="person" id="letterPeople" style="margin-top:8px">
                        <option value="">Choose a person</option>
                        @foreach($conversations as $row)
                            @php $person = !empty($personalHub) ? app(\App\Services\Mai\PersonalHub::class)->label($row->contact, $row) : (optional($row->contact)->displayName() ?: 'WhatsApp'); @endphp
                            <option value="{{ $row->id }}" data-name="{{ strtolower($person.' '.optional($row->contact)->display_phone) }}">{{ $person }}</option>
                        @endforeach
                    </select>
                </div>
                <label>Subject</label>
                <input type="text" name="subject" maxlength="180" placeholder="Subject">
                <label>Letter</label>
                <textarea name="body" placeholder="Write the letter"></textarea>
                <label>File</label>
                <input type="file" name="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                <button type="submit" class="send-letter">Send letter</button>
            </form>
        @else
            <div class="chats-empty">
                <strong>{{ $pane === 'updates' ? 'Updates' : 'Chats' }}</strong>
                {{ $pane === 'updates' ? 'Announcements you send are listed on the left.' : 'Search a name on the left. A person opens the WhatsApp thread. A group with a new message appears in this list too.' }}
            </div>
        @endif
    </section>
</div>
<template id="hubThreadShell">
    <header class="chats-head">
        <div class="chats-head-person">
            <a class="chat-back" href="{{ route('whatsapp.chats') }}" aria-label="Back to chats"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg></a>
            <span class="chat-avatar" id="threadAvatar" data-tone="0">W</span>
            <div>
                <h2 id="threadName">WhatsApp</h2>
                <p id="threadPhone"></p>
            </div>
        </div>
        <div class="mode-switch">
            <a class="call-btn" id="threadCall" href="#" hidden><i class="fa fa-phone"></i> Call</a>
            <button type="button" class="chat-gear js-hub-settings" aria-label="Settings">⚙</button>
            <form method="POST" id="threadTranslate" action="">@csrf<button type="submit" class="is-translate">Translate</button></form>
            <form method="POST" id="threadAi" action="">@csrf<button type="submit">AI</button></form>
            <form method="POST" id="threadHuman" action="">@csrf<button type="submit">Human</button></form>
            <a class="announce-btn" href="{{ !empty($personalHub) ? route('whatsapp.chats', ['pane' => 'announcements']) : route('announcements.compose') }}">Announcements</a>
        </div>
    </header>
    <div class="chat-live" id="chatLive">Reconnecting…</div>
    <div class="chats-stream" id="chatStream"></div>
    @if($canReply)
    <div class="reply-bar"><button type="button" class="reply-pick" id="openReplyLang"><span>Reply in</span><strong id="threadReplyName">Off</strong></button></div>
    <div class="quote-bar" id="quoteBar" hidden><span id="quoteText"></span><button type="button" id="quoteClear" aria-label="Cancel reply">×</button></div>
    <form id="replyLangForm" method="POST" action="" hidden>@csrf<input type="hidden" name="language" id="replyLangValue" value="off"></form>
    <form class="chats-compose" id="chatCompose" method="POST" action="" enctype="multipart/form-data">
        @csrf
        <label class="clip" title="Photo, file, or paste an image">+<input type="file" name="file" id="chatFile" data-skip-image-paste="1" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"></label>
        <span class="picked" id="chatPicked" hidden>
            <img class="paste-preview" id="chatPaste" alt="" hidden>
            <span class="file-name" id="chatFileName"></span>
            <button type="button" class="file-clear" aria-label="Remove attachment">×</button>
        </span>
        <button type="button" class="tool js-emoji" aria-label="Emoji">☺</button>
        <textarea name="body" id="chatBody" rows="4" autocomplete="off" aria-label="Message" placeholder=""></textarea>
        <button type="button" id="openAsk" class="mai-open" aria-label="MAI, Mbole AI" title="MAI"><i></i>MAI</button>
        <button type="button" class="tool js-mic" aria-label="Record a voice note"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
        <button type="submit" aria-label="Send">➤</button>
    </form>
    @endif
</template>
@php
    $staffCode = !empty($staffLanguage['code']) ? $staffLanguage['code'] : 'en';
    $defaultCode = !empty($defaultReply['code']) ? $defaultReply['code'] : '';
    $sheetTitle = ($open && isset($title)) ? $title : 'this chat';
    $sheetWrite = isset($writeName) ? $writeName : (!empty($staffLanguage['name']) ? $staffLanguage['name'] : 'English');
    $sheetCode = isset($replyCode) ? $replyCode : 'off';
    $keptName = (is_array($destination) && !empty($destination['name']) && (!empty($destination['off']) || empty($destination['enabled']))) ? $destination['name'] : '';
    $langHint = [
        'ur' => 'pakistan urdu',
        'pa' => 'pakistan punjabi',
        'ps' => 'pakistan pashto',
        'sd' => 'pakistan sindhi',
        'zh' => 'china chinese mandarin',
        'hi' => 'india hindi',
        'bn' => 'bangladesh bengali',
        'ar' => 'arabic',
        'fr' => 'french cameroon',
        'sw' => 'swahili kenya tanzania',
        'rw' => 'kinyarwanda rwanda',
        'am' => 'amharic ethiopia',
    ];
@endphp
<div class="lang-sheet" id="hubSettings">
    <div class="lang-card">
        <header>
            <h3>Settings</h3>
            <button type="button" class="lang-close" data-close="hubSettings" aria-label="Close">×</button>
        </header>
        <p>Theme, language, and updates. Chats, calls, media, replies, and your MAI tools stay available.</p>
        <button type="button" class="settings-link" id="openTheme">Theme <span>Light, black, or navy</span></button>
        <button type="button" class="settings-link" id="openLanguage">Language <span>What you write, and the default reply</span></button>
        <a class="settings-link" href="{{ route('whatsapp.chats', ['pane' => 'updates']) }}">Updates <span>Announcements and status</span></a>
    </div>
</div>
<div class="lang-sheet" id="themeSettings">
    <div class="lang-card">
        <header>
            <h3>Theme</h3>
            <button type="button" class="lang-close" data-close="themeSettings" aria-label="Close">×</button>
        </header>
        <p>The choice stays on this browser.</p>
        <button type="button" class="lang-option" data-theme="light"><span class="theme-swatch" style="background:#fff"></span>Light</button>
        <button type="button" class="lang-option" data-theme="black"><span class="theme-swatch" style="background:#111b21"></span>Black</button>
        <button type="button" class="lang-option" data-theme="navy"><span class="theme-swatch" style="background:#0b1b3a"></span>Navy</button>
    </div>
</div>
<div class="lang-sheet" id="langSettings">
    <form class="lang-card" method="POST" action="{{ route('whatsapp.chats.settings') }}">
        @csrf
        <input type="hidden" name="chat" value="{{ $open ? $open->id : '' }}">
        <header>
            <h3>Language</h3>
            <button type="button" class="lang-close" data-close="langSettings" aria-label="Close">×</button>
        </header>
        <p>You write in one language. Each person can receive your reply in another. That choice stays until you change it.</p>
        <label>I write in</label>
        <input class="lang-filter" type="search" data-list="staffList" placeholder="Search languages, including English" autocomplete="off">
        <input type="hidden" name="staff_language" id="staffLanguage" value="{{ $staffCode }}">
        <div class="lang-list" id="staffList">
            @include('whatsapp_hub.partials.language_options', ['target' => 'staffLanguage', 'selected' => $staffCode, 'blank' => null])
        </div>
        <label>Default reply language</label>
        <input class="lang-filter" type="search" data-list="defaultList" placeholder="Search languages" autocomplete="off">
        <input type="hidden" name="default_reply" id="defaultReply" value="{{ $defaultCode }}">
        <div class="lang-list" id="defaultList">
            <button type="button" class="lang-option {{ $defaultCode === '' ? 'is-on' : '' }}" data-code="" data-target="defaultReply" data-search="none off no default">No default</button>
            @include('whatsapp_hub.partials.language_options', ['target' => 'defaultReply', 'selected' => $defaultCode, 'blank' => null])
        </div>
        <label class="enter-choice"><input type="hidden" name="enter_sends" value="0"><input type="checkbox" name="enter_sends" value="1" {{ !empty($enterSends) ? 'checked' : '' }}> Enter key sends the message</label>
        <button type="submit" class="lang-save">Save</button>
    </form>
</div>
@if($canReply)
<div class="lang-sheet" id="replyLang">
    <div class="lang-card">
        <header>
            <h3 id="replyLangTitle">Reply to {{ $sheetTitle }}</h3>
            <button type="button" class="lang-close" data-close="replyLang" aria-label="Close">×</button>
        </header>
        <p>Everything you send in this chat goes out in the language you pick. Turning translation off pauses it, and turning it on again uses the same language. A message they write in {{ $sheetWrite }} still arrives in {{ $sheetWrite }}.</p>
        <p id="replyLangKept" @if($keptName === '') hidden @endif>{{ $keptName !== '' ? $keptName.' stays saved for this person. Turn translation on to use it again.' : '' }}</p>
        <input class="lang-filter" type="search" data-list="replyList" placeholder="Search languages" autocomplete="off">
        <div class="lang-list" id="replyList">
            <button type="button" class="lang-option {{ $sheetCode === 'off' ? 'is-on' : '' }}" data-code="off" data-target="replyLangValue" data-submit="1" data-search="off stop">Off</button>
            <button type="button" class="lang-option {{ $sheetCode === 'auto' ? 'is-on' : '' }}" data-code="auto" data-target="replyLangValue" data-submit="1" data-search="detect auto">Detect their language</button>
            @include('whatsapp_hub.partials.language_options', ['target' => 'replyLangValue', 'selected' => $sheetCode, 'submit' => true])
        </div>
    </div>
</div>
@endif
@include('whatsapp_hub.partials.local_time')
<div class="msg-menu" id="msgMenu" hidden>
    <div class="msg-react" id="msgReact" hidden>
        <button type="button" data-react="👍" aria-label="Thumbs up">👍</button>
        <button type="button" data-react="❤️" aria-label="Heart">❤️</button>
        <button type="button" data-react="😂" aria-label="Laugh">😂</button>
        <button type="button" data-react="😮" aria-label="Surprised">😮</button>
        <button type="button" data-react="😢" aria-label="Sad">😢</button>
        <button type="button" data-react="🙏" aria-label="Thanks">🙏</button>
        <button type="button" data-act="cancel-react">Back</button>
    </div>
    <div class="msg-actions" id="msgActions">
        <button type="button" data-act="reply">Reply</button>
        <button type="button" data-act="react">React</button>
        <button type="button" data-act="keep">Keep</button>
        <button type="button" data-act="pin">Pin</button>
        <button type="button" data-act="forward">Forward</button>
        <button type="button" data-act="copy">Copy</button>
        <button type="button" data-act="edit">Edit</button>
        <button type="button" data-act="info">Info</button>
        <hr>
        <button type="button" data-act="delete" class="danger">Delete</button>
        <hr>
        <button type="button" data-act="select">Select messages</button>
    </div>
    <form class="msg-edit" id="msgEdit" hidden>
        <textarea id="msgEditBody" maxlength="4000"></textarea>
        <button type="submit">Save</button>
        <button type="button" data-act="cancel-edit">Cancel</button>
    </form>
    <div class="msg-actions" id="msgDelete" hidden>
        <button type="button" data-act="delete-me" class="danger">Delete for me</button>
        <button type="button" data-act="delete-all" class="danger">Delete for everyone</button>
        <button type="button" data-act="cancel-delete">Cancel</button>
    </div>
    <div id="msgInfo" hidden>
        <p id="msgInfoText"></p>
        <button type="button" data-act="cancel-info">Back</button>
    </div>
</div>
<div id="selectBar" hidden>
    <span id="selectCount">0 selected</span>
    <button type="button" id="selectDelete">Delete for me</button>
    <button type="button" id="selectCancel">Cancel</button>
</div>
<div class="lang-sheet" id="transcribeSheet">
    <div class="lang-card ask-card">
        <header>
            <div class="ask-brand">
                <span class="ask-mark" aria-hidden="true"><i></i>MAI</span>
                <div>
                    <h3>MAI</h3>
                    <p class="ask-scope">Mbole AI · this chat only</p>
                </div>
            </div>
            <button type="button" class="lang-close" data-close="transcribeSheet" aria-label="Close">×</button>
        </header>
        <div class="ask-chips">
            <button type="button" data-prompt="Summarize this chat in a few sentences.">Summarize</button>
            <button type="button" data-prompt="Write a short reply I can send.">Suggest a reply</button>
            <button type="button" data-prompt="List every question and the facts they asked for, in order.">List the questions</button>
        </div>
        <div class="ask-thread" id="askThread">
            <p class="ask-note" id="transcribeStatus">Latest messages. MAI reads this chat.</p>
            <div class="ask-preview" id="askPreview"></div>
            <div id="transcribeResult" hidden></div>
            <button type="button" id="transcribeUse" hidden>Use this reply</button>
        </div>
        <div class="ask-dock">
            <div class="ask-file" id="askFileName" hidden>
                <img alt="" hidden>
                <span></span>
                <button type="button" id="askFileClear" aria-label="Remove file">×</button>
            </div>
            <div class="ask-row">
                <label class="ask-clip" aria-label="Attach a file">
                    <input type="file" id="askFile" class="js-no-image-paste" data-skip-image-paste="1" accept=".txt,.csv,.md,.json,.pdf,.docx,.png,.jpg,.jpeg,.webp,.gif">
                    Attach
                </label>
                <textarea id="transcribePrompt" maxlength="2000" rows="1" placeholder="Ask MAI, or paste an image"></textarea>
                <button type="button" id="transcribeAsk">MAI</button>
            </div>
        </div>
    </div>
</div>
<div class="lang-sheet" id="forwardSheet">
    <div class="lang-card">
        <header>
            <h3>Forward to</h3>
            <button type="button" class="lang-close" data-close="forwardSheet" aria-label="Close">×</button>
        </header>
        <input class="lang-filter" id="forwardSearch" type="search" placeholder="Search a name" autocomplete="off">
        <div class="forward-list" id="forwardList"></div>
    </div>
</div>
<div class="emoji-box" id="emojiBox" hidden></div>
<script>
window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
});
(function () {
    var search = document.getElementById('chatSearch');
    var list = document.getElementById('chatList');
    var filters = document.getElementById('chatFilters');
    var none = document.getElementById('chatNone');
    var filter = @json($pane === 'chats' ? $listFilter : 'all');
    var savedListScroll = 0;
    var openId = {{ $openId }};
    var listPage = @json(route('whatsapp.chats'));
    function applyList() {
        if (!list) return;
        var q = search ? search.value.toLowerCase().trim() : '';
        var shown = 0;
        list.querySelectorAll('.chat-row').forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            var kind = row.getAttribute('data-kind') || 'person';
            var read = row.getAttribute('data-read') === '1';
            var ok = !q || name.indexOf(q) !== -1;
            var liveGroup = kind === 'group' && row.getAttribute('data-live') === '1';
            if (filter === 'all' && kind === 'group') ok = ok && liveGroup;
            if (filter === 'unread') ok = ok && !read && (kind === 'person' || liveGroup);
            if (filter === 'read') ok = ok && kind === 'person' && read;
            if (filter === 'groups') ok = ok && kind === 'group';
            row.style.display = ok ? '' : 'none';
            if (ok) shown += 1;
        });
        if (none) none.style.display = shown ? 'none' : 'block';
    }
    if (filters) {
        filters.querySelectorAll('button').forEach(function (button) {
            if (button.getAttribute('data-filter') === filter) {
                filters.querySelectorAll('button').forEach(function (other) { other.classList.remove('is-on'); });
                button.classList.add('is-on');
            }
            button.addEventListener('click', function () {
                filter = button.getAttribute('data-filter') || 'all';
                filters.querySelectorAll('button').forEach(function (other) { other.classList.remove('is-on'); });
                button.classList.add('is-on');
                if (list) list.scrollTop = 0;
                if (window.history && history.replaceState) {
                    var url = new URL(window.location.href);
                    if (filter === 'all') url.searchParams.delete('filter');
                    else url.searchParams.set('filter', filter);
                    history.replaceState(null, '', url.toString());
                }
                applyList();
            });
        });
    }
    if (list) {
        list.addEventListener('click', function (event) {
            var row = event.target.closest('a.chat-row');
            if (!row || !row.getAttribute('href') || row.getAttribute('href').indexOf(listPage) !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey) return;
            var url = new URL(row.href, window.location.href);
            if (filter !== 'all') url.searchParams.set('filter', filter);
            else url.searchParams.delete('filter');
            rememberList();
            var chatId = row.getAttribute('data-id') || url.searchParams.get('chat');
            if (row.getAttribute('data-kind') === 'group' || url.searchParams.get('group') || !chatId) {
                window.location.href = url.toString();
                return;
            }
            event.preventDefault();
            openChatInPlace(chatId);
        });
    }
    if (search) search.addEventListener('input', function () { rememberList(); applyList(); });
    restoreList();
    var stream = document.getElementById('chatStream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var enterSends = @json(!empty($enterSends));
    var voiceHeard = 0;
    function pastedKind(file) {
        if (!file) return '';
        var type = (file.type || '').toLowerCase();
        var name = (file.name || '').toLowerCase();
        if (type.indexOf('image/') === 0 || /\.(png|jpe?g|gif|webp)$/.test(name)) return 'image';
        if (type === 'application/pdf' || name.slice(-4) === '.pdf') return 'pdf';
        if (type.indexOf('word') !== -1 || name.slice(-5) === '.docx' || name.slice(-4) === '.doc') return 'doc';
        if (type.indexOf('sheet') !== -1 || type.indexOf('excel') !== -1 || name.slice(-5) === '.xlsx' || name.slice(-4) === '.xls') return 'sheet';
        return '';
    }
    function namePasted(file) {
        if (!file) return null;
        var kind = pastedKind(file);
        if (!kind && file.name) kind = pastedKind({ type: file.type, name: file.name });
        if (!kind) return null;
        if (file.name) return file;
        var ext = kind === 'image' ? 'png' : (kind === 'pdf' ? 'pdf' : (kind === 'doc' ? 'docx' : 'xlsx'));
        var mime = file.type || (kind === 'image' ? 'image/png' : (kind === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
        try { return new File([file], 'pasted-file.' + ext, { type: mime }); }
        catch (e) { return file; }
    }
    function clipboardFile(data) {
        if (!data) return null;
        var items = data.items || [];
        var i;
        for (i = 0; i < items.length; i++) {
            if (!items[i] || items[i].kind !== 'file') continue;
            var fromItem = namePasted(items[i].getAsFile());
            if (fromItem) return fromItem;
        }
        var files = data.files || [];
        for (i = 0; i < files.length; i++) {
            var fromList = namePasted(files[i]);
            if (fromList) return fromList;
        }
        return null;
    }
    function showPicked(file) {
        var name = document.getElementById('chatFileName');
        var preview = document.getElementById('chatPaste');
        var chip = document.getElementById('chatPicked');
        var form = document.getElementById('chatCompose');
        if (form) form.hubFile = file || null;
        if (chip) chip.hidden = !file;
        if (name) {
            name.textContent = file ? (file.name || 'Pasted file') : '';
        }
        if (!preview) return;
        if (preview.dataset.url) URL.revokeObjectURL(preview.dataset.url);
        if (file && pastedKind(file) === 'image') {
            var url = URL.createObjectURL(file);
            preview.dataset.url = url;
            preview.src = url;
            preview.hidden = false;
        } else {
            preview.hidden = true;
            preview.removeAttribute('src');
        }
    }
    function attachPasted(picked) {
        picked = namePasted(picked);
        if (!picked) return;
        var input = document.getElementById('chatFile');
        var form = document.getElementById('chatCompose');
        if (form) form.hubFile = picked;
        if (input) {
            try {
                var list = new DataTransfer();
                list.items.add(picked);
                input.files = list.files;
            } catch (e) {}
        }
        showPicked(picked);
    }
    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('.file-clear')) {
            event.preventDefault();
            var clearInput = document.getElementById('chatFile');
            var clearForm = document.getElementById('chatCompose');
            if (clearInput) clearInput.value = '';
            if (clearForm) clearForm.hubFile = null;
            showPicked(null);
            return;
        }
        if (event.target && event.target.id === 'chatFileName' && event.target.textContent) {
            var input = document.getElementById('chatFile');
            if (input) input.value = '';
            showPicked(null);
        }
    });
    document.addEventListener('change', function (event) {
        if (!event.target || event.target.id !== 'chatFile') return;
        var picked = event.target.files && event.target.files[0];
        showPicked(picked || null);
    });
    var askPasteUrl = '';
    function showAskFile(file) {
        var input = document.getElementById('askFile');
        var name = document.getElementById('askFileName');
        if (!name) return;
        var span = name.querySelector('span');
        var preview = name.querySelector('img');
        if (askPasteUrl) {
            URL.revokeObjectURL(askPasteUrl);
            askPasteUrl = '';
        }
        if (input && file) {
            try {
                var list = new DataTransfer();
                list.items.add(file);
                input.files = list.files;
            } catch (e) {}
        }
        if (!file) {
            name.hidden = true;
            if (span) span.textContent = '';
            if (preview) { preview.removeAttribute('src'); preview.hidden = true; }
            return;
        }
        var image = file.type && file.type.indexOf('image/') === 0;
        name.hidden = false;
        if (span) span.textContent = file.name || (image ? 'Pasted image' : 'Pasted file');
        if (preview) {
            if (image) {
                askPasteUrl = URL.createObjectURL(file);
                preview.src = askPasteUrl;
                preview.hidden = false;
            } else {
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        }
    }
    function askPastedFile(data) {
        var named = clipboardFile(data);
        if (named) return named;
        if (!data) return null;
        var items = data.items || [];
        var i;
        for (i = 0; i < items.length; i++) {
            if (!items[i] || items[i].kind !== 'file') continue;
            var fromItem = items[i].getAsFile();
            if (fromItem) return fromItem;
        }
        var files = data.files || [];
        return files.length ? files[0] : null;
    }
    document.addEventListener('paste', function (event) {
        var field = event.target;
        if (!field) return;
        if (field.id === 'transcribePrompt') {
            var askPicked = askPastedFile(event.clipboardData);
            if (!askPicked) return;
            var pastedText = '';
            try { pastedText = event.clipboardData.getData('text/plain') || ''; } catch (e) {}
            showAskFile(askPicked);
            if (!pastedText.trim()) event.preventDefault();
            return;
        }
        if (field.id !== 'chatBody') return;
        var picked = clipboardFile(event.clipboardData);
        if (!picked) return;
        event.preventDefault();
        attachPasted(picked);
    }, true);
    document.addEventListener('dragover', function (event) {
        if (!event.target || (event.target.id !== 'chatBody' && event.target.id !== 'transcribePrompt')) return;
        event.preventDefault();
    });
    document.addEventListener('drop', function (event) {
        if (!event.target) return;
        if (event.target.id === 'transcribePrompt') {
            var askPicked = askPastedFile(event.dataTransfer);
            if (!askPicked) return;
            event.preventDefault();
            showAskFile(askPicked);
            return;
        }
        if (event.target.id !== 'chatBody') return;
        var picked = clipboardFile(event.dataTransfer);
        if (!picked) return;
        event.preventDefault();
        attachPasted(picked);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && event.target && event.target.id === 'transcribePrompt') {
            event.preventDefault();
            runAsk('');
            return;
        }
        if (!enterSends || event.key !== 'Enter' || event.shiftKey) return;
        var field = event.target;
        if (!field || field.id !== 'chatBody') return;
        var picked = document.getElementById('chatFile');
        var hasFile = (picked && picked.files && picked.files.length) || (field.form && field.form.hubFile);
        if (field.form && field.form.getAttribute('data-sending') !== '1' && (field.value.trim() !== '' || hasFile)) {
            event.preventDefault();
            if (field.form.requestSubmit) field.form.requestSubmit();
            else field.form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    });
    document.addEventListener('submit', function (event) {
        if (event.target && event.target.id === 'msgEdit') {
            event.preventDefault();
            if (!activeMessage) return;
            var box = document.getElementById('msgEditBody');
            messagePost(activeMessage.getAttribute('data-id'), 'edit', { text: box ? box.value : '' }).then(function (payload) {
                if (!payload || !payload._ok) { window.alert((payload && payload.error) || 'Edit failed.'); return; }
                if (payload.message) replaceBubble(payload.message);
                closeMessageMenu();
            });
            return;
        }
        var form = event.target;
        if (!form || !form.classList || !form.classList.contains('chats-compose')) return;
        var action = form.getAttribute('action') || '';
        if (!/\/chats\/\d+\/reply/.test(action) && action.indexOf('/chats/group') === -1) return;
        event.preventDefault();
        sendChat(form);
    });
    function openSheet(id) {
        var sheet = document.getElementById(id);
        if (!sheet) return;
        sheet.classList.add('is-on');
        var searches = sheet.querySelectorAll('.lang-filter');
        searches.forEach(function (search) {
            search.value = '';
            search.dispatchEvent(new Event('input'));
        });
        if (searches.length) searches[0].focus();
        sheet.querySelectorAll('.lang-option').forEach(function (option) { option.style.display = ''; });
    }
    function closeSheet(id) {
        var sheet = document.getElementById(id);
        if (sheet) sheet.classList.remove('is-on');
    }
    document.querySelectorAll('.js-hub-settings').forEach(function (button) {
        button.addEventListener('click', function () { openSheet('hubSettings'); });
    });
    var openLanguage = document.getElementById('openLanguage');
    var openTheme = document.getElementById('openTheme');
    if (openLanguage) openLanguage.addEventListener('click', function () { closeSheet('hubSettings'); openSheet('langSettings'); });
    if (openTheme) openTheme.addEventListener('click', function () { closeSheet('hubSettings'); openSheet('themeSettings'); });
    function applyHubTheme(theme) {
        var app = document.getElementById('chatsApp');
        if (!app) return;
        app.classList.remove('theme-black', 'theme-navy');
        if (theme === 'black' || theme === 'navy') app.classList.add('theme-' + theme);
        document.querySelectorAll('#themeSettings .lang-option').forEach(function (option) {
            option.classList.toggle('is-on', option.getAttribute('data-theme') === theme);
        });
        try { localStorage.setItem('mai-theme', theme || 'light'); } catch (e) {}
    }
    document.querySelectorAll('#themeSettings .lang-option').forEach(function (option) {
        option.addEventListener('click', function () { applyHubTheme(option.getAttribute('data-theme') || 'light'); });
    });
    var openReply = document.getElementById('openReplyLang');
    if (openReply) openReply.addEventListener('click', function () { openSheet('replyLang'); });
    document.querySelectorAll('.lang-sheet').forEach(function (sheet) {
        sheet.addEventListener('click', function (event) {
            if (event.target === sheet) closeSheet(sheet.id);
        });
    });
    document.querySelectorAll('.lang-close').forEach(function (button) {
        button.addEventListener('click', function () { closeSheet(button.getAttribute('data-close')); });
    });
    document.querySelectorAll('.lang-filter').forEach(function (input) {
        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            var list = document.getElementById(input.getAttribute('data-list'));
            if (!list) return;
            list.querySelectorAll('.lang-option').forEach(function (option) {
                var hay = ((option.getAttribute('data-search') || '') + ' ' + option.textContent).toLowerCase();
                option.style.display = hay.indexOf(query) === -1 ? 'none' : '';
            });
        });
    });
    document.querySelectorAll('.lang-option').forEach(function (option) {
        option.addEventListener('click', function () {
            var list = option.parentNode;
            if (list) list.querySelectorAll('.lang-option').forEach(function (row) { row.classList.remove('is-on'); });
            option.classList.add('is-on');
            var target = document.getElementById(option.getAttribute('data-target'));
            if (target) target.value = option.getAttribute('data-code') || '';
            if (option.getAttribute('data-submit') === '1') {
                var form = document.getElementById('replyLangForm');
                if (form) form.submit();
            }
        });
    });
    var chatPage = @json(route('whatsapp.chats'));
    try {
        var chatUrl = new URL(chatPage, window.location.href);
        if (chatUrl.origin !== window.location.origin) chatPage = window.location.pathname;
    } catch (e) {}
    var openChat = {{ $open ? (int) $open->id : 0 }};
    var quoteId = 0;
    var activeMessage = null;
    var selecting = false;
    var selectedIds = {};
    var openGroup = @json($group ? $group['jid'] : '');
    var listStamp = '';
    var seen = {{ ($open && $messages->last()) ? (int) $messages->last()->id : 0 }};
    var seenGroup = {{ ($group && $groupMessages->last()) ? (int) $groupMessages->last()->id : 0 }};
    var misses = 0;
    var polling = false;
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }
    function linkify(value) {
        var text = esc(value);
        return text.replace(/(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi, function (raw) {
            var display = raw.replace(/[.,);]+$/, '');
            var tail = raw.slice(display.length);
            var href = /^www\./i.test(display) ? 'https://' + display : display;
            return '<a class="chat-link" href="' + href + '" target="_blank" rel="noopener">' + display + '</a>' + tail;
        });
    }
    function tickHtml(tick) {
        if (!tick) return '';
        if (tick === 'failed') return '<span class="tick failed">! Not sent</span>';
        if (tick === 'read' || tick === 'played') return '<span class="tick ' + tick + '">✓✓ Read</span>';
        if (tick === 'delivered') return '<span class="tick delivered">✓✓ Delivered</span>';
        if (tick === 'sent') return '<span class="tick sent">✓ Sent</span>';
        if (tick === 'queued') return '<span class="tick queued">Sending</span>';
        return '<span class="tick">✓</span>';
    }
    function bubbleHtml(message) {
        var media = '';
        var photo = message.type === 'IMAGE' || message.type === 'STICKER';
        if (photo && message.media) {
            media = '<img class="chat-photo" alt="Photo" loading="lazy" decoding="async" src="' + esc(message.media) + '" onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'aside\',textContent:\'Photo\'}))">';
        } else if (message.type === 'AUDIO' && message.media) {
            media = '<audio controls preload="none" src="' + esc(message.media) + '"></audio>';
        } else if (message.type === 'VIDEO' && message.media) {
            media = '<video controls preload="none" src="' + esc(message.media) + '"></video>';
        } else if (message.media && (message.type === 'DOCUMENT' || message.type === 'LOCATION')) {
            media = '<a class="chat-file" target="_blank" rel="noopener" href="' + esc(message.media) + '">' + esc(message.name || 'Document') + '</a>';
        } else if (message.unavailable || message.reason) {
            media = '<span class="aside">' + esc(message.reason || 'WhatsApp did not send this file to MAI. It is still on the phone.') + '</span>';
        } else if (photo) {
            media = '<span class="aside">Photo</span>';
        } else if (message.type === 'VIDEO') {
            media = '<span class="aside">Video</span>';
        } else if (message.type === 'ALBUM') {
            media = '<span class="aside">Album</span>';
        } else if (message.type === 'EDIT') {
            media = '<span class="aside">Edited message</span>';
        } else if (!message.text) {
            media = '<span class="aside">No preview</span>';
        }
        if (message.view_once) {
            media += '<span class="aside">View once</span>';
        }
        var at = message.at || '';
        var dayKey = at && window.hubDayKey ? window.hubDayKey(at) : (message.day || '');
        var clock = at && window.hubClock ? window.hubClock(at) : (message.time || '');
        return '<div class="bubble ' + (message.out ? 'out' : 'in') + (message.deleted ? ' is-deleted' : '') + (message.starred ? ' is-star' : '') + '" data-id="' + message.id + '"'
            + (message.out ? ' data-out="1"' : '')
            + (message.can_edit ? ' data-edit="1"' : '')
            + (message.can_drop ? ' data-drop="1"' : '')
            + (message.starred ? ' data-star="1"' : '')
            + (message.deleted ? ' data-deleted="1"' : '')
            + (message.type === 'AUDIO' ? ' data-audio="1"' : '')
            + (message.needs_voice ? ' data-voice="' + message.id + '"' : '')
            + ' data-day="' + esc(dayKey) + '"' + (at ? ' data-at="' + esc(at) + '"' : '') + '>'
            + (message.who ? '<span class="who">' + esc(message.who) + '</span>' : '')
            + (message.forwarded ? '<span class="forwarded">Forwarded</span>' : '')
            + (message.quote ? '<span class="quote">' + linkify(message.quote) + '</span>' : '')
            + media
            + (message.text ? '<span class="body">' + linkify(message.text) + '</span>' : '')
            + (message.aside ? '<span class="aside">' + linkify(message.aside) + '</span>' : '')
            + '<time' + (at ? ' data-at="' + esc(at) + '"' : '') + '><span class="clock">' + esc(clock) + '</span>'
            + (message.edited ? '<span class="edited">Edited</span>' : '')
            + (message.starred ? '<span class="star">★</span>' : '')
            + (message.out ? tickHtml(message.tick) : '') + '</time>'
            + (String(message.id).indexOf('pending') === 0 ? '' : '<button type="button" class="bubble-more" aria-label="Message options">⋮</button>')
            + '</div>';
    }
    function nearBottom(node) {
        return node.scrollHeight - node.scrollTop - node.clientHeight < 140;
    }
    function placeMessages(messages, prepend) {
        stream = document.getElementById('chatStream');
        if (!stream || !messages || !messages.length) return;
        var stick = nearBottom(stream);
        var html = '';
        var previous = '';
        if (!prepend) {
            var last = stream.querySelector('.bubble:last-child');
            previous = last ? (last.getAttribute('data-day') || '') : '';
        }
        messages.forEach(function (message) {
            if (stream.querySelector('.bubble[data-id="' + message.id + '"]')) return;
            if (message.at && window.hubDayKey) {
                message.day = window.hubDayKey(message.at);
                message.day_label = window.hubDayLabel(message.at);
            }
            if (message.day && message.day !== previous) {
                html += '<div class="day-chip">' + esc(message.day_label || message.day) + '</div>';
                previous = message.day;
            }
            html += bubbleHtml(message);
            if (!prepend && message.id > seen) seen = message.id;
            if (openGroup && message.id > seenGroup) seenGroup = message.id;
        });
        if (!html) return;
        if (prepend) {
            var hold = stream.scrollHeight;
            var button = document.getElementById('loadEarlier');
            if (button) button.insertAdjacentHTML('afterend', html);
            else stream.insertAdjacentHTML('afterbegin', html);
            stream.scrollTop += stream.scrollHeight - hold;
        } else {
            var empty = stream.querySelector('.chats-empty');
            if (empty) empty.remove();
            stream.insertAdjacentHTML('beforeend', html);
            if (stick) stream.scrollTop = stream.scrollHeight;
        }
        applyLocalMarks();
        drainVoice();
    }
    function drainVoice() {
        if (drainVoice.busy || voiceHeard >= 6 || !openChat) return;
        var nodes = document.querySelectorAll('#chatStream .bubble[data-voice]');
        if (!nodes.length) return;
        var node = nodes[nodes.length - 1];
        drainVoice.busy = true;
        voiceHeard += 1;
        var data = new FormData();
        data.append('message', node.getAttribute('data-voice'));
        data.append('_token', csrfToken());
        fetch(@json(url('/admin/whatsapp/chats')) + '/' + openChat + '/voice', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) { return response.json(); })
            .then(function (payload) {
                node.removeAttribute('data-voice');
                if (payload && payload.message) node.outerHTML = bubbleHtml(payload.message);
            })
            .catch(function () { node.removeAttribute('data-voice'); })
            .then(function () { drainVoice.busy = false; drainVoice(); });
    }
    function paintList(items) {
        if (!list || !filters || !items) return;
        list.querySelectorAll('.chat-row.is-on').forEach(function (row) { row.classList.remove('is-on'); });
        var head = document.getElementById('chatNone');
        var ordered = items.slice().sort(function (a, b) {
            var left = Date.parse(a.at || '') || 0;
            var right = Date.parse(b.at || '') || 0;
            if (left === right) return (b.id || 0) - (a.id || 0);
            return right - left;
        });
        var after = head;
        ordered.forEach(function (item) {
            var isGroup = item.kind === 'group';
            var row = null;
            if (isGroup) {
                var groupRows = list.querySelectorAll('.chat-row[data-kind="group"]');
                for (var g = 0; g < groupRows.length; g++) {
                    if (groupRows[g].getAttribute('data-jid') === item.jid) { row = groupRows[g]; break; }
                }
            } else {
                row = list.querySelector('.chat-row[data-id="' + item.id + '"]');
            }
            if (!row) {
                row = document.createElement('a');
                row.className = 'chat-row';
                row.setAttribute('data-kind', isGroup ? 'group' : 'person');
                if (isGroup) {
                    row.setAttribute('data-jid', item.jid || '');
                    row.setAttribute('data-live', '1');
                } else {
                    row.setAttribute('data-id', item.id);
                }
            }
            row.href = isGroup ? (chatPage + '?group=' + encodeURIComponent(item.jid || '')) : (chatPage + '?chat=' + item.id);
            if (isGroup) row.setAttribute('data-live', '1');
            var unread = (openChat && String(item.id) === String(openChat)) ? 0 : (item.unread > 0 ? item.unread : 0);
            row.setAttribute('data-read', unread > 0 ? '0' : '1');
            row.setAttribute('data-name', (item.name + ' ' + (item.phone || '')).toLowerCase());
            if (openChat && item.id === openChat) row.classList.add('is-on');
            var badge = unread > 0 ? '<span class="chat-badge">' + unread + '</span>' : '';
            var state = unread > 0 ? '<span class="chat-state">Unread</span>' : '';
            var ai = item.mode === 'AI' ? '<span class="chat-ai">AI </span>' : '';
            var initial = isGroup ? 'G' : (item.name || 'W').charAt(0).toUpperCase();
            var when = item.at && window.hubListTime ? window.hubListTime(item.at) : (item.time || '');
            var typingLabel = item.typing === 'recording' ? 'recording a voice note…' : (item.typing === 'typing' ? 'typing…' : '');
            var preview = typingLabel ? '<span class="chat-preview is-typing">' + esc(typingLabel) + '</span>' : '<span class="chat-preview">' + ai + esc(item.preview || '') + '</span>';
            row.innerHTML = '<span class="chat-avatar' + (isGroup ? ' is-group' : '') + '"' + (isGroup ? '' : ' data-tone="' + (item.tone || 0) + '"') + '>' + esc(initial) + '</span><span class="chat-main"><span class="chat-top"><span class="chat-name">' + esc(item.name) + '</span>' + state + '<span class="chat-time"' + (item.at ? ' data-at="' + esc(item.at) + '"' : '') + '>' + esc(when) + '</span></span><span class="chat-bottom">' + preview + badge + '</span></span>';
            var next = after ? after.nextSibling : list.firstChild;
            if (next !== row) list.insertBefore(row, next);
            after = row;
        });
        var unread = 0;
        var read = 0;
        list.querySelectorAll('.chat-row[data-kind="person"]').forEach(function (row) {
            if (row.getAttribute('data-read') === '1') read += 1;
            else unread += 1;
        });
        var unreadBtn = filters.querySelector('[data-filter="unread"] span');
        var readBtn = filters.querySelector('[data-filter="read"] span');
        if (unreadBtn) unreadBtn.textContent = unread;
        if (readBtn) readBtn.textContent = read;
        document.querySelectorAll('.chats-thread .chat-row').forEach(function (row) { row.remove(); });
        applyList();
    }
    function paintTicks(ticks) {
        stream = document.getElementById('chatStream');
        if (!stream || !ticks) return;
        ticks.forEach(function (item) {
            var node = stream.querySelector('.bubble[data-id="' + item.id + '"] .tick');
            if (!node) return;
            node.className = 'tick' + (item.tick ? ' ' + item.tick : '');
            node.textContent = item.tick === 'failed' ? '! Not sent' : (item.tick === 'read' || item.tick === 'played' ? '✓✓ Read' : (item.tick === 'delivered' ? '✓✓ Delivered' : (item.tick === 'sent' ? '✓ Sent' : (item.tick === 'queued' ? 'Sending' : '✓'))));
        });
    }
    var openingChat = false;
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    function setCsrf(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        var inputs = document.querySelectorAll('input[name="_token"]');
        for (var i = 0; i < inputs.length; i++) inputs[i].value = token;
    }
    function refreshCsrf() {
        return fetch(chatPage + '?poll=1&csrf=1', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) { return response.json(); }).then(function (data) {
            if (data && data.csrf) setCsrf(data.csrf);
            return !!(data && data.csrf);
        }).catch(function () { return false; });
    }
    function postHub(url, data, attempt) {
        if (attempt > 0 && data && typeof data.set === 'function') data.set('_token', csrfToken());
        return fetch(url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) {
            if (response.status === 419 && attempt < 1) {
                return refreshCsrf().then(function () { return postHub(url, data, attempt + 1); });
            }
            return response.json().then(function (payload) {
                payload._ok = response.ok;
                return payload;
            }, function () {
                return { ok: false, _ok: false, error: response.status === 419 ? 'This page expired. Send once more.' : 'Send failed.' };
            });
        });
    }
    function ensureThread() {
        if (document.getElementById('threadName') && document.getElementById('chatStream')) return;
        var shell = document.getElementById('hubThreadShell');
        var panel = document.querySelector('.chats-thread');
        if (!shell || !panel) return;
        panel.innerHTML = shell.innerHTML;
    }
    function paintThread(data) {
        var stream = document.getElementById('chatStream');
        if (!stream) return;
        var html = '';
        if (data.has_earlier && data.messages && data.messages.length) {
            html += '<button type="button" class="load-earlier" id="loadEarlier" data-before="' + data.messages[0].id + '">Earlier messages</button>';
        }
        var previous = '';
        (data.messages || []).forEach(function (message) {
            if (message.day && message.day !== previous) {
                html += '<div class="day-chip">' + esc(message.day_label || message.day) + '</div>';
                previous = message.day;
            }
            html += bubbleHtml(message);
            if (message.id > seen) seen = message.id;
        });
        if (!html) html = '<div class="chats-empty"><strong>No messages yet</strong>Send the first message below.</div>';
        stream.innerHTML = html;
        stream.scrollTop = stream.scrollHeight;
        var earlier = document.getElementById('loadEarlier');
        if (earlier) {
            earlier.addEventListener('click', function () {
                var before = earlier.getAttribute('data-before');
                if (before) poll('&before=' + before);
            });
        }
    }
    function chatWarning(chat) {
        var dest = chat.destination || {};
        var staff = (chat.staff && chat.staff.name) ? chat.staff.name : 'your language';
        if (dest.off || (dest.code && !dest.enabled)) return '';
        if (dest.locked && dest.name) {
            return 'Everything you send in this chat goes out in ' + dest.name + ', until you change it. If they write in ' + staff + ', you still see it that way.';
        }
        if (dest.enabled && !dest.code) {
            return 'This chat detects their language. If they write in ' + staff + ', you still see it that way.';
        }
        return '';
    }
    function showChatNote(text) {
        var thread = document.querySelector('.chats-thread');
        if (!thread) return;
        var note = document.getElementById('chatNote');
        if (!text) {
            thread.querySelectorAll('.chat-note').forEach(function (node) { node.remove(); });
            return;
        }
        if (!note || !thread.contains(note)) {
            thread.querySelectorAll('.chat-note').forEach(function (node) { node.remove(); });
            note = document.createElement('div');
            note.className = 'chat-note';
            note.id = 'chatNote';
            var head = thread.querySelector('.chats-head');
            if (head) head.insertAdjacentElement('afterend', note);
            else thread.insertAdjacentElement('afterbegin', note);
        }
        note.textContent = text;
    }
    function applyChat(data) {
        var chat = data.chat || {};
        openChat = chat.id || 0;
        openGroup = '';
        seen = 0;
        var name = document.getElementById('threadName');
        var phone = document.getElementById('threadPhone');
        var avatar = document.getElementById('threadAvatar');
        if (name) name.textContent = chat.name || 'WhatsApp';
        if (phone) phone.innerHTML = esc(chat.phone || '') + ' <span class="mode-pill ' + (chat.mode === 'AI' ? '' : 'is-human') + '">' + (chat.mode === 'AI' ? 'AI' : 'Human') + '</span>';
        if (avatar) {
            avatar.textContent = (chat.name || 'W').charAt(0).toUpperCase();
            avatar.setAttribute('data-tone', chat.tone || 0);
        }
        var call = document.getElementById('threadCall');
        if (call) {
            if (chat.digits) { call.href = 'tel:+' + chat.digits; call.hidden = false; }
            else call.hidden = true;
        }
        function setAction(id, url) {
            var form = document.getElementById(id);
            if (form && url) form.setAttribute('action', url);
        }
        setAction('threadTranslate', chat.translator);
        setAction('threadAi', chat.ai);
        setAction('threadHuman', chat.human);
        setAction('replyLangForm', chat.language);
        setAction('chatCompose', chat.reply);
        var translateBtn = document.querySelector('#threadTranslate button');
        var aiBtn = document.querySelector('#threadAi button');
        var humanBtn = document.querySelector('#threadHuman button');
        if (translateBtn) translateBtn.classList.toggle('is-on', !!chat.translate);
        if (aiBtn) aiBtn.classList.toggle('is-on', chat.mode === 'AI');
        if (humanBtn) humanBtn.classList.toggle('is-on', chat.mode !== 'AI');
        var forms = document.querySelectorAll('.chats-thread form');
        if (forms.length && chat.reply) {
            forms.forEach(function (form) {
                var action = form.getAttribute('action') || '';
                if (action.indexOf('/reply') !== -1) form.setAttribute('action', chat.reply);
                if (action.indexOf('/translator') !== -1) form.setAttribute('action', chat.translator);
                if (action.indexOf('/enable-ai') !== -1) form.setAttribute('action', chat.ai);
                if (action.indexOf('/takeover') !== -1) form.setAttribute('action', chat.human);
                if (action.indexOf('/language') !== -1 && action.indexOf('language-settings') === -1) form.setAttribute('action', chat.language);
            });
        }
        var replyName = document.getElementById('threadReplyName');
        var dest = chat.destination || {};
        var replyCode = 'off';
        var label = 'Off';
        if (dest.off || (dest.code && !dest.enabled)) {
            replyCode = 'off';
            label = 'Off';
        } else if (dest.locked && dest.name) {
            replyCode = dest.code || 'off';
            label = dest.name;
        } else if (dest.enabled && !dest.code) {
            replyCode = 'auto';
            label = 'Detect';
        } else if (chat.default_reply && chat.default_reply.name) {
            replyCode = chat.default_reply.code || '';
            label = chat.default_reply.name;
        }
        if (replyName) replyName.textContent = label;
        var replyTitle = document.getElementById('replyLangTitle');
        if (replyTitle) replyTitle.textContent = 'Reply to ' + (chat.name || 'this chat');
        var kept = document.getElementById('replyLangKept');
        if (kept) {
            if ((dest.off || (dest.code && !dest.enabled)) && dest.name) {
                kept.hidden = false;
                kept.textContent = dest.name + ' stays saved for this person. Turn translation on to use it again.';
            } else {
                kept.hidden = true;
            }
        }
        showChatNote(chatWarning(chat));
        var replyValue = document.getElementById('replyLangValue');
        if (replyValue) replyValue.value = replyCode;
        document.querySelectorAll('#replyList .lang-option').forEach(function (option) {
            option.classList.toggle('is-on', (option.getAttribute('data-code') || '') === replyCode);
        });
        var composeField = document.getElementById('chatBody');
        if (composeField && !composeField.value) composeField.placeholder = '';
        paintThread(data);
        var app = document.querySelector('.chats-app');
        if (app) app.classList.add('has-open');
        openId = openChat;
        document.querySelectorAll('.chats-thread .chat-row').forEach(function (row) { row.remove(); });
        if (list) {
            list.querySelectorAll('.chat-row.is-on').forEach(function (row) { row.classList.remove('is-on'); });
            var current = list.querySelector('.chat-row[data-id="' + openChat + '"]');
            if (current) {
                current.classList.add('is-on');
                current.setAttribute('data-read', '1');
                var badge = current.querySelector('.chat-badge');
                if (badge) badge.remove();
                var state = current.querySelector('.chat-state');
                if (state) state.remove();
            }
            var unread = 0;
            var read = 0;
            list.querySelectorAll('.chat-row[data-kind="person"]').forEach(function (row) {
                if (row.getAttribute('data-read') === '1') read += 1;
                else unread += 1;
            });
            var unreadBtn = document.querySelector('#chatFilters [data-filter="unread"] span');
            var readBtn = document.querySelector('#chatFilters [data-filter="read"] span');
            if (unreadBtn) unreadBtn.textContent = unread;
            if (readBtn) readBtn.textContent = read;
        }
        applyList();
    }
    function rememberList() {
        if (!list) return;
        savedListScroll = list.scrollTop;
        try {
            sessionStorage.setItem('chatListScroll', String(list.scrollTop));
            sessionStorage.setItem('chatListQuery', search ? search.value : '');
            sessionStorage.setItem('chatListFilter', filter || 'all');
        } catch (e) {}
    }
    function restoreList() {
        try {
            var keptQuery = sessionStorage.getItem('chatListQuery');
            var keptScroll = sessionStorage.getItem('chatListScroll');
            if (search && keptQuery !== null) search.value = keptQuery;
            if (keptScroll !== null) savedListScroll = parseInt(keptScroll, 10) || 0;
        } catch (e) {}
        applyList();
        if (list) list.scrollTop = savedListScroll;
    }
    function showChatList(fromHistory) {
        var app = document.querySelector('.chats-app');
        if (app) app.classList.remove('has-open');
        openChat = 0;
        openGroup = '';
        if (list) list.querySelectorAll('.chat-row.is-on').forEach(function (row) { row.classList.remove('is-on'); });
        restoreList();
        if (!fromHistory && window.history && history.pushState) {
            var url = new URL(chatPage, window.location.href);
            url.searchParams.delete('chat');
            url.searchParams.delete('group');
            if (filter && filter !== 'all') url.searchParams.set('filter', filter);
            else url.searchParams.delete('filter');
            history.pushState({ chat: 0 }, '', url.pathname + url.search);
        }
    }
    function openChatInPlace(id, fromHistory) {
        if (!id || openingChat) return;
        openingChat = true;
        var app = document.querySelector('.chats-app');
        if (app) app.classList.add('has-open');
        ensureThread();
        var stream = document.getElementById('chatStream');
        if (stream) stream.innerHTML = '<div class="chats-empty"><strong>Opening</strong></div>';
        fetch(chatPage + '?open=1&chat=' + encodeURIComponent(id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) { if (!response.ok) throw new Error('open'); return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) throw new Error('open');
                applyChat(data);
                if (!fromHistory && window.history && history.pushState) {
                    var url = new URL(chatPage, window.location.href);
                    url.searchParams.set('chat', id);
                    url.searchParams.delete('group');
                    if (filter && filter !== 'all') url.searchParams.set('filter', filter);
                    history.pushState({ chat: id }, '', url.pathname + url.search);
                }
            })
            .catch(function () { window.location.href = chatPage + '?chat=' + encodeURIComponent(id); })
            .then(function () { openingChat = false; });
    }
    function sendChat(form) {
        if (form.getAttribute('data-sending') === '1') return;
        var field = form.querySelector('#chatBody') || form.querySelector('textarea');
        var picker = form.querySelector('#chatFile') || form.querySelector('input[type="file"]');
        var typed = field ? field.value.trim() : '';
        var picked = picker && picker.files && picker.files.length;
        var held = form.hubFile || null;
        if (!typed && !picked && !held) return;
        form.setAttribute('data-sending', '1');
        var button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        var token = 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
        var pendingId = 'pending-' + token;
        var sentChat = String(openChat || '');
        var actionMatch = (form.getAttribute('action') || '').match(/chats\/(\d+)\/reply/);
        if (actionMatch) sentChat = actionMatch[1];
        var data = new FormData(form);
        if (!picked && held) data.append('file', held, held.name || 'pasted-file');
        data.append('send_token', token);
        if (quoteId) data.append('quote_id', quoteId);
        if (typed) {
            placeMessages([{ id: pendingId, out: true, text: typed, time: '', at: new Date().toISOString(), tick: 'queued', type: 'TEXT', media: '', name: '', aside: '', day: '' }], false);
        }
        if (field) field.value = '';
        if (picker) picker.value = '';
        showPicked(null);
        clearQuote();
        postHub(form.getAttribute('action'), data, 0)
            .then(function (payload) {
                if (String(openChat || '') !== sentChat) return;
                var node = document.querySelector('.bubble[data-id="' + pendingId + '"]');
                function swapPending(message) {
                    if (!message) return;
                    var existing = document.querySelector('.bubble[data-id="' + message.id + '"]');
                    if (node && existing && existing !== node) node.remove();
                    else if (node) node.outerHTML = bubbleHtml(message);
                    else if (!existing) placeMessages([message], false);
                    if (message.id > seen) seen = message.id;
                }
                if (payload && !payload.ok && payload.error && !payload.notice) payload.notice = payload.error;
                if (payload && payload.notice) {
                    var note = document.querySelector('.chats-thread .chat-note');
                    if (!note) {
                        note = document.createElement('div');
                        note.className = 'chat-note';
                        var head = document.querySelector('.chats-head');
                        if (head) head.insertAdjacentElement('afterend', note);
                    }
                    if (note) note.textContent = payload.notice;
                }
                if (!payload || !payload.ok) {
                    if (payload && payload.message && typeof payload.message === 'object') swapPending(payload.message);
                    else if (node) {
                        var tick = node.querySelector('.tick');
                        if (tick) { tick.className = 'tick failed'; tick.textContent = '! Not sent'; }
                    }
                    if (field && typed) field.value = typed;
                    return;
                }
                if (payload.message) swapPending(payload.message);
                else if (node) node.remove();
                poll();
            })
            .catch(function () {
                var node = document.querySelector('.bubble[data-id="' + pendingId + '"]');
                if (node) {
                    var tick = node.querySelector('.tick');
                    if (tick) { tick.className = 'tick failed'; tick.textContent = '! Not sent'; }
                }
                if (field && typed) field.value = typed;
            })
            .then(function () {
                form.removeAttribute('data-sending');
                if (button) button.disabled = false;
            });
    }
    function paintTyping(state) {
        var line = document.getElementById('typingLine');
        var label = state === 'recording' ? 'recording a voice note…' : (state === 'typing' ? 'typing…' : '');
        if (line) {
            line.hidden = label === '';
            line.textContent = label;
        }
        if (!list || !openChat) return;
        var preview = list.querySelector('.chat-row[data-id="' + openChat + '"] .chat-preview');
        if (!preview) return;
        if (label) {
            if (!preview.getAttribute('data-preview')) preview.setAttribute('data-preview', preview.textContent);
            preview.textContent = label;
        } else if (preview.getAttribute('data-preview') !== null) {
            preview.textContent = preview.getAttribute('data-preview');
            preview.removeAttribute('data-preview');
        }
    }
    var typingSent = 0;
    function notifyTyping(state) {
        if (!openChat || openGroup) return;
        var now = Date.now();
        if (now - typingSent < 4000) return;
        typingSent = now;
        var data = new FormData();
        data.append('state', state);
        data.append('_token', csrfToken());
        fetch(chatPage + '/' + openChat + '/typing', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).catch(function () {});
    }
    var typingBox = document.getElementById('chatBody');
    if (typingBox) {
        typingBox.addEventListener('input', function () {
            if (typingBox.value.trim() === '') return;
            notifyTyping('composing');
        });
    }
    function poll(extra) {
        if (polling || document.hidden) return;
        polling = true;
        var url = chatPage + '?poll=1';
        if (openChat) url += '&chat=' + openChat + '&after=' + seen;
        if (openGroup) url += '&group=' + encodeURIComponent(openGroup) + '&after_group=' + seenGroup;
        if (!extra && listStamp) url += '&stamp=' + encodeURIComponent(listStamp);
        if (extra) url += extra;
        var live = document.getElementById('chatLive');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { if (!response.ok) throw new Error('poll'); return response.json(); })
            .then(function (data) {
                if (data && data.csrf) setCsrf(data.csrf);
                stream = document.getElementById('chatStream');
                misses = 0;
                if (live) live.classList.remove('is-on');
                if (extra) {
                    placeMessages(data.earlier || [], true);
                    var button = document.getElementById('loadEarlier');
                    if (button) {
                        if (data.history === 'pending') {
                            button.textContent = 'Looking for older messages';
                            button.disabled = true;
                            setTimeout(function () {
                                button.disabled = false;
                                button.textContent = 'Earlier messages';
                                poll('&before=' + button.getAttribute('data-before'));
                            }, 2000);
                        } else if (data.history === 'failed') {
                            button.textContent = 'WhatsApp did not return older messages. Try again.';
                            button.disabled = false;
                        } else if (data.history === 'unavailable') {
                            button.textContent = 'WhatsApp did not send older messages for this chat. They stay on the phone and in WhatsApp Web.';
                            button.disabled = true;
                        } else if (data.has_earlier && data.earlier && data.earlier.length) button.setAttribute('data-before', data.earlier[0].id);
                        else button.remove();
                    }
                } else {
                    placeMessages(openGroup ? (data.group_messages || []) : (data.messages || []), false);
                    (data.translated || []).forEach(function (message) {
                        if (!stream) return;
                        var bubble = stream.querySelector('.bubble[data-id="' + message.id + '"]');
                        if (!bubble || !message.text) return;
                        var body = bubble.querySelector('.body');
                        if (!body) {
                            body = document.createElement('span');
                            body.className = 'body';
                            var time = bubble.querySelector('time');
                            bubble.insertBefore(body, time || null);
                        }
                        body.innerHTML = linkify(message.text);
                        var aside = bubble.querySelector('.aside');
                        if (message.aside) {
                            if (!aside) {
                                aside = document.createElement('span');
                                aside.className = 'aside';
                                body.insertAdjacentElement('afterend', aside);
                            }
                            aside.innerHTML = linkify(message.aside);
                        }
                    });
                    paintTicks(data.ticks || []);
                    if (data.list !== false) paintList(data.items || []);
                    if (data.stamp) listStamp = data.stamp;
                    paintTyping(data.typing || '');
                }
            })
            .catch(function () {
                misses += 1;
                if (live && misses > 1) live.classList.add('is-on');
            })
            .then(function () { polling = false; });
    }
    var earlier = document.getElementById('loadEarlier');
    if (earlier) {
        earlier.addEventListener('click', function () {
            var before = earlier.getAttribute('data-before');
            if (before) poll('&before=' + before);
        });
    }
    if (liveReady()) setInterval(poll, 2000);
    var installBtn = document.getElementById('hubInstall');
    var deferredInstall = null;
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches && installBtn) installBtn.hidden = true;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstall = event;
        if (installBtn) installBtn.hidden = false;
    });
    if (installBtn) installBtn.addEventListener('click', function () {
        if (deferredInstall) { deferredInstall.prompt(); return; }
        var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
        window.alert(ios
            ? 'On iPhone, tap Share, then Add to Home Screen. The icon is Whats MAI.'
            : 'On a Mac, Chrome shows Install in the address bar. Safari: File, then Add to Dock. No App Store.');
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    window.addEventListener('focus', function () { poll(); });
    function liveReady() { return true; }
    function clearQuote() {
        quoteId = 0;
        var bar = document.getElementById('quoteBar');
        if (bar) bar.hidden = true;
    }
    function setQuote(id, text) {
        quoteId = id;
        var bar = document.getElementById('quoteBar');
        var label = document.getElementById('quoteText');
        if (label) label.textContent = text || 'Reply';
        if (bar) bar.hidden = false;
        var field = document.getElementById('chatBody');
        if (field) field.focus();
    }
    function messageBody(bubble) {
        var body = bubble ? bubble.querySelector('.body') : null;
        return body ? body.textContent : '';
    }
    function messagePost(id, action, fields) {
        var data = new FormData();
        data.append('_token', csrfToken());
        Object.keys(fields || {}).forEach(function (key) { data.append(key, fields[key]); });
        return fetch(chatPage + '/' + openChat + '/messages/' + id + '/' + action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) {
            return response.json().then(function (payload) { payload._ok = response.ok; return payload; });
        });
    }
    function replaceBubble(message) {
        if (!message) return;
        var node = document.querySelector('#chatStream .bubble[data-id="' + message.id + '"]');
        var html = bubbleHtml(message);
        if (node) node.outerHTML = html;
        else placeMessages([message], false);
        applyLocalMarks();
    }
    function resetMessageSheet() {
        var actions = document.getElementById('msgActions');
        var edit = document.getElementById('msgEdit');
        var drop = document.getElementById('msgDelete');
        var react = document.getElementById('msgReact');
        var info = document.getElementById('msgInfo');
        if (actions) actions.hidden = false;
        if (edit) edit.hidden = true;
        if (drop) drop.hidden = true;
        if (react) react.hidden = true;
        if (info) info.hidden = true;
    }
    function closeMessageMenu() {
        var menu = document.getElementById('msgMenu');
        if (!menu) return;
        menu.classList.remove('is-on');
        menu.hidden = true;
        resetMessageSheet();
    }
    function positionMessageMenu(anchor) {
        var menu = document.getElementById('msgMenu');
        if (!menu || !anchor) return;
        var rect = anchor.getBoundingClientRect();
        var width = menu.offsetWidth || 220;
        var height = menu.offsetHeight || 420;
        var left = rect.right - width;
        if (left < 8) left = Math.min(rect.left, window.innerWidth - width - 8);
        if (left + width > window.innerWidth - 8) left = Math.max(8, window.innerWidth - width - 8);
        var top = rect.bottom + 4;
        if (top + height > window.innerHeight - 8) top = Math.max(8, rect.top - height - 4);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }
    function showMenuPane(id) {
        document.getElementById('msgActions').hidden = true;
        document.getElementById(id).hidden = false;
        var menu = document.getElementById('msgMenu');
        if (menu) positionMessageMenu(menu._anchor || activeMessage);
    }
    function openMessageMenu(bubble, anchor) {
        if (!bubble || openGroup || !openChat) return;
        activeMessage = bubble;
        resetMessageSheet();
        var deleted = bubble.getAttribute('data-deleted') === '1';
        var text = messageBody(bubble);
        document.querySelectorAll('#msgActions button').forEach(function (button) {
            var act = button.getAttribute('data-act');
            var show = true;
            if (deleted && act !== 'delete' && act !== 'info') show = false;
            if (act === 'edit' && bubble.getAttribute('data-edit') !== '1') show = false;
            if (act === 'copy' && !text) show = false;
            if (act === 'keep') button.textContent = bubble.getAttribute('data-star') === '1' ? 'Undo keep' : 'Keep';
            if (act === 'pin') button.textContent = bubble.getAttribute('data-pin') === '1' ? 'Unpin' : 'Pin';
            button.hidden = !show;
        });
        var everyone = document.querySelector('#msgDelete [data-act="delete-all"]');
        if (everyone) everyone.hidden = bubble.getAttribute('data-drop') !== '1';
        var menu = document.getElementById('msgMenu');
        if (!menu) return;
        menu._anchor = anchor || bubble;
        menu.hidden = false;
        menu.classList.add('is-on');
        positionMessageMenu(menu._anchor);
    }
    function localStore(key) {
        try { return JSON.parse(sessionStorage.getItem(key) || '{}'); } catch (e) { return {}; }
    }
    function localSave(key, value) {
        try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (e) {}
    }
    function paintReaction(bubble, emoji) {
        if (!bubble) return;
        var mark = bubble.querySelector('.react');
        if (!emoji) { if (mark) mark.remove(); return; }
        if (!mark) {
            mark = document.createElement('span');
            mark.className = 'react';
            var time = bubble.querySelector('time');
            if (time) time.insertBefore(mark, time.firstChild);
            else bubble.appendChild(mark);
        }
        mark.textContent = emoji;
    }
    function rememberReaction(bubble, emoji) {
        var id = bubble.getAttribute('data-id');
        var all = localStore('mai-reacts-' + openChat);
        if (all[id] === emoji) delete all[id];
        else all[id] = emoji;
        localSave('mai-reacts-' + openChat, all);
        paintReaction(bubble, all[id] || '');
    }
    function pinnedBar() {
        var bar = document.getElementById('pinnedBar');
        var stream = document.getElementById('chatStream');
        if (!bar && stream && stream.parentNode) {
            bar = document.createElement('div');
            bar.id = 'pinnedBar';
            bar.hidden = true;
            stream.parentNode.insertBefore(bar, stream);
        }
        return bar;
    }
    function renderPins() {
        var bar = pinnedBar();
        if (!bar) return;
        var pins = localStore('mai-pins-' + openChat);
        document.querySelectorAll('#chatStream .bubble').forEach(function (bubble) {
            if (pins[bubble.getAttribute('data-id')]) bubble.setAttribute('data-pin', '1');
            else bubble.removeAttribute('data-pin');
        });
        var ids = Object.keys(pins);
        if (!ids.length) { bar.hidden = true; bar.innerHTML = ''; return; }
        bar.hidden = false;
        bar.innerHTML = ids.map(function (id) {
            return '<button type="button" data-pin-jump="' + esc(id) + '">Pinned · ' + esc(pins[id]) + '</button>';
        }).join('');
    }
    function togglePin(bubble) {
        var id = bubble.getAttribute('data-id');
        var pins = localStore('mai-pins-' + openChat);
        if (pins[id]) delete pins[id];
        else pins[id] = (messageBody(bubble) || 'Message').slice(0, 80);
        localSave('mai-pins-' + openChat, pins);
        renderPins();
    }
    function paintSelect() {
        var count = 0;
        document.querySelectorAll('#chatStream .bubble').forEach(function (bubble) {
            var on = !!selectedIds[bubble.getAttribute('data-id')];
            bubble.classList.toggle('is-picked', on);
            if (on) count += 1;
        });
        var bar = document.getElementById('selectBar');
        var label = document.getElementById('selectCount');
        if (label) label.textContent = count + ' selected';
        if (bar) bar.hidden = !selecting;
    }
    function startSelect(id) {
        selecting = true;
        selectedIds = {};
        if (id) selectedIds[id] = true;
        closeMessageMenu();
        paintSelect();
    }
    function stopSelect() {
        selecting = false;
        selectedIds = {};
        paintSelect();
    }
    function toggleSelected(id) {
        if (!id) return;
        if (selectedIds[id]) delete selectedIds[id];
        else selectedIds[id] = true;
        paintSelect();
    }
    function deleteSelected() {
        var ids = Object.keys(selectedIds);
        var next = function () {
            var id = ids.shift();
            if (!id) { stopSelect(); return; }
            messagePost(id, 'delete', { everyone: '0' }).then(function (payload) {
                if (payload && payload._ok && payload.removed) {
                    var node = document.querySelector('#chatStream .bubble[data-id="' + id + '"]');
                    if (node) node.remove();
                } else if (payload && payload._ok && payload.message) replaceBubble(payload.message);
                next();
            }).catch(function () { next(); });
        };
        next();
    }
    function applyLocalMarks() {
        var reacts = localStore('mai-reacts-' + openChat);
        document.querySelectorAll('#chatStream .bubble').forEach(function (bubble) {
            paintReaction(bubble, reacts[bubble.getAttribute('data-id')] || '');
        });
        renderPins();
        if (selecting) paintSelect();
    }
    function askLine(mine, text) {
        var row = document.createElement('div');
        row.className = 'ask-line ' + (mine ? 'is-mine' : 'is-them');
        var who = document.createElement('span');
        who.textContent = mine ? 'You' : 'Them';
        var body = document.createElement('p');
        body.textContent = text.length > 180 ? text.slice(0, 177) + '…' : text;
        row.appendChild(who);
        row.appendChild(body);
        return row;
    }
    function bubbleAskText(bubble) {
        var text = messageBody(bubble);
        if ((bubble.getAttribute('data-audio') === '1' || bubble.querySelector('audio')) && !text) text = 'Voice note';
        if (!text && bubble.querySelector('img.chat-photo')) text = 'Photo';
        if (!text && bubble.querySelector('a.chat-file')) text = 'File';
        return text;
    }
    function paintAskPreview(bubbles) {
        var box = document.getElementById('askPreview');
        if (!box) return;
        box.innerHTML = '';
        var shown = 0;
        (bubbles || []).forEach(function (bubble) {
            var text = bubbleAskText(bubble);
            var photo = bubble.querySelector('img.chat-photo');
            if (!text && !photo) return;
            var row = askLine(bubble.getAttribute('data-out') === '1', text || 'Photo');
            if (photo && photo.getAttribute('src')) {
                var img = document.createElement('img');
                img.className = 'ask-photo';
                img.alt = photo.getAttribute('alt') || 'Photo';
                img.src = photo.getAttribute('src');
                row.appendChild(img);
            }
            box.appendChild(row);
            shown += 1;
        });
        if (!shown) box.innerHTML = '<p class="ask-empty">No messages in this chat yet.</p>';
        var thread = document.getElementById('askThread');
        if (thread) thread.scrollTop = thread.scrollHeight;
    }
    function paintRecentChat() {
        var bubbles = Array.prototype.slice.call(document.querySelectorAll('#chatStream .bubble'));
        paintAskPreview(bubbles);
    }
    function fillTranscribe(status, text) {
        var statusNode = document.getElementById('transcribeStatus');
        if (statusNode) statusNode.textContent = status || '';
        var box = document.getElementById('askPreview');
        if (!box) return;
        if (!text) {
            box.innerHTML = '<p class="ask-empty"></p>';
            var empty = box.querySelector('.ask-empty');
            if (empty) empty.textContent = status || 'Nothing to show yet.';
            return;
        }
        var mine = activeMessage && activeMessage.getAttribute('data-out') === '1';
        box.innerHTML = '';
        box.appendChild(askLine(mine, text));
    }
    function clearAskFile() {
        var input = document.getElementById('askFile');
        var name = document.getElementById('askFileName');
        if (input) input.value = '';
        if (name) {
            name.hidden = true;
            var span = name.querySelector('span');
            if (span) span.textContent = '';
        }
    }
    function askAttachment() {
        var input = document.getElementById('askFile');
        return input && input.files && input.files[0] ? input.files[0] : null;
    }
    function openTranscribe(bubble) {
        if (!bubble) return;
        activeMessage = bubble;
        var sheet = document.getElementById('transcribeSheet');
        var result = document.getElementById('transcribeResult');
        var use = document.getElementById('transcribeUse');
        var prompt = document.getElementById('transcribePrompt');
        var ask = document.getElementById('transcribeAsk');
        var isAudio = bubble.getAttribute('data-audio') === '1' || !!bubble.querySelector('audio');
        var existing = messageBody(bubble);
        if (sheet) sheet.setAttribute('data-kind', isAudio ? 'audio' : 'text');
        var scope = sheet ? sheet.querySelector('.ask-scope') : null;
        if (scope) scope.textContent = 'Mbole AI · this message';
        if (result) { result.hidden = true; result.textContent = ''; }
        if (use) use.hidden = true;
        if (prompt) prompt.value = '';
        clearAskFile();
        if (ask) { ask.disabled = isAudio && !existing; ask.textContent = 'MAI'; }
        closeMessageMenu();
        openSheet('transcribeSheet');
        if (prompt) setTimeout(function () { prompt.focus(); }, 40);
        if (!isAudio) {
            fillTranscribe(existing ? 'This message as text' : 'This message has no text.', existing);
            return;
        }
        fillTranscribe(existing ? 'Voice note as text' : 'Hearing the voice note…', existing);
        var data = new FormData();
        data.append('message', bubble.getAttribute('data-id'));
        data.append('_token', csrfToken());
        fetch(chatPage + '/' + openChat + '/voice', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) {
            return response.json().then(function (payload) { payload._ok = response.ok; return payload; }).catch(function () { return { _ok: false }; });
        }).then(function (payload) {
            if (ask) ask.disabled = false;
            if (payload && payload._ok && payload.message) {
                replaceBubble(payload.message);
                var heard = payload.message.text || payload.message.aside || existing;
                fillTranscribe('Voice note as text', heard);
                var fresh = document.querySelector('#chatStream .bubble[data-id="' + payload.message.id + '"]');
                if (fresh) activeMessage = fresh;
                return;
            }
            if (!existing) fillTranscribe((payload && payload.error) || 'Could not hear that voice note.', '');
        }).catch(function () {
            if (ask) ask.disabled = false;
            if (!existing) fillTranscribe('Could not hear that voice note.', '');
        });
    }
    function composerDraft(button) {
        var form = button && button.closest ? button.closest('form') : document.getElementById('chatCompose');
        var field = form ? (form.querySelector('textarea[name="body"]') || form.querySelector('textarea')) : null;
        if (!field) field = document.querySelector('.chats-thread textarea[name="body"]');
        var picker = form ? form.querySelector('input[type="file"]') : null;
        var file = form && form.hubFile ? form.hubFile : (picker && picker.files && picker.files[0] ? picker.files[0] : null);
        return { form: form, field: field, text: field ? field.value.trim() : '', file: file };
    }
    function openAsk(bubble, source) {
        if (bubble) {
            openTranscribe(bubble);
            return;
        }
        if (!openChat) return;
        activeMessage = null;
        var sheet = document.getElementById('transcribeSheet');
        var result = document.getElementById('transcribeResult');
        var use = document.getElementById('transcribeUse');
        var prompt = document.getElementById('transcribePrompt');
        var ask = document.getElementById('transcribeAsk');
        if (sheet) sheet.setAttribute('data-kind', 'chat');
        var personName = (document.getElementById('threadName') || {}).textContent || '';
        personName = personName.replace(/\s+/g, ' ').trim();
        var scope = sheet ? sheet.querySelector('.ask-scope') : null;
        var heading = sheet ? sheet.querySelector('h3') : null;
        if (heading) heading.textContent = personName || 'MAI';
        if (scope) scope.textContent = personName ? ('Chat with ' + personName) : 'This chat';
        if (result) { result.hidden = true; result.textContent = ''; }
        if (use) use.hidden = true;
        var draft = source || composerDraft(null);
        var typed = draft.text || '';
        if (prompt) prompt.value = typed;
        if (ask) { ask.disabled = false; ask.textContent = 'MAI'; }
        clearAskFile();
        if (draft.file) showAskFile(draft.file);
        var statusNode = document.getElementById('transcribeStatus');
        if (statusNode) statusNode.textContent = personName ? ('Ask about the chat with ' + personName + ', or ask anything else.') : 'Ask about this chat, or ask anything else.';
        paintRecentChat();
        openSheet('transcribeSheet');
        if (typed || draft.file) {
            if (draft.field) draft.field.value = '';
            if (draft.form) {
                var picker = draft.form.querySelector('input[type="file"]');
                if (picker) picker.value = '';
                draft.form.hubFile = null;
                showPicked(null);
            }
            runAsk(typed, draft.file);
            return;
        }
        if (prompt) setTimeout(function () { prompt.focus(); }, 40);
    }
    function runAsk(instruction, carriedFile) {
        var ask = document.getElementById('transcribeAsk');
        if (!openChat || (ask && ask.disabled)) return;
        var prompt = document.getElementById('transcribePrompt');
        var file = carriedFile || askAttachment();
        instruction = (instruction || (prompt ? prompt.value : '')).trim();
        if (!instruction && !file) { window.alert('Write what you want to ask MAI.'); return; }
        if (!instruction) instruction = 'Read the attached file and explain it.';
        if (file && file.size > ((/\.(png|jpe?g|webp|gif)$/i).test(file.name) ? 1500000 : 4000000)) {
            window.alert('That file is too large.');
            return;
        }
        if (prompt) prompt.value = '';
        clearAskFile();
        var asked = appendAsked(instruction, file);
        if (prompt) prompt.focus();
        var result = document.getElementById('transcribeResult');
        var use = document.getElementById('transcribeUse');
        var sheet = document.getElementById('transcribeSheet');
        if (ask) { ask.disabled = true; ask.textContent = '…'; }
        var data = new FormData();
        data.append('instruction', instruction);
        if (activeMessage) data.append('message', activeMessage.getAttribute('data-id'));
        else if (sheet && sheet.getAttribute('data-kind') === 'general') data.append('scope', 'general');
        if (file) data.append('file', file);
        data.append('_token', csrfToken());
        fetch(chatPage + '/' + openChat + '/ask', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) {
            return response.json().then(function (payload) { payload._ok = response.ok; return payload; }).catch(function () { return { _ok: false }; });
        }).then(function (payload) {
            if (ask) { ask.disabled = false; ask.textContent = 'MAI'; }
            if (!payload || !payload._ok || !payload.answer) {
                if (asked && asked.parentNode) asked.parentNode.removeChild(asked);
                if (prompt && !prompt.value) prompt.value = instruction;
                if (file) showAskFile(file);
                window.alert((payload && payload.error) || 'The assistant could not answer that.');
                return;
            }
            var kindName = sheet && sheet.getAttribute('data-kind') === 'audio' ? 'Voice note as text' : (sheet && sheet.getAttribute('data-kind') === 'general' ? 'MAI' : (sheet && sheet.getAttribute('data-kind') === 'chat' ? 'This chat' : 'This message as text'));
            if (payload.text && sheet && sheet.getAttribute('data-kind') !== 'chat' && sheet.getAttribute('data-kind') !== 'general') fillTranscribe(kindName, payload.text);
            if (result) { result.hidden = false; result.textContent = payload.answer; }
            if (use) use.hidden = false;
            var thread = document.getElementById('askThread');
            if (thread) thread.scrollTop = thread.scrollHeight;
        }).catch(function () {
            if (ask) { ask.disabled = false; ask.textContent = 'MAI'; }
            if (asked && asked.parentNode) asked.parentNode.removeChild(asked);
            if (prompt && !prompt.value) prompt.value = instruction;
            if (file) showAskFile(file);
            window.alert('The assistant could not answer that.');
        });
    }
    function appendAsked(text, file) {
        var box = document.getElementById('askPreview');
        if (!box) return null;
        var empty = box.querySelector('.ask-empty');
        if (empty && empty.parentNode) empty.parentNode.removeChild(empty);
        var line = document.createElement('div');
        line.className = 'ask-line is-mine';
        var who = document.createElement('span');
        who.textContent = 'You';
        line.appendChild(who);
        if (text) {
            var body = document.createElement('p');
            body.textContent = text;
            line.appendChild(body);
        }
        if (file && file.type && file.type.indexOf('image/') === 0) {
            var img = document.createElement('img');
            img.className = 'ask-photo';
            img.alt = '';
            img.src = URL.createObjectURL(file);
            line.appendChild(img);
        } else if (file) {
            var note = document.createElement('p');
            note.textContent = file.name || 'Attached file';
            line.appendChild(note);
        }
        box.appendChild(line);
        var thread = document.getElementById('askThread');
        if (thread) thread.scrollTop = thread.scrollHeight;
        return line;
    }
    var transcribeAsk = document.getElementById('transcribeAsk');
    if (transcribeAsk) transcribeAsk.addEventListener('click', function () { runAsk(''); });
    var askFile = document.getElementById('askFile');
    if (askFile) askFile.addEventListener('change', function () {
        showAskFile(askAttachment());
    });
    var transcribeUse = document.getElementById('transcribeUse');
    if (transcribeUse) transcribeUse.addEventListener('click', function () {
        var result = document.getElementById('transcribeResult');
        var field = document.getElementById('chatBody');
        if (field && result) {
            field.value = result.textContent;
            field.focus();
        }
        closeSheet('transcribeSheet');
    });
    function openForwardList() {
        var list = document.getElementById('forwardList');
        var rows = document.querySelectorAll('.chat-row[data-kind="person"]');
        if (!list) return;
        list.innerHTML = '';
        rows.forEach(function (row) {
            var id = row.getAttribute('data-id');
            if (!id || String(id) === String(openChat)) return;
            var name = row.querySelector('.chat-name');
            var button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-to', id);
            button.textContent = name ? name.textContent : 'Chat';
            list.appendChild(button);
        });
        if (!list.children.length) {
            list.innerHTML = '<p>No other chat to forward to.</p>';
        }
        closeMessageMenu();
        openSheet('forwardSheet');
    }
    var forwardSearch = document.getElementById('forwardSearch');
    if (forwardSearch) {
        forwardSearch.addEventListener('input', function () {
            var query = forwardSearch.value.trim().toLowerCase();
            document.querySelectorAll('#forwardList button').forEach(function (button) {
                button.hidden = query !== '' && button.textContent.toLowerCase().indexOf(query) === -1;
            });
        });
    }
    document.addEventListener('click', function (event) {
        if (event.target && event.target.id === 'askFileClear') { clearAskFile(); return; }
        var askOpen = event.target.closest && event.target.closest('#openAsk, .mai-open');
        if (askOpen) {
            event.preventDefault();
            openAsk(null, composerDraft(askOpen));
            return;
        }
        var unmask = event.target.closest && event.target.closest('#threadUnmask');
        if (unmask) {
            var chatId = unmask.getAttribute('data-chat');
            if (!chatId) return;
            unmask.disabled = true;
            unmask.textContent = '…';
            fetch(chatPage + '/' + chatId + '/unmask', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: csrfToken() })
            }).then(function (response) { return response.json().then(function (payload) { payload._ok = response.ok; return payload; }); })
                .then(function (payload) {
                    unmask.disabled = false;
                    unmask.textContent = 'Unmask';
                    if (!payload || !payload._ok || !payload.name) {
                        window.alert((payload && payload.error) || 'No MoMo name found.');
                        return;
                    }
                    var title = document.getElementById('threadName');
                    if (title) title.textContent = payload.name;
                    unmask.textContent = 'Unmasked';
                }).catch(function () {
                    unmask.disabled = false;
                    unmask.textContent = 'Unmask';
                    window.alert('Could not look up that MoMo name.');
                });
            return;
        }
        var chip = event.target.closest && event.target.closest('[data-prompt]');
        if (chip) { runAsk(chip.getAttribute('data-prompt') || ''); return; }
        var quoteClear = event.target.closest && event.target.closest('#quoteClear');
        if (quoteClear) { clearQuote(); return; }
        var pinJump = event.target.closest && event.target.closest('[data-pin-jump]');
        if (pinJump) {
            var pinned = document.querySelector('#chatStream .bubble[data-id="' + pinJump.getAttribute('data-pin-jump') + '"]');
            if (pinned && pinned.scrollIntoView) pinned.scrollIntoView({ block: 'center' });
            return;
        }
        if (event.target && event.target.id === 'selectCancel') { stopSelect(); return; }
        if (event.target && event.target.id === 'selectDelete') { deleteSelected(); return; }
        var pickedBubble = selecting && event.target.closest && event.target.closest('#chatStream .bubble');
        if (pickedBubble && !(event.target.closest && event.target.closest('a, audio, video'))) {
            toggleSelected(pickedBubble.getAttribute('data-id'));
            return;
        }
        var reaction = event.target.closest && event.target.closest('[data-react]');
        if (reaction && activeMessage) {
            rememberReaction(activeMessage, reaction.getAttribute('data-react'));
            closeMessageMenu();
            return;
        }
        var act = event.target.closest && event.target.closest('#msgMenu [data-act]');
        if (act && activeMessage) {
            var id = activeMessage.getAttribute('data-id');
            var action = act.getAttribute('data-act');
            if (action === 'reply') {
                setQuote(id, messageBody(activeMessage));
                closeMessageMenu();
                return;
            }
            if (action === 'react') { showMenuPane('msgReact'); return; }
            if (action === 'keep') {
                messagePost(id, 'star').then(function (payload) {
                    if (payload && payload._ok && payload.message) replaceBubble(payload.message);
                    closeMessageMenu();
                });
                return;
            }
            if (action === 'pin') {
                togglePin(activeMessage);
                closeMessageMenu();
                return;
            }
            if (action === 'copy') {
                var copied = messageBody(activeMessage);
                if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(copied);
                closeMessageMenu();
                return;
            }
            if (action === 'forward') { openForwardList(); return; }
            if (action === 'edit') {
                var box = document.getElementById('msgEditBody');
                if (box) box.value = messageBody(activeMessage);
                showMenuPane('msgEdit');
                return;
            }
            if (action === 'info') {
                var time = activeMessage.querySelector('time');
                var info = document.getElementById('msgInfoText');
                if (info) info.textContent = time ? time.textContent.trim() : 'No time on this message';
                showMenuPane('msgInfo');
                return;
            }
            if (action === 'select') {
                startSelect(id);
                return;
            }
            if (action === 'delete') { showMenuPane('msgDelete'); return; }
            if (action === 'cancel-edit' || action === 'cancel-delete' || action === 'cancel-react' || action === 'cancel-info') {
                resetMessageSheet();
                positionMessageMenu(document.getElementById('msgMenu')._anchor || activeMessage);
                return;
            }
            if (action === 'delete-me' || action === 'delete-all') {
                if (action === 'delete-all' && !window.confirm('Delete this message for everyone?')) return;
                messagePost(id, 'delete', { everyone: action === 'delete-all' ? '1' : '0' }).then(function (payload) {
                    if (!payload || !payload._ok) { window.alert((payload && payload.error) || 'Delete failed.'); return; }
                    if (payload.removed) {
                        var node = document.querySelector('#chatStream .bubble[data-id="' + id + '"]');
                        if (node) node.remove();
                    } else if (payload.message) replaceBubble(payload.message);
                    closeMessageMenu();
                });
                return;
            }
        }
        var forwardTo = event.target.closest && event.target.closest('#forwardList button');
        if (forwardTo && activeMessage) {
            messagePost(activeMessage.getAttribute('data-id'), 'forward', { to: forwardTo.getAttribute('data-to') }).then(function (payload) {
                if (!payload || !payload._ok) { window.alert((payload && payload.error) || 'Forward failed.'); return; }
                showChatNote(payload.notice || 'Forwarded.');
                closeSheet('forwardSheet');
            });
            return;
        }
        var more = event.target.closest && event.target.closest('.bubble-more');
        if (more) {
            event.preventDefault();
            var moreBubble = more.closest('.bubble');
            if (moreBubble) openMessageMenu(moreBubble, more);
            return;
        }
        if (!(event.target.closest && event.target.closest('#msgMenu'))) closeMessageMenu();
        var bubble = event.target.closest && event.target.closest('#chatStream .bubble');
        if (bubble && !(event.target.closest && event.target.closest('a, audio, video, button'))) {
            return;
        }
        var back = event.target.closest && event.target.closest('a.chat-back');
        if (back) {
            event.preventDefault();
            showChatList(false);
            return;
        }
        var gear = event.target.closest && event.target.closest('.js-hub-settings');
        if (gear) openSheet('hubSettings');
        var replyPick = event.target.closest && event.target.closest('#openReplyLang');
        if (replyPick) openSheet('replyLang');
    });
    var recorder = null;
    var recordChunks = [];
    var emojiBox = document.getElementById('emojiBox');
    var emojiSets = {
        'Smileys': ['😀','😁','😂','🤣','😊','😍','😘','😎','🤔','😢','😭','😡','🙏','👍','👎','👏','🔥','💯','✅','❤️'],
        'Hands': ['👋','🤝','✌️','🤞','👌','✋','💪','🙌','👐','👊','✊','🫶','✍️','🖐️','👇','👆','👉','👈'],
        'Hearts': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','❣️','💕','💞','💓','💗','💖','💘'],
        'Work': ['📞','📷','📄','📝','📌','📎','📅','⏰','💰','📦','🚚','🏠','🚗','🎉','⭐','⚡','🔔','🔒']
    };
    if (emojiBox) {
        Object.keys(emojiSets).forEach(function (title) {
            var heading = document.createElement('h4');
            heading.textContent = title;
            emojiBox.appendChild(heading);
            emojiSets[title].forEach(function (face) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = face;
                emojiBox.appendChild(button);
            });
        });
    }
    function insertEmoji(face) {
        var field = document.getElementById('chatBody');
        if (!field) return;
        var start = field.selectionStart || field.value.length;
        var end = field.selectionEnd || field.value.length;
        field.value = field.value.slice(0, start) + face + field.value.slice(end);
        var caret = start + face.length;
        field.focus();
        field.selectionStart = caret;
        field.selectionEnd = caret;
    }
    function sendVoice(form, blob) {
        if (!form || form.getAttribute('data-sending') === '1') return;
        form.setAttribute('data-sending', '1');
        var button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        var token = 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
        var pendingId = 'pending-' + token;
        var data = new FormData(form);
        var mime = blob.type || 'audio/webm';
        var ext = mime.indexOf('mp4') !== -1 ? 'm4a' : (mime.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
        data.append('voice', blob, 'voice.' + ext);
        data.append('send_token', token);
        placeMessages([{ id: pendingId, out: true, text: 'Voice note', time: '', at: new Date().toISOString(), tick: 'queued', type: 'AUDIO', media: '', name: '', aside: '', day: '', needs_voice: false }], false);
        var field = form.querySelector('#chatBody');
        if (field) field.value = '';
        postHub(form.getAttribute('action'), data, 0)
            .then(function (payload) {
                var node = document.querySelector('.bubble[data-id="' + pendingId + '"]');
                if (payload && payload.message && node) node.outerHTML = bubbleHtml(payload.message);
                else if (node && (!payload || !payload.ok)) node.querySelector('.tick').textContent = payload && payload.error ? payload.error : 'Not sent';
            })
            .catch(function () {
                var node = document.querySelector('.bubble[data-id="' + pendingId + '"]');
                if (node && node.querySelector('.tick')) node.querySelector('.tick').textContent = 'Not sent';
            })
            .then(function () {
                form.removeAttribute('data-sending');
                if (button) button.disabled = false;
            });
    }
    document.addEventListener('click', function (event) {
        var audience = event.target.closest && event.target.closest('[data-audience]');
        if (audience) {
            var kind = audience.getAttribute('data-audience');
            var holder = document.getElementById('letterAudience');
            if (holder) holder.value = kind;
            audience.parentNode.querySelectorAll('button').forEach(function (button) { button.classList.remove('is-on'); });
            audience.classList.add('is-on');
            var group = document.getElementById('letterGroup');
            var person = document.getElementById('letterPerson');
            if (group) group.hidden = kind !== 'group';
            if (person) person.hidden = kind !== 'person';
            return;
        }
        var face = event.target.closest && event.target.closest('#emojiBox button');
        if (face) {
            insertEmoji(face.textContent);
            return;
        }
        var emojiBtn = event.target.closest && event.target.closest('.js-emoji');
        if (emojiBtn && emojiBox) {
            emojiBox.hidden = !emojiBox.hidden;
            return;
        }
        if (emojiBox && !event.target.closest('#emojiBox')) emojiBox.hidden = true;
        var mic = event.target.closest && event.target.closest('.js-mic');
        if (!mic) return;
        event.preventDefault();
        var form = mic.closest('form');
        if (!navigator.mediaDevices || !window.MediaRecorder) {
            window.alert('This browser cannot record a voice note.');
            return;
        }
        if (recorder) {
            recorder.stop();
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            var options = MediaRecorder.isTypeSupported('audio/webm') ? { mimeType: 'audio/webm' } : {};
            recorder = new MediaRecorder(stream, options);
            recordChunks = [];
            recorder.ondataavailable = function (item) { if (item.data && item.data.size) recordChunks.push(item.data); };
            recorder.onstop = function () {
                stream.getTracks().forEach(function (track) { track.stop(); });
                var blob = new Blob(recordChunks, { type: recorder.mimeType || 'audio/webm' });
                recorder = null;
                document.querySelectorAll('.js-mic').forEach(function (button) { button.classList.remove('is-on'); });
                sendVoice(form, blob);
            };
            recorder.start();
            mic.classList.add('is-on');
        }).catch(function () {
            window.alert('Allow the microphone to record a voice note.');
        });
    });
    var letterSearch = document.getElementById('letterSearch');
    if (letterSearch) {
        letterSearch.addEventListener('input', function () {
            var query = letterSearch.value.trim().toLowerCase();
            var list = document.getElementById('letterPeople');
            if (!list) return;
            list.querySelectorAll('option').forEach(function (option) {
                var name = option.getAttribute('data-name') || '';
                option.hidden = query !== '' && name.indexOf(query) === -1;
            });
        });
    }
    window.addEventListener('popstate', function () {
        var params = new URL(window.location.href).searchParams;
        if (params.get('group')) {
            window.location.reload();
            return;
        }
        var id = params.get('chat');
        if (id) openChatInPlace(id, true);
        else showChatList(true);
    });
    if (window.applyLocalHubTimes) window.applyLocalHubTimes();
    var moreOpen = document.getElementById('moreOpen');
    var moreMenu = document.getElementById('moreMenu');
    var newChatOpen = document.getElementById('newChatOpen');
    var newChat = document.getElementById('newChat');
    function closeInboxPopovers(except) {
        if (moreMenu && except !== moreMenu) { moreMenu.hidden = true; if (moreOpen) moreOpen.setAttribute('aria-expanded', 'false'); }
        if (newChat && except !== newChat) newChat.hidden = true;
    }
    if (moreOpen && moreMenu) moreOpen.addEventListener('click', function (event) {
        event.stopPropagation();
        var open = moreMenu.hidden;
        closeInboxPopovers(open ? moreMenu : null);
        moreMenu.hidden = !open;
        moreOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    if (newChatOpen && newChat) newChatOpen.addEventListener('click', function (event) {
        event.stopPropagation();
        var open = newChat.hidden;
        closeInboxPopovers(open ? newChat : null);
        newChat.hidden = !open;
        if (open) {
            var field = document.getElementById('newChatPhone');
            if (field) field.focus();
        }
    });
    document.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('#moreOpen, #newChatOpen, #newChat')) return;
        closeInboxPopovers(null);
    });
    function fitInbox() {
        var app = document.getElementById('chatsApp');
        if (!app || !window.visualViewport || !window.matchMedia('(max-width: 800px)').matches) {
            if (app) { app.style.height = ''; app.style.transform = ''; }
            return;
        }
        app.style.height = Math.round(window.visualViewport.height) + 'px';
        app.style.transform = 'translateY(' + Math.round(window.visualViewport.offsetTop) + 'px)';
    }
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', fitInbox);
        window.visualViewport.addEventListener('scroll', fitInbox);
        fitInbox();
    }
    var composeField = document.getElementById('chatBody');
    if (composeField) composeField.addEventListener('focus', function () {
        setTimeout(function () {
            fitInbox();
            var stream = document.getElementById('chatStream');
            if (stream) stream.scrollTop = stream.scrollHeight;
        }, 60);
    });
    applyLocalMarks();
    drainVoice();
    poll();
    setTimeout(function () {
        fetch(chatPage + '?poll=1&names=1', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var rows = document.getElementById('chatList');
                (data.names || []).forEach(function (item) {
                    if (!item.name) return;
                    if (rows) {
                        var row = rows.querySelector('.chat-row[data-id="' + item.id + '"]');
                        if (row) {
                            var name = row.querySelector('.chat-name');
                            if (name) name.textContent = item.name;
                            var avatar = row.querySelector('.chat-avatar');
                            if (avatar) avatar.textContent = item.name.charAt(0).toUpperCase();
                        }
                    }
                    if (openChat && item.id === openChat) {
                        var title = document.querySelector('.chats-head h2');
                        if (title) title.textContent = item.name;
                    }
                });
            })
            .catch(function () {});
    }, 600);
})();
</script>
@endsection
