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
                <label>Default conversation mode</label>
                <select name="default_conversation_mode" class="form-control mb-3" style="max-width:240px">
                    @foreach(['HUMAN','AI','PAUSED','CLOSED'] as $opt)
                        <option value="{{ $opt }}" {{ strtoupper($mode) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
                <label>SLA — normal (minutes)</label>
                <input type="number" min="1" max="10080" name="sla_normal_minutes" class="form-control mb-2" style="max-width:240px" value="{{ $sla['sla_normal_minutes'] }}">
                <label>SLA — warning (minutes)</label>
                <input type="number" min="1" max="10080" name="sla_warning_minutes" class="form-control mb-2" style="max-width:240px" value="{{ $sla['sla_warning_minutes'] }}">
                <label>SLA — critical (minutes)</label>
                <input type="number" min="1" max="10080" name="sla_critical_minutes" class="form-control mb-3" style="max-width:240px" value="{{ $sla['sla_critical_minutes'] }}">
                <hr>
                <h5>Beyond Assistant</h5>
                <p class="small text-muted">Environment switch: {{ !empty($assistantEnv) ? 'Allowed' : 'Blocked (WHATSAPP_ASSISTANT_ENABLED)' }}. Provider key: {{ !empty($assistantConfigured) ? 'Configured' : 'Missing' }}. Secrets are never shown here.</p>
                <label class="d-block mb-3">
                    <input type="hidden" name="assistant_enabled" value="0">
                    <input type="checkbox" name="assistant_enabled" value="1" {{ !empty($assistantEnabled) ? 'checked' : '' }}>
                    Enable Beyond Assistant (uncheck to disable all AI replies immediately)
                </label>
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</section>
@endsection
