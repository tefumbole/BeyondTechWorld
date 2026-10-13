@extends('layout.main')

@section('content')
<style>
    #content { display: block !important; height: auto !important; max-height: none !important; overflow: visible !important; padding: 0 !important; background: var(--beyond-bg); }
    .main-footer { display: none !important; }
    .chats-app { display: flex; height: calc(100vh - 220px); min-height: 520px; background: var(--beyond-bg); color: var(--beyond-text); overflow: hidden; }
    .chats-side { width: 340px; max-width: 38vw; flex: none; display: flex; flex-direction: column; min-height: 0; height: 100%; overflow: hidden; border-right: 1px solid #e3e9f4; background: var(--beyond-card); }
    .chats-search { flex: none; padding: 10px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chats-search input { width: 100%; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 38px; padding: 0 12px; }
    .chats-search input::placeholder { color: var(--beyond-muted); }
    .chats-list { overflow-x: hidden; overflow-y: auto; flex: 1 1 auto; min-height: 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
    .chat-none { display: none; padding: 28px 16px; text-align: center; color: var(--beyond-muted); }
    .chat-row { display: flex; gap: 12px; align-items: center; padding: 10px 14px; color: var(--beyond-text); text-decoration: none; border-bottom: 1px solid #e3e9f4; }
    .chat-row:hover { background: var(--beyond-bg); color: var(--beyond-text); text-decoration: none; }
    .chat-row.is-on { background: #e7eef8; }
    .chat-avatar { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; flex: none; background: var(--beyond-primary); }
    .chat-main { min-width: 0; flex: 1; }
    .chat-top, .chat-bottom { display: flex; justify-content: space-between; gap: 8px; }
    .chat-name { font-weight: 600; color: var(--beyond-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-time { color: var(--beyond-muted); font-size: 12px; flex: none; }
    .chat-preview { color: var(--beyond-muted); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-badge { background: var(--beyond-primary); color: #fff; border-radius: 999px; min-width: 18px; height: 18px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; }
    .chats-thread { flex: 1 1 auto; min-width: 0; min-height: 0; height: 100%; overflow: hidden; display: flex; flex-direction: column; background: var(--beyond-bg); }
    .chats-head { min-height: 60px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 16px; }
    .chat-back { display: none; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; color: var(--beyond-primary); font-size: 28px; font-weight: 700; text-decoration: none; flex: none; line-height: 1; }
    .chats-head-person { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .chats-head-person h2, .chats-head-person p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chats-head h2 { margin: 0; font-size: 16px; font-weight: 600; color: var(--beyond-text); }
    .chats-head p { margin: 0; color: var(--beyond-muted); font-size: 12px; }
    .call-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; min-height: 32px; padding: 0 12px; background: var(--beyond-primary); color: #fff !important; text-decoration: none; font-weight: 700; }
    .chats-stream { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 8px 7% 16px; display: flex; flex-direction: column; gap: 2px; }
    .chats-head, .chats-compose, .chat-note { flex: none; }
    .bubble { flex: none; align-self: flex-start; max-width: min(680px, 82%); margin: 0; padding: 3px 8px 2px; border-radius: 8px; line-height: 1.25; white-space: pre-wrap; word-break: break-word; color: var(--beyond-text); }
    .bubble.in { background: #fff; border: 1px solid #e3e9f4; }
    .bubble.out { align-self: flex-end; background: #e7eef8; border: 1px solid #d5e2f4; margin-left: auto; }
    .bubble time { float: right; margin: 6px 0 0 8px; color: var(--beyond-muted); font-size: 11px; line-height: 1; }
    .bubble .tick { margin-left: 3px; letter-spacing: -1px; }
    .bubble .tick.read { color: var(--beyond-primary); }
    .bubble .tick.failed { color: #b42318; }
    .day-chip { align-self: center; background: #fff; border: 1px solid #e3e9f4; color: var(--beyond-muted); border-radius: 8px; font-size: 12px; padding: 3px 10px; margin: 8px 0 4px; }
    .chats-compose { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; padding: 10px 16px; background: var(--beyond-card); border-top: 1px solid #e3e9f4; }
    .chats-compose textarea { flex: 1; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 44px; max-height: 120px; padding: 10px 12px; resize: none; }
    .chats-compose button { border: 0; border-radius: 50%; width: 44px; height: 44px; background: var(--beyond-primary); color: #fff; font-weight: 800; cursor: pointer; }
    .chats-empty { margin: auto; text-align: center; color: var(--beyond-muted); max-width: 420px; padding: 24px; }
    .chats-empty strong { display: block; color: var(--beyond-text); font-size: 28px; margin-bottom: 8px; }
    .chat-note { background: #fff8e6; color: #7a5b10; border-bottom: 1px solid #ead79a; padding: 8px 16px; font-size: 13px; }
    .twilio-screen { display: flex; flex-direction: column; height: calc(100vh - 150px); min-height: 640px; background: var(--beyond-bg); }
    .sent-log { flex: 0 0 auto; max-height: 38%; min-height: 168px; overflow: auto; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .sent-log h2 { position: sticky; top: 0; margin: 0; padding: 12px 16px 8px; background: var(--beyond-card); color: var(--beyond-text); font-size: 16px; }
    .sent-log p { margin: 0; padding: 0 16px 10px; color: var(--beyond-muted); font-size: 13px; }
    .sent-table { width: 100%; border-collapse: collapse; }
    .sent-table th, .sent-table td { padding: 8px 12px; border-top: 1px solid #e3e9f4; text-align: left; vertical-align: top; font-size: 13px; color: var(--beyond-text); }
    .sent-table th { color: var(--beyond-muted); font-size: 12px; font-weight: 700; }
    .sent-table a { color: var(--beyond-primary); font-weight: 700; text-decoration: none; }
    .sent-table tr.is-on { background: #e7eef8; }
    .sent-table .msg { max-width: 520px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sent-empty { padding: 18px 16px 22px; color: var(--beyond-muted); }
    .sent-failed { color: #b42318; font-weight: 700; }
    .chats-label { flex: none; padding: 10px 14px 0; font-size: 13px; font-weight: 700; color: var(--beyond-muted); background: var(--beyond-card); }
    .twilio-screen .chats-app { flex: 1 1 auto; height: auto; min-height: 320px; }
    @media (max-width: 800px) {
        .twilio-screen { height: auto; min-height: 0; }
        .sent-log { max-height: 280px; }
        .twilio-screen .chats-app { height: calc(100vh - 220px); min-height: 420px; }
        .chats-app { height: calc(100vh - 180px); min-height: 420px; }
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
        .chat-back { display: inline-flex; }
        .chats-stream { padding: 8px 12px 12px; }
        .bubble { max-width: 92%; }
        .chats-compose { padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); }
        .chats-head { padding: 8px 10px; }
    }
</style>
@php
    $line = $inbox['number'] !== '' ? $inbox['number'] : 'Twilio';
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $showJustSent = $open && is_array($justSent) && ($justSent['digits'] ?? '') === $open['digits'];
    if ($showJustSent) {
        $last = count($open['messages']) ? $open['messages'][count($open['messages']) - 1] : null;
        if ($last && $last['out'] === '1' && trim((string) $last['body']) === trim((string) $justSent['body'])) {
            $showJustSent = false;
        }
    }
    $sent = isset($inbox['sent']) && is_array($inbox['sent']) ? $inbox['sent'] : [];
@endphp
<div class="twilio-screen">
    <section class="sent-log" id="twilio-sent">
        <h2>Sent messages</h2>
        <p>{{ count($sent) }} message{{ count($sent) === 1 ? '' : 's' }} from {{ $line }}</p>
        @if(empty($inbox['ok']))
            <div class="sent-empty">{{ $inbox['error'] ?: 'Twilio chats could not be loaded.' }}</div>
        @elseif(count($sent) === 0)
            <div class="sent-empty">No sent messages on this Twilio number yet.</div>
        @else
            <table class="sent-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sent as $row)
                        @php
                            $preview = isset($row['text']) && $row['text'] !== '' ? $row['text'] : 'Template message';
                        @endphp
                        <tr class="{{ $open && $open['digits'] === $row['digits'] ? 'is-on' : '' }}">
                            <td>{{ $row['when'] }}</td>
                            <td><a href="{{ route('whatsapp.twilio_log', ['chat' => $row['digits']]) }}#twilio-chat">{{ $row['who'] }}</a></td>
                            <td class="{{ $row['tick'] === 'failed' ? 'sent-failed' : '' }}">{{ $row['status'] !== '' ? $row['status'] : 'sent' }}</td>
                            <td class="msg" title="{{ $preview }}">{{ $preview }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
<div class="chats-app {{ $open ? 'has-open' : '' }}" id="twilio-chat">
    <aside class="chats-side">
        <div class="chats-label">Chat</div>
        <div class="chats-search">
            <input type="search" id="chatSearch" placeholder="Search a number" autocomplete="off">
        </div>
        <div class="chats-list" id="chatList">
            <div class="chat-none" id="chatNone" @if(count($inbox['chats']) === 0) style="display:block" @endif>
                @if(empty($inbox['ok']))
                    {{ $inbox['error'] ?: 'Twilio chats could not be loaded.' }}
                @else
                    No conversations yet.
                @endif
            </div>
            @foreach($inbox['chats'] as $row)
                <a class="chat-row {{ $open && $open['digits'] === $row['digits'] ? 'is-on' : '' }}" data-name="{{ strtolower($row['phone']) }}" href="{{ route('whatsapp.twilio_log', ['chat' => $row['digits']]) }}#twilio-chat">
                    <span class="chat-avatar">{{ strtoupper(substr(ltrim($row['phone'], '+'), 0, 1)) }}</span>
                    <span class="chat-main">
                        <span class="chat-top">
                            <span class="chat-name">{{ $row['phone'] }}</span>
                            <span class="chat-time">{{ $row['list_time'] }}</span>
                        </span>
                        <span class="chat-bottom">
                            <span class="chat-preview">{{ $row['preview'] !== '' ? $row['preview'] : '—' }}</span>
                            @if(!empty($row['unread']))
                                <span class="chat-badge">1</span>
                            @endif
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </aside>
    <section class="chats-thread">
        @if($open)
            <header class="chats-head">
                <div class="chats-head-person">
                    <a class="chat-back" href="{{ route('whatsapp.twilio_log') }}" aria-label="Back to chats">‹</a>
                    <div>
                        <h2>{{ $open['phone'] }}</h2>
                        <p>Twilio · {{ $line }}</p>
                    </div>
                </div>
                <a class="call-btn" href="tel:+{{ $open['digits'] }}"><i class="fa fa-phone"></i> Call</a>
            </header>
            @if(session('not_permitted'))<div class="chat-note">{{ session('not_permitted') }}</div>@endif
            <div class="chats-stream" id="chatStream">
                @php $prevDay = null; @endphp
                @foreach($open['messages'] as $message)
                    @if($message['day'] !== $prevDay)
                        @php
                            $prevDay = $message['day'];
                            $dayLabel = 'Earlier';
                            if ($message['day'] === $today) $dayLabel = 'Today';
                            elseif ($message['day'] === $yesterday) $dayLabel = 'Yesterday';
                            elseif ($message['day'] !== '') $dayLabel = date('M j, Y', strtotime($message['day']));
                        @endphp
                        <div class="day-chip">{{ $dayLabel }}</div>
                    @endif
                    @include('whatsapp_hub.partials.chat_bubble', [
                        'out' => $message['out'] === '1',
                        'text' => !empty($message['text']) ? $message['text'] : ($message['body'] !== '' ? $message['body'] : ((int) $message['media'] > 0 ? 'Attachment' : 'Template message')),
                        'time' => $message['time'],
                        'tick' => $message['tick'],
                        'day' => $message['day'],
                    ])
                @endforeach
                @if($showJustSent)
                    @if(($justSent['day'] ?? '') !== $prevDay)
                        <div class="day-chip">Today</div>
                    @endif
                    @include('whatsapp_hub.partials.chat_bubble', [
                        'out' => true,
                        'text' => $justSent['body'],
                        'time' => $justSent['time'],
                        'tick' => 'sent',
                        'day' => $justSent['day'] ?? $today,
                    ])
                @endif
            </div>
            @if($canReply)
                <form class="chats-compose" method="POST" action="{{ route('whatsapp.twilio_log.reply') }}">
                    @csrf
                    <input type="hidden" name="to" value="{{ $open['phone'] }}">
                    <input type="hidden" name="chat" value="{{ $open['digits'] }}">
                    <textarea name="body" id="chatBody" placeholder="Type a message"></textarea>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @else
            <div class="chats-empty">
                <strong>Twilio</strong>
                @if(empty($inbox['ok']))
                    {{ $inbox['error'] ?: 'Twilio chats could not be loaded.' }}
                @else
                    Messages on {{ $line }}. Pick a person on the left to open the thread and reply.
                @endif
            </div>
        @endif
    </section>
</div>
</div>
<script>
(function () {
    var search = document.getElementById('chatSearch');
    var list = document.getElementById('chatList');
    var none = document.getElementById('chatNone');
    function applyList() {
        if (!list) return;
        var q = search ? search.value.toLowerCase().trim() : '';
        var shown = 0;
        list.querySelectorAll('.chat-row').forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            var ok = !q || name.indexOf(q) !== -1;
            row.style.display = ok ? '' : 'none';
            if (ok) shown += 1;
        });
        if (none) none.style.display = shown ? 'none' : 'block';
    }
    if (search) search.addEventListener('input', applyList);
    var stream = document.getElementById('chatStream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var body = document.getElementById('chatBody');
    if (body) {
        body.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                if (body.value.trim() !== '') body.form.submit();
            }
        });
        body.form.addEventListener('submit', function (event) {
            if (body.value.trim() === '') event.preventDefault();
        });
    }
})();
</script>
@endsection
