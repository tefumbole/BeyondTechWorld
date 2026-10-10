@extends('layout.main')

@section('content')
<style>
    #content { display: flex; flex-direction: column; height: calc(100dvh - 70px); max-height: calc(100dvh - 70px); padding: 0 !important; background: var(--beyond-bg); overflow: hidden; }
    #content > .container-fluid { flex: none; }
    .main-footer { display: none !important; }
    .chats-app { display: flex; flex: 1 1 auto; height: 0; min-height: 0; background: var(--beyond-bg); color: var(--beyond-text); overflow: hidden; }
    .chat-rail { width: 76px; flex: none; min-height: 0; display: flex; flex-direction: column; gap: 4px; padding: 10px 6px; background: var(--beyond-primary); overflow-y: auto; -webkit-overflow-scrolling: touch; }
    .chat-rail a { display: flex; flex-direction: column; align-items: center; gap: 3px; color: rgba(255,255,255,.82); text-decoration: none; border-radius: 10px; padding: 8px 2px; font-size: 11px; font-weight: 700; text-align: center; }
    .chat-rail a i { font-size: 16px; color: var(--beyond-accent); }
    .chat-rail a.is-on, .chat-rail a:hover { background: rgba(255,255,255,.12); color: #fff; }
    .chats-side { width: 340px; max-width: 38vw; flex: none; display: flex; flex-direction: column; min-height: 0; height: 100%; overflow: hidden; border-right: 1px solid #e3e9f4; background: var(--beyond-card); }
    .call-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; min-height: 32px; padding: 0 12px; background: var(--beyond-primary); color: #fff !important; text-decoration: none; font-weight: 700; }
    .chats-search { flex: none; padding: 10px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chats-search input { width: 100%; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 38px; padding: 0 12px; }
    .chats-search input::placeholder { color: var(--beyond-muted); }
    .chat-filters { flex: none; display: flex; gap: 6px; overflow-x: auto; padding: 8px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chat-filters button { border: 1px solid #d5deee; background: #fff; color: var(--beyond-text); border-radius: 999px; min-height: 30px; padding: 0 12px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .chat-filters button.is-on { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; }
    .chat-filters button span { margin-left: 4px; }
    .chats-list { overflow-x: hidden; overflow-y: auto; flex: 1 1 auto; min-height: 0; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
    .chat-none { display: none; padding: 28px 16px; text-align: center; color: var(--beyond-muted); }
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
    .chats-thread { flex: 1 1 auto; min-width: 0; min-height: 0; height: 100%; overflow: hidden; display: flex; flex-direction: column; background: var(--beyond-bg); }
    .chats-head { min-height: 60px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 16px; }
    .chat-back { display: none; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; color: var(--beyond-primary); font-size: 28px; font-weight: 700; text-decoration: none; flex: none; line-height: 1; }
    .chats-head-person { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .chats-head-person h2, .chats-head-person p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .load-earlier { align-self: center; border: 1px solid #d5deee; background: #fff; color: var(--beyond-primary); border-radius: 999px; min-height: 32px; padding: 0 14px; font-weight: 700; cursor: pointer; margin: 8px 0; }
    .chat-live { display: none; background: #fff8e6; color: #7a5b10; text-align: center; font-size: 13px; font-weight: 700; padding: 6px 10px; }
    .chat-live.is-on { display: block; }
    .chats-head h2 { margin: 0; font-size: 16px; font-weight: 600; color: var(--beyond-text); }
    .chats-head p { margin: 0; color: var(--beyond-muted); font-size: 12px; }
    .mode-switch { display: flex; gap: 6px; }
    .mode-switch button, .announce-btn { border: 1px solid #e3e9f4; border-radius: 999px; min-height: 32px; padding: 0 12px; font-weight: 700; cursor: pointer; background: #fff; color: var(--beyond-text); }
    .mode-switch button.is-on { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; }
    .announce-btn { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; text-decoration: none; display: inline-flex; align-items: center; }
    .chats-stream { flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 8px 7% 16px; display: flex; flex-direction: column; gap: 2px; }
    .chats-head, .chats-compose, .chat-note, .chat-live { flex: none; }
    .bubble { flex: none; align-self: flex-start; max-width: min(680px, 82%); margin: 0; padding: 3px 8px 2px; border-radius: 8px; line-height: 1.25; white-space: pre-wrap; word-break: break-word; color: var(--beyond-text); }
    .bubble.in { background: #fff; border: 1px solid #e3e9f4; }
    .bubble.out { align-self: flex-end; background: #e7eef8; border: 1px solid #d5e2f4; margin-left: auto; }
    .bubble time { float: right; margin: 6px 0 0 8px; color: var(--beyond-muted); font-size: 11px; line-height: 1; }
    .bubble .tick { margin-left: 3px; letter-spacing: -1px; }
    .bubble .tick.read { color: var(--beyond-primary); }
    .bubble .tick.failed { color: #b42318; }
    .bubble img.chat-photo { display: block; max-width: 260px; width: 100%; border-radius: 6px; margin-bottom: 4px; }
    .bubble audio, .bubble video { display: block; max-width: 260px; width: 100%; margin-bottom: 4px; }
    .bubble a.chat-file { color: var(--beyond-primary); font-weight: 700; }
    .bubble .who { display: block; font-size: 12px; font-weight: 700; color: var(--beyond-primary); margin-bottom: 2px; }
    .bubble .choices { clear: both; display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
    .bubble .choice { display: block; background: #fff; border: 1px solid #d5deee; border-radius: 8px; padding: 6px 8px; font-size: 13px; line-height: 1.3; }
    .day-chip { align-self: center; background: #fff; border: 1px solid #e3e9f4; color: var(--beyond-muted); border-radius: 8px; font-size: 12px; padding: 3px 10px; margin: 8px 0 4px; }
    .clip { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; border: 1px solid #e3e9f4; background: var(--beyond-bg); color: var(--beyond-primary); font-size: 24px; font-weight: 600; cursor: pointer; flex: none; overflow: hidden; position: relative; }
    .clip input { display: none; }
    .file-name { align-self: center; color: var(--beyond-muted); font-size: 12px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .chats-compose { display: flex; align-items: center; flex-wrap: nowrap; gap: 8px; padding: 10px 16px; background: var(--beyond-card); border-top: 1px solid #e3e9f4; }
    .chats-compose textarea { flex: 1; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 44px; max-height: 120px; padding: 10px 12px; resize: none; }
    .chats-compose button { border: 0; border-radius: 50%; width: 44px; height: 44px; background: var(--beyond-primary); color: #fff; font-weight: 800; cursor: pointer; }
    .chats-empty { margin: auto; text-align: center; color: var(--beyond-muted); max-width: 420px; padding: 24px; }
    .chats-empty strong { display: block; color: var(--beyond-text); font-size: 28px; margin-bottom: 8px; }
    .group-card { margin: auto; background: var(--beyond-card); border: 1px solid #e3e9f4; border-radius: 16px; padding: 28px 24px; max-width: 460px; text-align: center; color: var(--beyond-text); }
    .group-card h2 { margin: 8px 0; color: var(--beyond-text); }
    .chat-note { background: #fff8e6; color: #7a5b10; border-bottom: 1px solid #ead79a; padding: 8px 16px; font-size: 13px; }
    @media (max-width: 800px) {
        #content { height: calc(100dvh - 56px); max-height: calc(100dvh - 56px); }
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.has-open .chat-rail, .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
        .chat-back { display: inline-flex; }
        .chats-stream { padding: 8px 12px 12px; }
        .bubble { max-width: 92%; }
        .chats-compose { padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); }
        .chats-head { padding: 8px 10px; }
        .mode-switch button, .announce-btn, .call-btn { min-height: 36px; }
        .beyond-module-tabs { margin-bottom: 0; }
        .beyond-module-tabs-label { display: none; }
        .beyond-module-tabs-nav { flex-wrap: nowrap !important; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 8px; }
        .beyond-module-tab { padding: 8px 12px; font-size: 12px; }
        .chat-row { padding: 12px; }
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
        @php
            $unreadChats = $conversations->filter(function ($row) { return (int) $row->unread_count > 0; })->count();
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
            <div class="chat-none" id="chatNone">Nothing in this list.</div>
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
                @endphp
                <a class="chat-row {{ $open && (int) $open->id === (int) $row->id ? 'is-on' : '' }}" data-id="{{ $row->id }}" data-kind="person" data-read="{{ (int) $row->unread_count > 0 ? '0' : '1' }}" data-name="{{ strtolower($person.' '.optional($row->contact)->display_phone) }}" href="{{ route('whatsapp.chats', ['chat' => $row->id]) }}">
                    <span class="chat-avatar">{{ strtoupper(substr($person, 0, 1)) }}</span>
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
                            @if((int) $row->unread_count > 0)
                                <span class="chat-badge">{{ $row->unread_count }}</span>
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
                        ['Settings', 'Assistant and WhatsApp settings', route('whatsapp.settings')],
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
                    <a class="chat-back" href="{{ route('whatsapp.chats') }}" aria-label="Back to chats">‹</a>
                    <div>
                        <h2>{{ $title }}</h2>
                        <p>{{ optional($open->contact)->display_phone }} · {{ $open->mode === 'AI' ? 'AI mode' : 'Human mode' }}</p>
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
    var filter = @json($pane === 'chats' && $group ? 'groups' : 'all');
    function applyList() {
        if (!list) return;
        var q = search ? search.value.toLowerCase().trim() : '';
        var shown = 0;
        list.querySelectorAll('.chat-row').forEach(function (row) {
            var name = row.getAttribute('data-name') || '';
            var kind = row.getAttribute('data-kind') || 'person';
            var read = row.getAttribute('data-read') === '1';
            var ok = !q || name.indexOf(q) !== -1;
            if (filter === 'unread') ok = ok && kind === 'person' && !read;
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
                applyList();
            });
        });
    }
    if (search) search.addEventListener('input', applyList);
    applyList();
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
        var anchor = list.querySelector('.chat-row[data-kind="group"]');
        items.forEach(function (item) {
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
            row.innerHTML = '<span class="chat-avatar">' + esc(initial) + '</span><span class="chat-main"><span class="chat-top"><span class="chat-name">' + esc(item.name) + '</span><span class="chat-time">' + esc(item.time) + '</span></span><span class="chat-bottom"><span class="chat-preview">' + ai + esc(item.preview || '') + '</span>' + badge + '</span></span>';
            if (anchor) list.insertBefore(row, anchor);
            else list.appendChild(row);
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
