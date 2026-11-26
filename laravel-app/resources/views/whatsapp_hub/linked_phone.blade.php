@extends('beyond.layout')

@section('title', 'WhatsApp +'.$phone)

@section('content')
<section class="py-6" style="background:#eef3fb;min-height:70vh;">
    <div class="max-w-6xl mx-auto px-4">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h1 style="margin:0;color:#072f6b;font-size:1.4rem;">WhatsApp +{{ $phone }}</h1>
            <a href="{{ route('whatsapp.chats', ['line' => 'main']) }}" style="color:#0b3f90;font-weight:700;">Main line</a>
        </div>
        @if(session('message'))<p style="background:#ecfdf3;color:#166534;border-radius:12px;padding:8px 12px;">{{ session('message') }}</p>@endif
        @if(session('not_permitted'))<p style="background:#fdecec;color:#7f1d1d;border-radius:12px;padding:8px 12px;">{{ session('not_permitted') }}</p>@endif
        @if(($session['status'] ?? '') !== 'CONNECTED')
            <p style="color:#16315c;">This phone is not linked yet. <a href="{{ route('whatsapp.mai.link') }}">Link +{{ $phone }}</a></p>
        @else
        <div style="display:grid;grid-template-columns:320px 1fr;gap:12px;align-items:start;">
            <div style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #d8e2f2;">
                @forelse($chats as $chat)
                    <a href="{{ route('whatsapp.chats', ['jid' => $chat['jid']]) }}" style="display:block;padding:12px 14px;text-decoration:none;color:#16315c;border-bottom:1px solid #eef2f8;{{ ($open['jid'] ?? '') === $chat['jid'] ? 'background:#e8f1ff;' : '' }}">
                        <strong>{{ $chat['name'] ?: ('+'.$chat['phone']) }}</strong>
                        @if(!empty($chat['unread']))<span style="float:right;background:#16a34a;color:#fff;border-radius:999px;padding:0 7px;font-size:12px;">{{ $chat['unread'] }}</span>@endif
                        <div style="color:#5c6b84;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $chat['preview'] }}</div>
                    </a>
                @empty
                    <p style="padding:16px;color:#5c6b84;">Chats from +{{ $phone }} will appear here.</p>
                @endforelse
            </div>
            <div style="background:#fff;border-radius:16px;border:1px solid #d8e2f2;min-height:420px;padding:16px;">
                @if(!empty($open))
                    <h2 style="margin:0 0 12px;color:#072f6b;font-size:1.1rem;">{{ $open['name'] ?: $open['jid'] }}</h2>
                    @forelse($messages as $row)
                        <p style="margin:0 0 8px;padding:8px 12px;border-radius:12px;background:{{ !empty($row['from_me']) ? '#e4e7ee' : '#f8fafc' }};color:#16315c;">{{ $row['text'] }}</p>
                    @empty
                        <p style="color:#5c6b84;">No messages in this chat yet.</p>
                    @endforelse
                    <form method="POST" action="{{ route('whatsapp.chats.linked_send') }}" style="margin-top:16px;">
                        @csrf
                        <input type="hidden" name="to" value="{{ $open['jid'] }}">
                        <input name="text" placeholder="Message" required style="width:100%;border:1px solid #d8e2f2;border-radius:999px;min-height:44px;padding:0 14px;">
                        <button type="submit" style="margin-top:8px;background:#D4AF37;color:#072f6b;border:0;border-radius:999px;font-weight:800;padding:8px 18px;">Send</button>
                    </form>
                @else
                    <p style="color:#5c6b84;">Choose a chat from +{{ $phone }}.</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</section>
@if(($session['status'] ?? '') === 'CONNECTED' && count($chats) === 0)
<script>setTimeout(function () { window.location.reload(); }, 5000);</script>
@endif
@endsection
