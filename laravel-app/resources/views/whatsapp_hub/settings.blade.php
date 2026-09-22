@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">WhatsApp Hub Settings</h1>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif

        <div class="wa-card">
            <h5>Provider</h5>
            <p>Connection: <span class="wa-badge {{ !empty($session['connected']) ? 'wa-badge-ok' : 'wa-badge-bad' }}">{{ $session['status'] }}</span></p>
            <p>Session name: {{ $session['session_name'] ?: '—' }}</p>
            <p>API key: {{ !empty(config('services.whatsapp.wasender_api_key')) ? 'Configured' : 'Missing' }}</p>
            <p>Webhook secret: {{ trim((string) config('services.whatsapp.wasender_webhook_secret')) !== '' ? 'Configured' : 'Missing' }}</p>
            <p>Webhook URL (subscribe this in WaSender): <code>{{ $webhookUrl }}</code></p>
            <p class="small text-muted">Subscribe events: messages.received, messages.update, message-receipt.update, call.</p>
        </div>

        <div class="wa-card">
            <h5>Conversations</h5>
            <form method="post" action="{{ route('whatsapp.settings.update') }}">
                @csrf
                <label>Default conversation mode (AI is not implemented in Phase 1)</label>
                <select name="default_conversation_mode" class="form-control mb-3" style="max-width:240px">
                    @foreach(['HUMAN','AI','PAUSED','CLOSED'] as $opt)
                        <option value="{{ $opt }}" {{ strtoupper($mode) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</section>
@endsection
