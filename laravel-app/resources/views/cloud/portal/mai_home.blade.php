@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Whats MAI is open for this phone.')

@push('head')
<link rel="manifest" href="{{ asset('whats-mai.webmanifest') }}">
<meta name="theme-color" content="#072f6b">
<style>
    .mai-home { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.4rem 1.2rem 1.6rem; }
    .mai-home h1 { margin: 0.2rem 0 0.4rem; font-size: 1.8rem; color: #fff; }
    .mai-home p { color: rgba(255,255,255,.92); }
    .gold { display: inline-block; background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.75rem 1.2rem; margin-top: 1rem; cursor: pointer; text-decoration: none; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; }
</style>
@endpush

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-home">
            <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="64" height="72" style="width:64px;height:72px;object-fit:contain">
            <h1>Whats MAI</h1>
            <p>Hello {{ $user->name }}.</p>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="note">{{ session('not_permitted') }}</p>@endif
            @if(($session['status'] ?? '') === 'CONNECTED')
                <p>WhatsApp for +{{ preg_replace('/\D/', '', (string) ($linkedPhone ?? $user->phone)) }}.</p>
                @if(!empty($openChat))
                    <p><a href="{{ route('whatsapp.mai.home') }}" style="color:#F5D76E;">All chats</a></p>
                    @forelse(($messages ?? []) as $row)
                        <p class="note"><strong>{{ !empty($row['from_me']) ? 'You' : '+' . $openChat }}</strong> {{ $row['text'] }}</p>
                    @empty
                        <p>No messages in this chat yet.</p>
                    @endforelse
                    <form method="POST" action="{{ route('whatsapp.mai.link.send') }}">
                        @csrf
                        <input type="hidden" name="next" value="home">
                        <input type="hidden" name="to" value="{{ $openChat }}">
                        <input name="text" placeholder="Message" required style="width:100%;border:0;border-radius:999px;min-height:44px;padding:0.6rem 1rem;margin-top:0.6rem;">
                        <button class="gold" type="submit">Send</button>
                    </form>
                @else
                    @forelse(($chats ?? []) as $chat)
                        <p class="note"><a href="{{ route('whatsapp.mai.home', ['chat' => $chat['phone']]) }}" style="color:#fff;text-decoration:none;"><strong>{{ $chat['name'] ?: ('+'.$chat['phone']) }}</strong>@if(!empty($chat['unread'])) ({{ $chat['unread'] }})@endif<br>{{ $chat['preview'] }}</a></p>
                    @empty
                        <p>Chats from this phone will appear here.</p>
                    @endforelse
                @endif
            @else
                <p>This computer is open for {{ $user->phone }}. WhatsApp is not linked yet. Scan the QR code on the link page, then open Whats MAI here.</p>
                @if(!empty($session['error']))<p class="note">{{ $session['error'] }}</p>@endif
                <p><a href="{{ route('whatsapp.mai.link') }}" style="color:#F5D76E;">Link WhatsApp</a></p>
                <p><a class="gold" href="{{ route('whatsapp.mai.plan') }}">Choose a longer plan</a></p>
                <button type="button" id="install-android" class="gold">Install on Android</button>
                <p id="install-note" hidden>On Android, open this page in Chrome, tap the menu, then Install app or Add to Home screen.</p>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var promptEvent = null;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ url('/whatsapp-mai/sw.js') }}', { scope: '/whatsapp-mai/' }).catch(function () {});
    }
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        promptEvent = event;
    });
    @if(($session['status'] ?? '') === 'CONNECTED' && empty($chats) && empty($openChat))
    setTimeout(function () { window.location.reload(); }, 5000);
    @endif
    var button = document.getElementById('install-android');
    if (!button) return;
    button.onclick = function () {
        if (promptEvent) {
            promptEvent.prompt();
            return;
        }
        var note = document.getElementById('install-note');
        if (note) note.hidden = false;
    };
})();
</script>
@endpush
