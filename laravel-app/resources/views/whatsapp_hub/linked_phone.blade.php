@extends('layout.main')

@section('content')
<style>
    body.whatsapp-hub-app .header,
    body.whatsapp-hub-app nav.side-navbar,
    body.whatsapp-hub-app .sidebar-backdrop,
    body.whatsapp-hub-app .main-footer,
    body.whatsapp-hub-app #beyond-module-tabs { display: none !important; }
    body.whatsapp-hub-app .page { margin: 0 !important; width: 100% !important; min-height: 100vh; }
    body.whatsapp-hub-app #content,
    body.whatsapp-hub-app #content > .container-fluid { padding: 0 !important; margin: 0 !important; max-width: none !important; }
    .chats-app {
        --wa-green: #25d366; --wa-ink: #0f1f3d; --wa-muted: #6b7a99; --wa-line: #e6ebf5;
        --wa-grad: linear-gradient(135deg, #0b3d91 0%, #1565c0 45%, #12a4a4 100%);
        display: flex; height: 100vh; min-height: 640px; color: var(--wa-ink); overflow: hidden; background: #fff;
    }
    .chats-side { width: 380px; max-width: 42vw; flex: none; display: flex; flex-direction: column; min-height: 0; border-right: 1px solid var(--wa-line); background: #fff; }
    .chats-search { padding: 14px 14px 8px; }
    .chats-search input { width: 100%; border: 1px solid transparent; border-radius: 999px; background: #f1f4fa; min-height: 40px; padding: 0 14px; }
    .chats-search input:focus { outline: none; background: #fff; border-color: #9cc2ff; }
    .hub-label { padding: 4px 16px 8px; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--wa-muted); }
    .chats-list { overflow: auto; flex: 1; padding: 0 8px 12px; }
    .chat-row { position: relative; display: flex; gap: 12px; align-items: center; padding: 10px 12px; margin: 2px 0; color: var(--wa-ink); text-decoration: none; border-radius: 14px; }
    .chat-row:hover { background: #f4f7fc; color: var(--wa-ink); text-decoration: none; }
    .chat-row.is-on { background: linear-gradient(90deg, rgba(21,101,192,.12), rgba(18,164,164,.08)); }
    .chat-avatar { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; flex: none; background: linear-gradient(135deg, #1565c0, #0b3d91); }
    .chat-avatar[data-tone="0"] { background: linear-gradient(135deg, #ff7a59, #e8445a); }
    .chat-avatar[data-tone="1"] { background: linear-gradient(135deg, #7c5cff, #4f46e5); }
    .chat-avatar[data-tone="2"] { background: linear-gradient(135deg, #22c1c3, #128c7e); }
    .chat-avatar[data-tone="3"] { background: linear-gradient(135deg, #f7b733, #fc6a03); }
    .chat-avatar[data-tone="4"] { background: linear-gradient(135deg, #2bd47d, #0f9b58); }
    .chat-avatar[data-tone="5"] { background: linear-gradient(135deg, #3fa9f5, #1565c0); }
    .chat-main { min-width: 0; flex: 1; }
    .chat-top, .chat-bottom { display: flex; justify-content: space-between; gap: 8px; align-items: center; }
    .chat-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-time, .chat-preview { color: var(--wa-muted); font-size: 12px; }
    .chat-preview { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-badge { background: #18a957; color: #fff; border-radius: 999px; min-width: 22px; height: 22px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; }
    .chats-thread { flex: 1; min-width: 0; display: flex; flex-direction: column; background: #eef3fb; background-image: radial-gradient(circle at 12px 12px, rgba(21,101,192,.07) 2px, transparent 2.5px); background-size: 48px 48px; }
    .chats-head { min-height: 66px; background: rgba(255,255,255,.92); border-bottom: 1px solid var(--wa-line); display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 18px; }
    .chats-head h2 { margin: 0; font-size: 16px; }
    .chats-head p { margin: 2px 0 0; color: var(--wa-muted); font-size: 12px; }
    .chat-back { display: none; align-items: center; min-height: 36px; padding: 0 12px; border-radius: 999px; background: var(--wa-grad); color: #fff !important; font-weight: 800; text-decoration: none; }
    .chats-stream { flex: 1; overflow: auto; padding: 16px 8% 18px; display: flex; flex-direction: column; gap: 6px; }
    .bubble { align-self: flex-start; max-width: min(680px, 78%); margin: 0; padding: 8px 12px 6px; border-radius: 4px 16px 16px 16px; background: #fff; line-height: 1.4; white-space: pre-wrap; word-break: break-word; box-shadow: 0 1px 2px rgba(15,31,61,.08); }
    .bubble.is-me { align-self: flex-end; background: #d9fdd3; border-radius: 16px 4px 16px 16px; }
    .bubble .aside { display: block; margin-top: 4px; font-size: 11px; color: var(--wa-muted); text-align: right; }
    .chats-compose { display: flex; gap: 8px; padding: 10px 14px 14px; background: #f4f7fb; }
    .chats-compose input { flex: 1; border: 0; border-radius: 999px; min-height: 44px; padding: 0 16px; }
    .chats-compose button { border: 0; border-radius: 999px; min-width: 64px; background: var(--wa-grad); color: #fff; font-weight: 800; }
    .main-line { color: #1565c0; font-weight: 800; text-decoration: none; }
    @media (max-width: 800px) {
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.is-open .chats-side { display: none; }
        .chats-app.is-open .chats-thread { display: flex; }
        .chat-back { display: inline-flex; }
    }
</style>
@php
    $openJid = !empty($open['jid']) ? $open['jid'] : '';
@endphp
<div class="chats-app {{ $openJid !== '' ? 'is-open' : '' }}">
    <aside class="chats-side">
        <div class="chats-search"><input id="chatFilter" type="search" placeholder="Search"></div>
        <div class="hub-label">WhatsApp +{{ $phone }}</div>
        <div class="chats-list" id="chatList">
            @forelse($chats as $chat)
                @php
                    $label = $chat['name'] !== '' ? $chat['name'] : ('+'.$chat['phone']);
                    $initial = function_exists('mb_substr') ? mb_substr($label, 0, 1) : substr($label, 0, 1);
                    $tone = abs(crc32($label)) % 6;
                    $when = !empty($chat['at']) ? date('H:i', (int) $chat['at']) : '';
                @endphp
                <a class="chat-row {{ $openJid === $chat['jid'] ? 'is-on' : '' }}" data-name="{{ strtolower($label) }}" href="{{ route('whatsapp.chats', ['jid' => $chat['jid']]) }}">
                    <span class="chat-avatar" data-tone="{{ $tone }}">{{ strtoupper($initial) }}</span>
                    <span class="chat-main">
                        <span class="chat-top"><span class="chat-name">{{ $label }}</span><span class="chat-time">{{ $when }}</span></span>
                        <span class="chat-bottom"><span class="chat-preview">{{ $chat['preview'] !== '' ? $chat['preview'] : '+'.$chat['phone'] }}</span>@if(!empty($chat['unread']))<span class="chat-badge">{{ $chat['unread'] }}</span>@endif</span>
                    </span>
                </a>
            @empty
                <p class="chat-preview" style="padding:16px;">Chats from +{{ $phone }} will appear here.</p>
            @endforelse
        </div>
    </aside>
    <section class="chats-thread">
        <header class="chats-head">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <a class="chat-back" href="{{ route('whatsapp.chats') }}">Back</a>
                <div style="min-width:0;">
                    <h2>{{ !empty($open) ? ($open['name'] ?: $open['jid']) : 'WhatsApp +'.$phone }}</h2>
                    <p>{{ !empty($open['phone']) ? '+'.$open['phone'] : 'Choose a chat' }}</p>
                </div>
            </div>
            <a class="main-line" href="{{ route('whatsapp.chats', ['line' => 'main']) }}">Main line</a>
        </header>
        <div class="chats-stream">
            @if(session('message'))<p class="bubble">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bubble">{{ session('not_permitted') }}</p>@endif
            @if(!empty($open))
                @forelse($messages as $row)
                    @php $stamp = (int) $row['at']; @endphp
                    <p class="bubble {{ !empty($row['from_me']) ? 'is-me' : '' }}">{{ $row['text'] }}@if($stamp > 0)<span class="aside">{{ date('H:i', $stamp) }}</span>@endif</p>
                @empty
                    <p class="chat-preview" style="text-align:center;">No messages in this chat yet.</p>
                @endforelse
            @else
                <p class="chat-preview" style="text-align:center;">Choose a chat from +{{ $phone }}.</p>
            @endif
        </div>
        @if(!empty($open))
            <form class="chats-compose" method="POST" action="{{ route('whatsapp.chats.linked_send') }}">
                @csrf
                <input type="hidden" name="to" value="{{ $open['jid'] }}">
                <input name="text" placeholder="Type a message" required autocomplete="off">
                <button type="submit">Send</button>
            </form>
        @endif
    </section>
</div>
<script>
    var filter = document.getElementById('chatFilter');
    if (filter) {
        filter.addEventListener('input', function () {
            var q = filter.value.toLowerCase();
            document.querySelectorAll('#chatList .chat-row').forEach(function (row) {
                row.style.display = row.getAttribute('data-name').indexOf(q) === -1 ? 'none' : '';
            });
        });
    }
    var stream = document.querySelector('.chats-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
</script>
@endsection
