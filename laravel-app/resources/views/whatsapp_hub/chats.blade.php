@extends('layout.main')

@section('content')
<style>
    #content { padding: 0 !important; background: var(--beyond-bg); }
    #content > .container-fluid { display: none; }
    .chats-app { display: flex; height: calc(100vh - 70px); min-height: 520px; background: var(--beyond-bg); color: var(--beyond-text); }
    .chats-side { width: 380px; max-width: 42vw; flex: none; display: flex; flex-direction: column; border-right: 1px solid #e3e9f4; background: var(--beyond-card); }
    .chats-search { padding: 10px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chats-search input { width: 100%; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 38px; padding: 0 12px; }
    .chats-search input::placeholder { color: var(--beyond-muted); }
    .chats-list { overflow: auto; flex: 1; }
    .chat-row { display: flex; gap: 12px; align-items: center; padding: 10px 14px; color: var(--beyond-text); text-decoration: none; border-bottom: 1px solid #e3e9f4; }
    .chat-row:hover { background: var(--beyond-bg); color: var(--beyond-text); text-decoration: none; }
    .chat-row.is-on { background: #e7eef8; }
    .chat-avatar { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; flex: none; background: var(--beyond-primary); }
    .chat-avatar.is-group { background: var(--beyond-accent); color: #10213d; }
    .chat-main { min-width: 0; flex: 1; }
    .chat-top, .chat-bottom { display: flex; justify-content: space-between; gap: 8px; }
    .chat-name { font-weight: 600; color: var(--beyond-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-time { color: var(--beyond-muted); font-size: 12px; flex: none; }
    .chat-preview { color: var(--beyond-muted); font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-badge { background: var(--beyond-primary); color: #fff; border-radius: 999px; min-width: 18px; height: 18px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; }
    .chat-ai { color: var(--beyond-primary); font-size: 11px; font-weight: 700; }
    .chats-thread { flex: 1; min-width: 0; display: flex; flex-direction: column; background: var(--beyond-bg); }
    .chats-head { min-height: 60px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 16px; }
    .chats-head h2 { margin: 0; font-size: 16px; font-weight: 600; color: var(--beyond-text); }
    .chats-head p { margin: 0; color: var(--beyond-muted); font-size: 12px; }
    .mode-switch { display: flex; gap: 6px; }
    .mode-switch button, .announce-btn { border: 1px solid #e3e9f4; border-radius: 999px; min-height: 32px; padding: 0 12px; font-weight: 700; cursor: pointer; background: #fff; color: var(--beyond-text); }
    .mode-switch button.is-on { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; }
    .announce-btn { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
    .chats-stream { flex: 1; overflow: auto; padding: 8px 7% 10px; display: flex; flex-direction: column; gap: 2px; }
    .bubble { max-width: min(680px, 82%); margin: 0; padding: 3px 8px 2px; border-radius: 8px; line-height: 1.25; white-space: pre-wrap; word-break: break-word; color: var(--beyond-text); }
    .bubble.in { background: #fff; border: 1px solid #e3e9f4; }
    .bubble.out { background: #e7eef8; border: 1px solid #d5e2f4; margin-left: auto; }
    .bubble time { float: right; margin: 6px 0 0 8px; color: var(--beyond-muted); font-size: 11px; line-height: 1; }
    .chats-compose { display: flex; gap: 8px; padding: 10px 16px; background: var(--beyond-card); border-top: 1px solid #e3e9f4; }
    .chats-compose textarea { flex: 1; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 44px; max-height: 120px; padding: 10px 12px; resize: none; }
    .chats-compose button { border: 0; border-radius: 50%; width: 44px; height: 44px; background: var(--beyond-primary); color: #fff; font-weight: 800; cursor: pointer; }
    .chats-empty { margin: auto; text-align: center; color: var(--beyond-muted); max-width: 420px; padding: 24px; }
    .chats-empty strong { display: block; color: var(--beyond-text); font-size: 28px; margin-bottom: 8px; }
    .group-card { margin: auto; background: var(--beyond-card); border: 1px solid #e3e9f4; border-radius: 16px; padding: 28px 24px; max-width: 460px; text-align: center; color: var(--beyond-text); }
    .group-card h2 { margin: 8px 0; color: var(--beyond-text); }
    .chat-note { background: #fff8e6; color: #7a5b10; border-bottom: 1px solid #ead79a; padding: 8px 16px; font-size: 13px; }
    @media (max-width: 800px) {
        .chats-app { height: calc(100vh - 56px); }
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
    }
</style>
<div class="chats-app {{ ($open || $group) ? 'has-open' : '' }}">
    <aside class="chats-side">
        <div class="chats-search">
            <input type="search" id="chatSearch" placeholder="Search a name" autocomplete="off">
        </div>
        <div class="chats-list" id="chatList">
            @foreach($conversations as $row)
                @php
                    $person = optional($row->contact)->displayName() ?: 'WhatsApp';
                    $when = $row->last_activity_at ?: $row->updated_at;
                @endphp
                <a class="chat-row {{ $open && (int) $open->id === (int) $row->id ? 'is-on' : '' }}" data-name="{{ strtolower($person.' '.optional($row->contact)->display_phone) }}" href="{{ route('whatsapp.chats', ['chat' => $row->id]) }}">
                    <span class="chat-avatar">{{ strtoupper(substr($person, 0, 1)) }}</span>
                    <span class="chat-main">
                        <span class="chat-top">
                            <span class="chat-name">{{ $person }}</span>
                            <span class="chat-time">{{ $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '' }}</span>
                        </span>
                        <span class="chat-bottom">
                            <span class="chat-preview">
                                @if($row->mode === 'AI')<span class="chat-ai">AI </span>@endif
                                {{ $row->last_message }}
                            </span>
                            @if((int) $row->unread_count > 0)
                                <span class="chat-badge">{{ $row->unread_count }}</span>
                            @endif
                        </span>
                    </span>
                </a>
            @endforeach
            @foreach($groups as $row)
                <a class="chat-row {{ $group && $group['jid'] === $row['jid'] ? 'is-on' : '' }}" data-name="{{ strtolower($row['name']) }}" href="{{ route('whatsapp.chats', ['group' => $row['jid']]) }}">
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
        </div>
    </aside>
    <section class="chats-thread">
        @if($open)
            @php $title = optional($open->contact)->displayName() ?: 'WhatsApp'; @endphp
            <header class="chats-head">
                <div>
                    <h2>{{ $title }}</h2>
                    <p>{{ optional($open->contact)->display_phone }} · {{ $open->mode === 'AI' ? 'AI mode' : 'Human mode' }}</p>
                </div>
                <div class="mode-switch">
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
            <div class="chats-stream" id="chatStream">
                @forelse($messages as $message)
                    @php
                        $text = trim((string) $message->body);
                        $text = preg_replace("/[ \t]+\n/", "\n", $text);
                        $text = preg_replace("/\n{2,}/", "\n", $text);
                    @endphp
                    <div class="bubble {{ $message->direction === 'OUTGOING' ? 'out' : 'in' }}">
                        {{ $text }}
                        <time>{{ $message->created_at ? $message->created_at->format('H:i') : '' }}</time>
                    </div>
                @empty
                    <div class="chats-empty"><strong>No messages yet</strong>Send the first message below.</div>
                @endforelse
            </div>
            @if($canReply)
                <form class="chats-compose" method="POST" action="{{ route('whatsapp.chats.reply', $open->id) }}">
                    @csrf
                    <textarea name="body" id="chatBody" placeholder="Type a message" required></textarea>
                    <button type="submit" aria-label="Send">➤</button>
                </form>
            @endif
        @elseif($group)
            <header class="chats-head">
                <div>
                    <h2>{{ $group['name'] }}</h2>
                    <p>{{ $group['members'] === null ? 'WhatsApp group' : number_format($group['members']).' contacts' }}</p>
                </div>
                <a class="announce-btn" href="{{ route('announcements.compose', ['group' => $group['jid']]) }}">Create announcement</a>
            </header>
            <div class="chats-stream">
                <div class="group-card">
                    <span class="chat-avatar is-group" style="margin:0 auto;width:72px;height:72px;font-size:28px;">G</span>
                    <h2>{{ $group['name'] }}</h2>
                    <p>This is a WhatsApp group. Open it here, then create an announcement for its members.</p>
                    <a class="announce-btn" href="{{ route('announcements.compose', ['group' => $group['jid']]) }}">Create announcement</a>
                </div>
            </div>
        @else
            <div class="chats-empty">
                <strong>Chats</strong>
                Search a name on the left. A person opens the WhatsApp thread. A group opens Create announcement.
            </div>
        @endif
    </section>
</div>
<script>
(function () {
    var search = document.getElementById('chatSearch');
    var list = document.getElementById('chatList');
    if (search && list) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            list.querySelectorAll('.chat-row').forEach(function (row) {
                var name = row.getAttribute('data-name') || '';
                row.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
    var stream = document.getElementById('chatStream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var body = document.getElementById('chatBody');
    if (body) {
        body.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                body.form.submit();
            }
        });
    }
    @if($open)
    var seen = {{ (int) optional($messages->last())->id }};
    setInterval(function () {
        if (body && body.value.trim() !== '') return;
        fetch(@json(route('whatsapp.conversation', $open->id).'?poll=1'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            var last = data && data.messages && data.messages.length ? data.messages[data.messages.length - 1].id : 0;
            if (last && last !== seen) window.location.reload();
        }).catch(function () {});
    }, 6000);
    @endif
})();
</script>
@endsection
