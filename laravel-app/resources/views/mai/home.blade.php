<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Sessions</title>
    <style>
        body { margin: 0; background: #f4f7fb; color: #072f6b; font-family: Georgia, serif; }
        main { max-width: 820px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #c6ab47; border-radius: 18px; padding: 16px; margin: 12px 0; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 8px; background: #0b3f90; color: #fff; font-size: 0.75rem; }
        input, button { font: inherit; border-radius: 999px; border: 1px solid #0b3f90; padding: 8px 12px; }
        button { background: #0b3f90; color: #fff; cursor: pointer; }
        code, pre { background: #041833; color: #fff; border-radius: 12px; padding: 10px; overflow: auto; }
        .secret { background: #fff7d6; border-radius: 12px; padding: 10px; }
    </style>
</head>
<body>
<main>
    <h1>My Sessions</h1>
    @if(session('message'))<p>{{ session('message') }}</p>@endif
    @if(session('not_permitted'))<p>{{ session('not_permitted') }}</p>@endif
    @if(session('mai_key'))<p class="secret">Copy this token now. It is not shown again.<br><code id="new-token">{{ session('mai_key') }}</code></p>@endif
    <section class="card">
        <h2>Link a number</h2>
        <form method="POST" action="{{ url('/mai/sessions') }}">
            @csrf
            <label>Your WhatsApp number <input name="phone" required></label>
            <button type="submit">Link this number</button>
        </form>
        <p>Use the QR code or the phone-number code on the link page. Both open the same session.</p>
        @if(session('mai_qr'))<p><img alt="WhatsApp QR" src="{{ session('mai_qr') }}"></p>@endif
    </section>
    @foreach($sessions as $session)
        @php $state = \App\Services\Mai\MaiEntitlement::summary($session); @endphp
        <section class="card">
            <h2>{{ $session->display_name ?: 'WhatsApp' }} <span class="badge">{{ $state['connection'] }}</span></h2>
            <p>Session ID <code>{{ $session->public_id }}</code></p>
            <p>Phone {{ $session->phone_number ?: $session->expected_phone }} · Plan {{ $state['subscription'] }} · API {{ $state['api_sending'] ? 'ready' : 'not ready' }}</p>
            @if($session->last_error)<p>{{ $session->last_error }}</p>@endif
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/rename') }}">
                @csrf
                <input name="name" value="{{ $session->display_name }}" placeholder="Session name">
                <button type="submit">Rename</button>
            </form>
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/refresh') }}">@csrf<button type="submit">Refresh status</button></form>
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/disconnect') }}">
                @csrf
                <label><input type="checkbox" name="confirm" value="yes"> I want to disconnect this session</label>
                <button type="submit">Disconnect</button>
            </form>
            <h3>Notification token</h3>
            <form method="POST" action="{{ url('/mai/keys') }}">
                @csrf
                <input type="hidden" name="session" value="{{ $session->public_id }}">
                <input name="name" placeholder="Token label" required>
                <input name="scopes" value="messages">
                <button type="submit">Create token</button>
            </form>
        </section>
    @endforeach
    <section class="card">
        <h2>Tokens</h2>
        @foreach($keys as $key)
            <p>{{ $key->name }} · {{ $key->prefix }}… · {{ $key->scopes }} · {{ $key->revoked_at ? 'Revoked' : 'Active' }} · last used {{ $key->last_used_at ?: 'never' }}</p>
            @if(! $key->revoked_at)
                <form method="POST" action="{{ url('/mai/keys/'.$key->id.'/rotate') }}">@csrf<button type="submit">Replace token</button></form>
                <form method="POST" action="{{ url('/mai/keys/'.$key->id.'/revoke') }}">@csrf<button type="submit">Revoke</button></form>
            @endif
        @endforeach
    </section>
    <section class="card">
        <h2>Send a notification</h2>
        <p>Put the token in your application backend. The session ID identifies the WhatsApp connection. The token authorizes the request. This page never shows the token again after you create it.</p>
        <p>Base URL <code>{{ $apiBase }}</code></p>
        <pre>curl -X POST "{{ $apiBase }}/sessions/SESSION_ID/messages" \
  -H "Authorization: Bearer mai_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: notice-1001" \
  -d '{"to":"237600000000","text":"Your order is ready."}'</pre>
        <p>Success: <code>{"ok":true,"status":"sent","provider_message_id":"MESSAGE_ID"}</code></p>
        <p>Not yet on the phone: <code>{"ok":false,"status":"queued"}</code> or <code>{"ok":false,"status":"uncertain"}</code>. A sent status means the session accepted the message. It does not mean the recipient has read it.</p>
        <p>Check it: <code>GET {{ $apiBase }}/sessions/SESSION_ID/messages/notice-1001</code></p>
    </section>
    <form method="POST" action="{{ url('/mai/logout') }}">@csrf<button type="submit">Sign out</button></form>
</main>
</body>
</html>
