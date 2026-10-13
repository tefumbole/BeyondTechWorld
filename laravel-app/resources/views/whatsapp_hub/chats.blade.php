@extends('layout.main')

@section('content')
<style>
    #content { display: block !important; height: auto !important; max-height: none !important; overflow: visible !important; padding: 0 !important; background: var(--beyond-bg); }
    #content > .container-fluid { flex: none; }
    .main-footer { display: none !important; }
    .chats-app {
        --wa-green: #25d366; --wa-teal: #128c7e; --wa-ink: #0f1f3d; --wa-muted: #6b7a99; --wa-line: #e6ebf5;
        --wa-grad: linear-gradient(135deg, #0b3d91 0%, #1565c0 45%, #12a4a4 100%);
        display: flex; height: calc(100vh - 220px); min-height: 640px; color: var(--wa-ink); overflow: hidden;
        background: #fff; border-radius: 18px; margin: 10px 14px 14px; box-shadow: 0 18px 48px rgba(15, 31, 61, .12), 0 2px 6px rgba(15, 31, 61, .06);
        font-feature-settings: "tnum" 1;
    }
    .chat-rail { width: 78px; flex: none; min-height: 0; display: flex; flex-direction: column; gap: 6px; padding: 14px 8px; background: var(--wa-grad); overflow-y: auto; -webkit-overflow-scrolling: touch; }
    .chat-rail a { display: flex; flex-direction: column; align-items: center; gap: 4px; color: rgba(255,255,255,.78); text-decoration: none; border-radius: 14px; padding: 10px 2px; font-size: 11px; font-weight: 700; text-align: center; transition: background .18s, color .18s, transform .18s; }
    .chat-rail a i { font-size: 17px; color: #fff; opacity: .9; }
    .chat-rail a:hover { background: rgba(255,255,255,.14); color: #fff; transform: translateY(-1px); }
    .chat-rail a.is-on { background: rgba(255,255,255,.22); color: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.35), 0 6px 16px rgba(0,0,0,.18); }
    .chat-rail a.is-on i { color: #ffd166; opacity: 1; }
    .chats-side { width: 360px; max-width: 40vw; flex: none; display: flex; flex-direction: column; min-height: 0; height: 100%; overflow: hidden; border-right: 1px solid var(--wa-line); background: #fff; }
    .call-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; min-height: 34px; padding: 0 14px; background: linear-gradient(135deg, #25d366, #128c7e); color: #fff !important; text-decoration: none; font-weight: 700; box-shadow: 0 4px 12px rgba(18, 140, 126, .3); }
    .chats-search { flex: none; padding: 14px 14px 8px; background: #fff; }
    .chats-search input { width: 100%; border: 1px solid transparent; border-radius: 999px; background: #f1f4fa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2.4' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E") no-repeat 14px center; color: var(--wa-ink); min-height: 40px; padding: 0 14px 0 40px; transition: border-color .18s, box-shadow .18s, background-color .18s; }
    .chats-search input:focus { outline: none; background-color: #fff; border-color: #9cc2ff; box-shadow: 0 0 0 4px rgba(21, 101, 192, .12); }
    .chats-search input::placeholder { color: var(--wa-muted); }
    .chat-filters { flex: none; display: flex; gap: 8px; overflow-x: auto; padding: 4px 14px 12px; background: #fff; border-bottom: 1px solid var(--wa-line); scrollbar-width: none; }
    .chat-filters::-webkit-scrollbar { display: none; }
    .chat-filters button { border: 0; background: #eef2f9; color: #3b4a68; border-radius: 999px; min-height: 32px; padding: 0 14px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .18s, color .18s, box-shadow .18s; }
    .chat-filters button:hover { background: #e2e9f6; }
    .chat-filters button span { margin-left: 6px; display: inline-flex; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; align-items: center; justify-content: center; background: rgba(15,31,61,.08); font-size: 11px; }
    .chat-filters button.is-on { background: var(--wa-grad); color: #fff; box-shadow: 0 6px 16px rgba(21, 101, 192, .28); }
    .chat-filters button.is-on span { background: rgba(255,255,255,.25); }
    .chat-filters button[data-filter="unread"]:not(.is-on) span { background: var(--wa-green); color: #fff; }
    .chats-list { overflow-x: hidden; overflow-y: auto; flex: 1 1 auto; min-height: 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 6px 8px 12px; }
    .chats-list::-webkit-scrollbar, .chats-stream::-webkit-scrollbar { width: 8px; }
    .chats-list::-webkit-scrollbar-thumb, .chats-stream::-webkit-scrollbar-thumb { background: rgba(15,31,61,.14); border-radius: 8px; }
    .chat-none { display: none; padding: 36px 16px; text-align: center; color: var(--wa-muted); }
    .chat-row { position: relative; display: flex; gap: 12px; align-items: center; padding: 10px 12px; margin: 2px 0; color: var(--wa-ink); text-decoration: none; border-radius: 14px; transition: background .15s, transform .15s; }
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
    .chat-top, .chat-bottom { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .chat-bottom { margin-top: 3px; }
    .chat-name { font-weight: 600; font-size: 15px; color: var(--wa-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-time { color: var(--wa-muted); font-size: 12px; flex: none; }
    .chat-preview { color: var(--wa-muted); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-row[data-read="0"] .chat-name { font-weight: 800; }
    .chat-row[data-read="0"] .chat-preview { color: #2c3a57; font-weight: 600; }
    .chat-row[data-read="0"] .chat-time { color: var(--wa-teal); font-weight: 700; }
    .chat-badge { background: var(--wa-green); color: #fff; border-radius: 999px; min-width: 22px; height: 22px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; padding: 0 7px; flex: none; box-shadow: 0 3px 8px rgba(37, 211, 102, .4); }
    .chat-ai { display: inline-block; color: #fff; background: linear-gradient(135deg, #7c5cff, #4f46e5); font-size: 10px; font-weight: 800; border-radius: 6px; padding: 1px 6px; margin-right: 4px; letter-spacing: .3px; }
    .chats-thread { flex: 1 1 auto; min-width: 0; min-height: 0; height: 100%; overflow: hidden; display: flex; flex-direction: column;
        background-color: #eef3fb;
        background-image: radial-gradient(circle at 12px 12px, rgba(21,101,192,.07) 2px, transparent 2.5px), radial-gradient(circle at 36px 36px, rgba(18,164,164,.07) 2px, transparent 2.5px), linear-gradient(180deg, #f4f8ff 0%, #e9f1fb 100%);
        background-size: 48px 48px, 48px 48px, 100% 100%; }
    .chats-head { min-height: 66px; background: rgba(255,255,255,.92); backdrop-filter: saturate(1.4) blur(10px); -webkit-backdrop-filter: saturate(1.4) blur(10px); border-bottom: 1px solid var(--wa-line); display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 18px; }
    .chat-back { display: none; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; color: #1565c0; font-size: 28px; font-weight: 700; text-decoration: none; flex: none; line-height: 1; }
    .chats-head-person { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .chats-head-person .chat-avatar { width: 42px; height: 42px; font-size: 15px; }
    .chats-head-person h2, .chats-head-person p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .load-earlier { align-self: center; border: 0; background: #fff; color: #1565c0; border-radius: 999px; min-height: 32px; padding: 0 16px; font-weight: 700; cursor: pointer; margin: 10px 0; box-shadow: 0 3px 10px rgba(15,31,61,.1); }
    .chat-live { display: none; background: linear-gradient(90deg, #fff4d6, #ffe7b3); color: #7a5b10; text-align: center; font-size: 13px; font-weight: 700; padding: 6px 10px; }
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
    .announce-btn { background: var(--wa-grad); color: #fff; text-decoration: none; display: inline-flex; align-items: center; box-shadow: 0 6px 16px rgba(21, 101, 192, .28); }
    .announce-btn:hover { color: #fff; text-decoration: none; }
    .chats-stream { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 12px 7% 18px; display: flex; flex-direction: column; gap: 4px; }
    .chats-head, .chats-compose, .chat-note, .chat-live { flex: none; }
    .bubble { flex: none; align-self: flex-start; max-width: min(680px, 78%); margin: 0; padding: 7px 11px 5px; border-radius: 4px 16px 16px 16px; line-height: 1.35; white-space: pre-wrap; word-break: break-word; color: var(--wa-ink); font-size: 14px; box-shadow: 0 1px 2px rgba(15,31,61,.08), 0 2px 8px rgba(15,31,61,.05); animation: bubbleIn .18s ease-out; }
    .bubble.in { background: #fff; }
    .bubble.out { align-self: flex-end; background: linear-gradient(135deg, #d9fdd3 0%, #c8f5e4 100%); border-radius: 16px 4px 16px 16px; margin-left: auto; }
    @keyframes bubbleIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .bubble time { float: right; margin: 8px 0 0 10px; color: #7c8aa5; font-size: 11px; line-height: 1; }
    .bubble .tick { margin-left: 3px; letter-spacing: -1px; }
    .bubble .tick.read { color: #34b7f1; }
    .bubble .tick.failed { color: #e5484d; }
    .bubble img.chat-photo { display: block; max-width: 280px; width: 100%; border-radius: 10px; margin-bottom: 5px; }
    .bubble audio, .bubble video { display: block; max-width: 280px; width: 100%; margin-bottom: 5px; border-radius: 10px; }
    .bubble a.chat-file { color: #1565c0; font-weight: 700; }
    .bubble .who { display: block; font-size: 12px; font-weight: 800; color: #7c5cff; margin-bottom: 2px; }
    .bubble .choices { clear: both; display: flex; flex-direction: column; gap: 5px; margin-top: 7px; }
    .bubble .choice { display: block; background: rgba(255,255,255,.8); border: 1px solid #d8e2f2; border-radius: 10px; padding: 7px 10px; font-size: 13px; line-height: 1.3; color: #1565c0; font-weight: 600; }
    .day-chip { align-self: center; background: rgba(255,255,255,.92); color: #4b5d7a; border-radius: 999px; font-size: 12px; font-weight: 700; padding: 4px 14px; margin: 12px 0 6px; box-shadow: 0 2px 8px rgba(15,31,61,.08); }
    .clip { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; border: 0; background: #eef2f9; color: #1565c0; font-size: 24px; font-weight: 600; cursor: pointer; flex: none; overflow: hidden; position: relative; transition: background .18s, transform .18s; }
    .clip:hover { background: #e2e9f6; transform: rotate(90deg); }
    .clip input { display: none; }
    .file-name { align-self: center; color: var(--wa-muted); font-size: 12px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chats-compose { display: flex; align-items: center; flex-wrap: nowrap; gap: 10px; padding: 12px 18px; background: rgba(255,255,255,.94); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-top: 1px solid var(--wa-line); }
    .chats-compose textarea { flex: 1; border: 1px solid transparent; border-radius: 22px; background: #f1f4fa; color: var(--wa-ink); min-height: 44px; max-height: 120px; padding: 11px 16px; resize: none; transition: border-color .18s, box-shadow .18s, background-color .18s; }
    .chats-compose textarea:focus { outline: none; background: #fff; border-color: #9cc2ff; box-shadow: 0 0 0 4px rgba(21, 101, 192, .12); }
    .chats-compose button { border: 0; border-radius: 50%; width: 46px; height: 46px; background: linear-gradient(135deg, #25d366, #128c7e); color: #fff; font-size: 18px; font-weight: 800; cursor: pointer; flex: none; box-shadow: 0 6px 16px rgba(18, 140, 126, .35); transition: transform .15s, box-shadow .15s; }
    .chats-compose button:hover { transform: scale(1.06); box-shadow: 0 8px 20px rgba(18, 140, 126, .45); }
    .chats-empty { margin: auto; text-align: center; color: var(--wa-muted); max-width: 440px; padding: 28px; }
    .chats-empty strong { display: block; color: var(--wa-ink); font-size: 26px; font-weight: 800; margin-bottom: 8px; }
    .chats-thread > .chats-empty::before { content: ""; display: block; width: 96px; height: 96px; margin: 0 auto 18px; border-radius: 28px; background: var(--wa-grad) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='44' height='44' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 11.5a8.4 8.4 0 0 1-12.3 7.5L3 21l2-5.5A8.5 8.5 0 1 1 21 11.5z'/%3E%3C/svg%3E") no-repeat center; box-shadow: 0 16px 36px rgba(21, 101, 192, .3); transform: rotate(-6deg); }
    .group-card { margin: auto; background: #fff; border: 0; border-radius: 20px; padding: 28px 24px; max-width: 460px; text-align: center; color: var(--wa-ink); box-shadow: 0 12px 32px rgba(15,31,61,.1); }
    .group-card h2 { margin: 8px 0; color: var(--wa-ink); }
    .chat-note { background: linear-gradient(90deg, #fff4d6, #ffe7b3); color: #7a5b10; padding: 9px 16px; font-size: 13px; font-weight: 600; }
    @media (max-width: 800px) {
        .chats-app { height: calc(100vh - 180px); min-height: 520px; margin: 0; border-radius: 0; box-shadow: none; }
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.has-open .chat-rail, .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
        .chat-back { display: inline-flex; }
        .chats-stream { padding: 8px 12px 12px; }
        .bubble { max-width: 90%; }
        .chats-compose { padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); }
        .chats-head { padding: 8px 10px; }
        .mode-switch button, .announce-btn, .call-btn { min-height: 36px; }
        .beyond-module-tabs { margin-bottom: 0; }
        .beyond-module-tabs-label { display: none; }
        .beyond-module-tabs-nav { flex-wrap: nowrap !important; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 8px; }
        .beyond-module-tab { padding: 8px 12px; font-size: 12px; }
        .chat-row { padding: 12px; }
        .chat-rail { width: 66px; }
    }
</style>
@php
    $paneKeep = array_filter([
        'chat' => $open ? $open->id : null,
        'group' => (! $open && $group) ? $group['jid'] : null,
    ]);
    $rail = [
        'chats' => ['Chats', 'fa-comments'],
        'updates' => ['Updates', 'fa-bell'],
        'calls' => ['Calls', 'fa-phone'],
        'media' => ['Media', 'fa-image'],
        'more' => ['More', 'fa-ellipsis-h'],
    ];
@endphp
<div class="chats-app {{ ($open || $group) ? 'has-open' : '' }}">
    <nav class="chat-rail">
        @foreach($rail as $key => $item)
            <a class="{{ $pane === $key ? 'is-on' : '' }}" href="{{ route('whatsapp.chats', $paneKeep + ['pane' => $key]) }}">
                <i class="fa {{ $item[1] }}"></i>{{ $item[0] }}
            </a>
        @endforeach
    </nav>
    <aside class="chats-side">
        @if(!empty($phoneLink['disconnected']))
            <div class="chat-note">WhatsApp on the phone is not linked{{ !empty($phoneLink['at']) ? ' since '.$phoneLink['at'] : '' }}. Reconnect that number in Wasender. New chats will then stay up to date here.</div>
        @endif
        @php
            $openId = $open ? (int) $open->id : 0;
            $unreadChats = $conversations->filter(function ($row) use ($openId) { return (int) $row->id !== $openId && (int) $row->unread_count > 0; })->count();
            $readChats = $conversations->count() - $unreadChats;
        @endphp
        <div class="chats-search">
            <input type="search" id="chatSearch" placeholder="{{ $pane === 'chats' ? 'Search a name' : 'Search this list' }}" autocomplete="off">
        </div>
        @if($pane === 'chats')
        <div class="chat-filters" id="chatFilters">
            <button type="button" class="is-on" data-filter="all">All</button>
            <button type="button" data-filter="unread">Unread <span>{{ $unreadChats }}</span></button>
            <button type="button" data-filter="read">Read <span>{{ $readChats }}</span></button>
            <button type="button" data-filter="groups">Groups <span>{{ count($groups) }}</span></button>
        </div>
        @endif
        <div class="chats-list" id="chatList">
            <div class="chat-none" id="chatNone" @if($pane === 'chats' && $conversations->isEmpty() && count($groups) === 0) style="display:block" @endif>@if($pane === 'chats' && $conversations->isEmpty() && count($groups) === 0)No conversations yet. Chats from the Wasender number appear here.@else Nothing in this list.@endif</div>
            @if($pane === 'chats')
            @foreach($conversations as $row)
                @php
                    $person = optional($row->contact)->displayName() ?: 'WhatsApp';
                    $when = $row->last_activity_at ?: $row->updated_at;
                    $preview = trim((string) $row->last_message);
                    $previewLabels = ['[IMAGE]' => 'Photo', '[AUDIO]' => 'Voice message', '[VIDEO]' => 'Video', '[DOCUMENT]' => 'Document', '[LOCATION]' => 'Location'];
                    $previewKey = strtoupper($preview);
                    if (isset($previewLabels[$previewKey])) {
                        $preview = $previewLabels[$previewKey];
                    }
                    $rowUnread = (int) $row->id === $openId ? 0 : (int) $row->unread_count;
                    $tone = sprintf('%u', crc32(strtolower($person))) % 8;
                @endphp
                <a class="chat-row {{ (int) $row->id === $openId ? 'is-on' : '' }}" data-id="{{ $row->id }}" data-kind="person" data-read="{{ $rowUnread > 0 ? '0' : '1' }}" data-name="{{ strtolower($person.' '.optional($row->contact)->display_phone) }}" href="{{ route('whatsapp.chats', ['chat' => $row->id]) }}">
                    <span class="chat-avatar" data-tone="{{ $tone }}">{{ mb_strtoupper(mb_substr($person, 0, 1)) }}</span>
                    <span class="chat-main">
                        <span class="chat-top">
                            <span class="chat-name">{{ $person }}</span>
                            <span class="chat-time">{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
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
            @endforeach
            @foreach($groups as $row)
                <a class="chat-row {{ $group && $group['jid'] === $row['jid'] ? 'is-on' : '' }}" data-kind="group" data-name="{{ strtolower($row['name']) }}" href="{{ route('whatsapp.chats', ['group' => $row['jid']]) }}">
                    <span class="chat-avatar is-group">G</span>
                    <span class="chat-main">
                        <span class="chat-top">
                            <span class="chat-name">{{ $row['name'] }}</span>
                            <span class="chat-time">Group</span>
                        </span>
                        <span class="chat-bottom">
                            <span class="chat-preview">{{ $row['members'] === null ? 'WhatsApp group' : number_format($row['members']).' contacts' }}</span>
                        </span>
                    </span>
                </a>
            @endforeach
            @elseif($pane === 'calls')
                @forelse($calls as $call)
                    @php
                        $person = optional($call->contact)->displayName() ?: ($call->caller_phone ?: 'Unknown');
                        $when = $call->called_at ?: $call->created_at;
                        $chatId = $call->contact_id && isset($callChats[$call->contact_id]) ? $callChats[$call->contact_id] : null;
                    @endphp
                    <a class="chat-row" data-name="{{ strtolower($person.' '.$call->caller_phone) }}" @if($chatId) href="{{ route('whatsapp.chats', ['chat' => $chatId]) }}" @else href="tel:+{{ preg_replace('/\D/', '', (string) $call->caller_phone) }}" @endif>
                        <span class="chat-avatar"><i class="fa fa-phone"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $person }}</span>
                                <span class="chat-time">{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ ucfirst(strtolower(str_replace('_', ' ', (string) $call->status))) }}{{ $call->call_type ? ' · '.$call->call_type : '' }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No calls yet</strong>When someone calls the WhatsApp line, it shows here.</div>
                @endforelse
            @elseif($pane === 'media')
                @forelse($mediaItems as $item)
                    @php
                        $person = optional($item->contact)->displayName() ?: 'WhatsApp';
                        $labels = ['IMAGE' => 'Photo', 'AUDIO' => 'Voice message', 'VIDEO' => 'Video', 'DOCUMENT' => 'Document'];
                        $label = isset($labels[$item->type]) ? $labels[$item->type] : $item->type;
                    @endphp
                    <a class="chat-row" data-name="{{ strtolower($person.' '.$label.' '.$item->body) }}" href="{{ route('whatsapp.chats', ['chat' => $item->conversation_id]) }}">
                        <span class="chat-avatar"><i class="fa fa-image"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $person }}</span>
                                <span class="chat-time">{{ $item->created_at ? $item->created_at->format($item->created_at->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ $label }}{{ $item->body ? ' · '.$item->body : '' }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No media yet</strong>Photos, voice messages, videos, and documents show here.</div>
                @endforelse
            @elseif($pane === 'updates')
                <a class="chat-row" data-name="new announcement" href="{{ route('announcements.compose') }}">
                    <span class="chat-avatar is-group">+</span>
                    <span class="chat-main"><span class="chat-name">New announcement</span><span class="chat-preview">Send an update to a group or a list</span></span>
                </a>
                @forelse($updates as $update)
                    @php $when = $update->created_at; $title = $update->subject ?: 'Announcement'; @endphp
                    <a class="chat-row" data-name="{{ strtolower($title) }}" href="{{ route('announcements.index') }}">
                        <span class="chat-avatar"><i class="fa fa-bell"></i></span>
                        <span class="chat-main">
                            <span class="chat-top">
                                <span class="chat-name">{{ $title }}</span>
                                <span class="chat-time">{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                            </span>
                            <span class="chat-bottom"><span class="chat-preview">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $update->body)), 80) }}</span></span>
                        </span>
                    </a>
                @empty
                    <div class="chats-empty"><strong>No updates yet</strong>Announcements you send show up in this list.</div>
                @endforelse
            @else
                @php
                    $more = [
                        ['People', 'Names and numbers', route('whatsapp.people')],
                        ['Groups', 'WhatsApp groups', route('whatsapp.groups')],
                        ['Leads', 'Enquiries from chats', route('whatsapp.leads')],
                        ['Announcements', 'Write and send an update', route('announcements.compose')],
                    ];
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
            @endif
        </div>
    </aside>
    <section class="chats-thread">
        @if($open)
            @php $title = optional($open->contact)->displayName() ?: 'WhatsApp'; @endphp
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', array_filter(['filter' => $listFilter !== 'all' ? $listFilter : null])) }}" aria-label="Back to chats">‹</a>
                    <span class="chat-avatar" data-tone="{{ sprintf('%u', crc32(strtolower($title))) % 8 }}">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</span>
                    <div>
                        <h2>{{ $title }}</h2>
                        <p>{{ optional($open->contact)->display_phone }} <span class="mode-pill {{ $open->mode === 'AI' ? '' : 'is-human' }}">{{ $open->mode === 'AI' ? 'AI' : 'Human' }}</span></p>
                    </div>
                </div>
                <div class="mode-switch">
                    @php $callDigits = preg_replace('/\D/', '', (string) optional($open->contact)->normalized_phone); @endphp
                    @if($callDigits !== '')
                        <a class="call-btn" href="tel:+{{ $callDigits }}"><i class="fa fa-phone"></i> Call</a>
                    @endif
                    <form method="POST" action="{{ route('whatsapp.conversation.enable_ai', $open->id) }}">
                        @csrf
                        <button type="submit" class="{{ $open->mode === 'AI' ? 'is-on' : '' }}">AI</button>
                    </form>
                    <form method="POST" action="{{ route('whatsapp.conversation.takeover', $open->id) }}">
                        @csrf
                        <button type="submit" class="{{ $open->mode !== 'AI' ? 'is-on' : '' }}">Human</button>
                    </form>
                </div>
            </header>
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
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
                        'out' => $message->direction === 'OUTGOING',
                        'text' => $message->body,
                        'time' => $message->created_at ? $message->created_at->format('H:i') : '',
                        'tick' => $message->ticks(),
                        'type' => $message->type,
                        'mediaUrl' => $message->chatMediaUrl(),
                        'mediaName' => $message->chatMediaName(),
                        'who' => null,
                        'choices' => app(\App\Services\Event\EventOptionPresentation::class)->displayChoices($message->body, $message->media_json),
                    ])
                @empty
                    <div class="chats-empty"><strong>No messages yet</strong>Send the first message below.</div>
                @endforelse
            </div>
            @if($canReply)
                <form class="chats-compose" method="POST" action="{{ route('whatsapp.chats.reply', $open->id) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="clip" title="Photo or file">+<input type="file" name="file" id="chatFile" data-skip-image-paste="1" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"></label>
                    <span class="file-name" id="chatFileName"></span>
                    <textarea name="body" id="chatBody" placeholder="Type a message"></textarea>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @elseif($group)
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.chats', ['pane' => 'chats']) }}" aria-label="Back to chats">‹</a>
                    <div>
                        <h2>{{ $group['name'] }}</h2>
                        <p>{{ $group['members'] === null ? 'WhatsApp group' : number_format($group['members']).' contacts' }}</p>
                    </div>
                </div>
                <a class="announce-btn" href="{{ route('announcements.compose', ['group' => $group['jid']]) }}">Create announcement</a>
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
                        'out' => ! empty($media['outgoing']),
                        'text' => $message->body,
                        'time' => $when ? $when->format('H:i') : '',
                        'tick' => ! empty($media['outgoing']) ? 'sent' : '',
                        'type' => isset($media['type']) ? $media['type'] : 'TEXT',
                        'mediaUrl' => $mediaUrl,
                        'mediaName' => isset($media['file_name']) ? $media['file_name'] : '',
                        'who' => empty($media['outgoing']) ? ($message->participant_name ?: 'Member') : null,
                    ])
                @empty
                    <div class="chats-empty"><strong>{{ $group['name'] }}</strong>Send a message to this group, or create an announcement for its members.</div>
                @endforelse
            </div>
            @if($canReply)
                <form class="chats-compose" method="POST" action="{{ route('whatsapp.chats.group') }}">
                    @csrf
                    <input type="hidden" name="jid" value="{{ $group['jid'] }}">
                    <textarea name="body" id="chatBody" placeholder="Message the group"></textarea>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @else
            <div class="chats-empty">
                <strong>Chats</strong>
                Search a name on the left. A person opens the WhatsApp thread. A group opens so you can message it or create an announcement.
            </div>
        @endif
    </section>
</div>
<script>
(function () {
    var search = document.getElementById('chatSearch');
    var list = document.getElementById('chatList');
    var filters = document.getElementById('chatFilters');
    var none = document.getElementById('chatNone');
    var filter = @json($pane === 'chats' ? $listFilter : 'all');
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
            var isOpen = openId && row.getAttribute('data-id') === String(openId);
            var ok = !q || name.indexOf(q) !== -1;
            if (filter === 'unread') ok = ok && kind === 'person' && (!read || isOpen);
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
            try { sessionStorage.setItem('chatListScroll', String(list.scrollTop)); } catch (e) {}
            event.preventDefault();
            window.location.href = url.toString();
        });
    }
    if (search) search.addEventListener('input', applyList);
    applyList();
    if (list) {
        try {
            var kept = sessionStorage.getItem('chatListScroll');
            if (kept !== null) {
                list.scrollTop = parseInt(kept, 10) || 0;
                sessionStorage.removeItem('chatListScroll');
            }
        } catch (e) {}
    }
    var stream = document.getElementById('chatStream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var body = document.getElementById('chatBody');
    var file = document.getElementById('chatFile');
    var fileName = document.getElementById('chatFileName');
    function canSend() {
        var typed = body && body.value.trim() !== '';
        var picked = file && file.files && file.files.length;
        return typed || picked;
    }
    if (file) {
        file.addEventListener('change', function () {
            var picked = file.files && file.files[0];
            if (fileName) fileName.textContent = picked ? picked.name : '';
        });
    }
    if (body && file) {
        body.addEventListener('paste', function (event) {
            var items = event.clipboardData && event.clipboardData.items;
            if (!items) return;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== 0) continue;
                var picked = items[i].getAsFile();
                if (!picked) return;
                event.preventDefault();
                try {
                    var list = new DataTransfer();
                    list.items.add(picked);
                    file.files = list.files;
                } catch (e) {}
                if (fileName) fileName.textContent = picked.name || 'Pasted image';
                return;
            }
        });
    }
    if (body) {
        body.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                if (canSend()) body.form.submit();
            }
        });
        body.form.addEventListener('submit', function (event) {
            if (!canSend()) event.preventDefault();
        });
    }
    var chatPage = @json(route('whatsapp.chats'));
    var openChat = {{ $open ? (int) $open->id : 0 }};
    var openGroup = @json($group ? $group['jid'] : '');
    var seen = {{ ($open && $messages->last()) ? (int) $messages->last()->id : 0 }};
    var seenGroup = {{ ($group && $groupMessages->last()) ? (int) $groupMessages->last()->id : 0 }};
    var misses = 0;
    var polling = false;
    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }
    function tickHtml(tick) {
        if (!tick) return '';
        if (tick === 'failed') return '<span class="tick failed">!</span>';
        if (tick === 'read' || tick === 'played' || tick === 'delivered') return '<span class="tick ' + tick + '">✓✓</span>';
        return '<span class="tick">✓</span>';
    }
    function bubbleHtml(message) {
        var media = '';
        if (message.type === 'IMAGE' && message.media) {
            media = '<img class="chat-photo" alt="" loading="lazy" decoding="async" src="' + esc(message.media) + '">';
        } else if (message.type === 'AUDIO' && message.media) {
            media = '<audio controls preload="none" src="' + esc(message.media) + '"></audio>';
        } else if (message.type === 'VIDEO' && message.media) {
            media = '<video controls preload="none" src="' + esc(message.media) + '"></video>';
        } else if (message.media && (message.type === 'DOCUMENT' || message.type === 'LOCATION')) {
            media = '<a class="chat-file" target="_blank" rel="noopener" href="' + esc(message.media) + '">' + esc(message.name || 'Document') + '</a>';
        }
        return '<div class="bubble ' + (message.out ? 'out' : 'in') + '" data-id="' + message.id + '" data-day="' + esc(message.day) + '">'
            + (message.who ? '<span class="who">' + esc(message.who) + '</span>' : '')
            + media
            + (message.text ? esc(message.text) : '')
            + '<time>' + esc(message.time || '') + (message.out ? tickHtml(message.tick) : '') + '</time></div>';
    }
    function nearBottom(node) {
        return node.scrollHeight - node.scrollTop - node.clientHeight < 140;
    }
    function placeMessages(messages, prepend) {
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
    }
    function paintList(items) {
        if (!list || !filters || !items) return;
        list.querySelectorAll('.chat-row.is-on').forEach(function (row) { row.classList.remove('is-on'); });
        var head = document.getElementById('chatNone');
        items.slice().reverse().forEach(function (item) {
            var row = list.querySelector('.chat-row[data-id="' + item.id + '"]');
            if (!row) {
                row = document.createElement('a');
                row.className = 'chat-row';
                row.setAttribute('data-kind', 'person');
                row.setAttribute('data-id', item.id);
            }
            row.href = chatPage + '?chat=' + item.id;
            row.setAttribute('data-read', item.unread > 0 ? '0' : '1');
            row.setAttribute('data-name', (item.name + ' ' + (item.phone || '')).toLowerCase());
            if (openChat && item.id === openChat) row.classList.add('is-on');
            var badge = item.unread > 0 ? '<span class="chat-badge">' + item.unread + '</span>' : '';
            var ai = item.mode === 'AI' ? '<span class="chat-ai">AI </span>' : '';
            var initial = (item.name || 'W').charAt(0).toUpperCase();
            row.innerHTML = '<span class="chat-avatar" data-tone="' + (item.tone || 0) + '">' + esc(initial) + '</span><span class="chat-main"><span class="chat-top"><span class="chat-name">' + esc(item.name) + '</span><span class="chat-time">' + esc(item.time) + '</span></span><span class="chat-bottom"><span class="chat-preview">' + ai + esc(item.preview || '') + '</span>' + badge + '</span></span>';
            list.insertBefore(row, head ? head.nextSibling : list.firstChild);
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
        applyList();
    }
    function paintTicks(ticks) {
        if (!stream || !ticks) return;
        ticks.forEach(function (item) {
            var node = stream.querySelector('.bubble[data-id="' + item.id + '"] .tick');
            if (!node) return;
            node.className = 'tick' + (item.tick ? ' ' + item.tick : '');
            node.textContent = item.tick === 'failed' ? '!' : ((item.tick === 'read' || item.tick === 'played' || item.tick === 'delivered') ? '✓✓' : '✓');
        });
    }
    function poll(extra) {
        if (polling || document.hidden) return;
        polling = true;
        var url = chatPage + '?poll=1';
        if (openChat) url += '&chat=' + openChat + '&after=' + seen;
        if (openGroup) url += '&group=' + encodeURIComponent(openGroup) + '&after_group=' + seenGroup;
        if (extra) url += extra;
        var live = document.getElementById('chatLive');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { if (!response.ok) throw new Error('poll'); return response.json(); })
            .then(function (data) {
                misses = 0;
                if (live) live.classList.remove('is-on');
                if (extra) {
                    placeMessages(data.earlier || [], true);
                    var button = document.getElementById('loadEarlier');
                    if (button) {
                        if (data.has_earlier && data.earlier && data.earlier.length) button.setAttribute('data-before', data.earlier[0].id);
                        else button.remove();
                    }
                } else {
                    placeMessages(openGroup ? (data.group_messages || []) : (data.messages || []), false);
                    paintTicks(data.ticks || []);
                    paintList(data.items || []);
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
    if (liveReady()) setInterval(poll, 4000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    function liveReady() { return true; }
    poll();
})();
</script>
@endsection
