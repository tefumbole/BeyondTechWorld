@extends('beyond.layout')

@section('title', 'Whats MAI')

@push('head')
<style>
    .mai-plan { max-width: 460px; margin: 0 auto; background: linear-gradient(165deg, rgba(8,32,78,.96), rgba(4,16,42,.94)); color: #fff; border: 1px solid rgba(212,175,55,.65); border-radius: 28px; padding: 1.4rem 1.2rem 1.6rem; }
    .mai-plan h1, .mai-plan p, .mai-plan label { color: #fff; }
    .gold { background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 800; padding: 0.75rem 1.2rem; margin-top: 1rem; cursor: pointer; }
    .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 0.65rem 0.75rem; }
    .bad { background: #fdecec; color: #7f1d1d; border-radius: 12px; padding: 0.65rem 0.75rem; }
    .chat { margin-top: 0.8rem; display: grid; gap: 8px; }
    .chat p { margin: 0; background: rgba(255,255,255,.06); border-radius: 12px; padding: 0.55rem 0.7rem; }
    .field { width: 100%; margin-top: 0.45rem; border-radius: 12px; border: 1px solid rgba(212,175,55,.55); background: #fff; color: #10233f; padding: 0.7rem 0.8rem; }
</style>
@endpush

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-plan">
            <p style="text-align:right;margin:0;"><a href="{{ route('whatsapp.mai.plan') }}" style="color:#F5D76E;font-weight:800;">Close</a></p>
            <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="64" height="72" style="width:64px;height:72px;object-fit:contain">
            <h1>Link WhatsApp</h1>
            @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            @if(!empty($session['error']))<p class="bad">{{ $session['error'] }}</p>@endif
            <div id="link-wait" @if(($session['status'] ?? '') === 'CONNECTED') hidden @endif>
                <p>A session is open for <strong>{{ $phone }}</strong>. On your phone, open WhatsApp, tap Linked devices, then scan this code or enter the link code.</p>
                <p id="link-state">Waiting for this phone to approve the link.</p>
                <img id="link-qr" alt="WhatsApp QR code" width="220" height="220" style="background:#fff;border-radius:16px;{{ empty($session['qr']) ? 'display:none;' : '' }}" @if(!empty($session['qr'])) src="{{ $session['qr'] }}" @endif>
                <p id="link-code" style="font-size:1.4rem;letter-spacing:0.12em;font-weight:800;color:#F5D76E;">{{ $session['pairing_code'] }}</p>
                <form method="POST" action="{{ route('whatsapp.mai.link.pair') }}">
                    @csrf
                    <button class="gold" type="submit">Link code</button>
                </form>
            </div>
            @if(($session['status'] ?? '') === 'CONNECTED')
                <p id="link-ready">WhatsApp is linked.</p>
            @endif
            <div class="chat" id="link-chats">
                @foreach($messages as $message)
                    <p>{{ !empty($message['from_me']) ? 'You' : 'Them' }}: {{ $message['text'] }}</p>
                @endforeach
            </div>
            @if(($session['status'] ?? '') === 'CONNECTED')
                <form method="POST" action="{{ route('whatsapp.mai.link.send') }}">
                    @csrf
                    <label for="to">Send from your WhatsApp</label>
                    <input class="field" id="to" name="to" inputmode="numeric" placeholder="Other phone number" autocomplete="off">
                    <input class="field" name="text" placeholder="Message" autocomplete="off">
                    <button class="gold" type="submit">Send</button>
                </form>
            @endif
            <p style="margin-top:0.8rem;"><a href="{{ route('whatsapp.mai.plan') }}" style="color:#F5D76E;">Choose a plan</a></p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    function paint(data) {
        var state = document.getElementById('link-state');
        var qr = document.getElementById('link-qr');
        var code = document.getElementById('link-code');
        var waiting = document.getElementById('link-wait');
        if (data.status === 'CONNECTED') {
            if (waiting) waiting.hidden = true;
            if (!document.getElementById('link-ready')) {
                var ready = document.createElement('p');
                ready.id = 'link-ready';
                ready.textContent = 'WhatsApp is linked.';
                if (waiting && waiting.parentNode) waiting.parentNode.insertBefore(ready, waiting);
            }
        } else if (waiting) {
            waiting.hidden = false;
        }
        if (state && data.status !== 'CONNECTED') state.textContent = 'Waiting for this phone to approve the link.';
        if (qr && data.qr && data.status !== 'CONNECTED') {
            qr.src = data.qr;
            qr.style.display = 'block';
        }
        if (code && data.status !== 'CONNECTED') code.textContent = data.pairing_code || '';
        var box = document.getElementById('link-chats');
        if (box && data.status === 'CONNECTED' && Array.isArray(data.messages)) {
            box.innerHTML = '';
            data.messages.forEach(function (row) {
                var line = document.createElement('p');
                line.textContent = (row.from_me ? 'You: ' : 'Them: ') + (row.text || '');
                box.appendChild(line);
            });
        }
    }
    setInterval(function () {
        fetch('{{ route('whatsapp.mai.link.status') }}', { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(paint)
            .catch(function () {});
    }, 4000);
})();
</script>
@endpush
