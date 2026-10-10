@extends('layout.main')

@section('content')
<style>
    #content { padding: 0 !important; background: var(--beyond-bg); }
    #content > .container-fluid { display: none; }
    .main-footer { display: none !important; }
    .chats-app { display: flex; height: calc(100vh - 70px); min-height: 0; background: var(--beyond-bg); color: var(--beyond-text); overflow: hidden; }
    .chat-rail { width: 76px; flex: none; display: flex; flex-direction: column; gap: 4px; padding: 10px 6px; background: var(--beyond-primary); }
    .chat-rail a { display: flex; flex-direction: column; align-items: center; gap: 3px; color: rgba(255,255,255,.82); text-decoration: none; border-radius: 10px; padding: 8px 2px; font-size: 11px; font-weight: 700; text-align: center; }
    .chat-rail a i { font-size: 16px; color: var(--beyond-accent); }
    .chat-rail a.is-on, .chat-rail a:hover { background: rgba(255,255,255,.12); color: #fff; }
    .chats-side { width: 340px; max-width: 38vw; flex: none; display: flex; flex-direction: column; border-right: 1px solid #e3e9f4; background: var(--beyond-card); }
    .call-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; min-height: 32px; padding: 0 12px; background: var(--beyond-primary); color: #fff !important; text-decoration: none; font-weight: 700; }
    .chats-search { padding: 10px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chats-search input { width: 100%; border: 1px solid #e3e9f4; border-radius: 8px; background: var(--beyond-bg); color: var(--beyond-text); min-height: 38px; padding: 0 12px; }
    .chats-search input::placeholder { color: var(--beyond-muted); }
    .chat-filters { display: flex; gap: 6px; overflow-x: auto; padding: 8px 12px; background: var(--beyond-card); border-bottom: 1px solid #e3e9f4; }
    .chat-filters button { border: 1px solid #d5deee; background: #fff; color: var(--beyond-text); border-radius: 999px; min-height: 30px; padding: 0 12px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .chat-filters button.is-on { background: var(--beyond-primary); border-color: var(--beyond-primary); color: #fff; }
    .chat-filters button span { margin-left: 4px; }
    .chats-list { overflow: auto; flex: 1; }
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
        .chats-app { height: calc(100vh - 56px); }
        .chats-side { width: 100%; max-width: none; }
        .chats-thread { display: none; }
        .chats-app.has-open .chat-rail, .chats-app.has-open .chats-side { display: none; }
        .chats-app.has-open .chats-thread { display: flex; }
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
                <a class="chat-row {{ $open && (int) $open->id === (int) $row->id ? 'is-on' : '' }}" data-kind="person" data-read="{{ (int) $row->unread_count > 0 ? '0' : '1' }}" data-name="{{ strtolower($person.' '.optional($row->contact)->display_phone) }}" href="{{ route('whatsapp.chats', ['chat' => $row->id]) }}">
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
                <div>
                    <h2>{{ $title }}</h2>
                    <p>{{ optional($open->contact)->display_phone }} · {{ $open->mode === 'AI' ? 'AI mode' : 'Human mode' }}</p>
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
            <div class="chats-stream" id="chatStream">
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
                <div>
                    <h2>{{ $group['name'] }}</h2>
                    <p>{{ $group['members'] === null ? 'WhatsApp group' : number_format($group['members']).' contacts' }}</p>
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
