<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Whats MAI</title>
</head>
<body>
    <h1>Whats MAI</h1>
    @if(session('message'))<p>{{ session('message') }}</p>@endif
    @if(session('not_permitted'))<p>{{ session('not_permitted') }}</p>@endif
    @if(session('mai_key'))<p>API key: {{ session('mai_key') }}</p>@endif
    @if(session('mai_qr'))<p><img alt="WhatsApp QR" src="{{ session('mai_qr') }}"></p>@endif
    <form method="POST" action="{{ url('/mai/sessions') }}">
        @csrf
        <label>Your WhatsApp number <input name="phone" required></label>
        <button type="submit">Link this number</button>
    </form>
    @foreach($sessions as $session)
        <section>
            <h2>{{ $session->expected_phone }} — {{ $session->status }}</h2>
            @if($session->last_error)<p>{{ $session->last_error }}</p>@endif
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/refresh') }}">@csrf<button type="submit">Check link</button></form>
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/disconnect') }}">@csrf<button type="submit">Unlink</button></form>
            <form method="POST" action="{{ url('/mai/sessions/'.$session->public_id.'/send') }}">
                @csrf
                <input name="to" placeholder="Other number">
                <input name="text" placeholder="Message">
                <button type="submit">Send text</button>
            </form>
        </section>
    @endforeach
    <h2>API key</h2>
    <form method="POST" action="{{ url('/mai/keys') }}">
        @csrf
        <input name="name" placeholder="Key name">
        <button type="submit">Create key</button>
    </form>
    <p>Text is available. Photos, groups, voice notes, history sync, and read receipts are not available yet.</p>
    <form method="POST" action="{{ url('/mai/logout') }}">@csrf<button type="submit">Sign out</button></form>
</body>
</html>
