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
            <p class="small text-muted">Subscribe events: messages.received, messages-group.received, messages.update, message-receipt.update, call.</p>
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
                <p class="small text-muted">Environment: {{ !empty($assistantEnv) ? 'Allowed' : 'Blocked' }}. OpenAI: {{ !empty($assistantConfigured) ? 'Configured' : 'Not Configured' }}. Secrets are never shown here.</p>
                <label class="d-block mb-3">
                    <input type="hidden" name="assistant_enabled" value="0">
                    <input type="checkbox" name="assistant_enabled" value="1" {{ !empty($assistantEnabled) ? 'checked' : '' }}>
                    Enable Beyond Assistant (uncheck to disable all AI replies immediately)
                </label>
                <label class="d-block mb-2">
                    <input type="hidden" name="ai_first" value="0">
                    <input type="checkbox" name="ai_first" value="1" {{ !empty($aiFirst) ? 'checked' : '' }}>
                    AI-first for new conversations only
                </label>
                <p class="small text-muted">Saving this does not change conversations that already exist.</p>
                <label class="d-block mb-2">
                    <input type="hidden" name="manual_reply_takes_over" value="0">
                    <input type="checkbox" name="manual_reply_takes_over" value="1" {{ !empty($manualTakeover) ? 'checked' : '' }}>
                    A manual staff reply takes the conversation over from AI
                </label>
                <label class="d-block mb-2">
                    <input type="hidden" name="assistant_greet_by_name" value="0">
                    <input type="checkbox" name="assistant_greet_by_name" value="1" {{ !empty($greetByName) ? 'checked' : '' }}>
                    Greet known contacts by name
                </label>
                <label class="d-block mb-3">
                    <input type="hidden" name="assistant_collect_name" value="0">
                    <input type="checkbox" name="assistant_collect_name" value="1" {{ !empty($collectName) ? 'checked' : '' }}>
                    Ask an unknown contact for their name once
                </label>
                <label>Default handover agent</label>
                <select name="default_handover_user_id" class="form-control mb-3" style="max-width:320px">
                    <option value="0">None</option>
                    @foreach($staff as $u)
                        <option value="{{ $u->id }}" {{ (int) $handoverUserId === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
                <label>History window (messages)</label>
                <input type="number" min="2" max="20" name="assistant_history_limit" class="form-control mb-2" style="max-width:240px" value="{{ $historyLimit }}">
                <label>Clarification limit</label>
                <input type="number" min="1" max="8" name="assistant_max_clarifications" class="form-control mb-3" style="max-width:240px" value="{{ $clarificationLimit }}">
                <hr>
                <h5>Website Assistant (Mbole AI)</h5>
                <p class="small text-muted">Website chats: {{ $websiteMetrics['conversations'] ?? 0 }} · Website leads: {{ $websiteMetrics['leads'] ?? 0 }}</p>
                <label class="d-block mb-2">
                    <input type="hidden" name="website_ai_enabled" value="0">
                    <input type="checkbox" name="website_ai_enabled" value="1" {{ !empty($websiteAiEnabled) ? 'checked' : '' }}>
                    Enable website assistant widget
                </label>
                <label class="d-block mb-2">
                    <input type="hidden" name="website_ai_auto_greeting" value="0">
                    <input type="checkbox" name="website_ai_auto_greeting" value="1" {{ !empty($websiteAutoGreeting) ? 'checked' : '' }}>
                    Show greeting bubble on first visit
                </label>
                <label class="d-block mb-2">
                    <input type="hidden" name="website_ai_handover_enabled" value="0">
                    <input type="checkbox" name="website_ai_handover_enabled" value="1" {{ !empty($websiteHandover) ? 'checked' : '' }}>
                    Allow handover to human from website chats
                </label>
                <label class="d-block mb-3">
                    <input type="hidden" name="website_ai_continue_whatsapp" value="0">
                    <input type="checkbox" name="website_ai_continue_whatsapp" value="1" {{ !empty($websiteContinueWa) ? 'checked' : '' }}>
                    Show “Continue on WhatsApp” link in the widget
                </label>
                <label>Widget display name</label>
                <input type="text" name="website_ai_name" class="form-control mb-2" style="max-width:320px" value="{{ $websiteAiName ?? 'Mbole AI' }}">
                <label>Greeting delay (ms)</label>
                <input type="number" min="0" max="10000" name="website_ai_greeting_delay_ms" class="form-control mb-3" style="max-width:240px" value="{{ $websiteGreetingDelay ?? 600 }}">
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
            <hr>
            <h5>Switch eligible conversations to AI</h5>
            <p class="small">Eligible now: {{ $switchPreview['eligible'] }}. Excluded — assigned {{ $switchPreview['excluded']['assigned'] }}, paused {{ $switchPreview['excluded']['paused'] }}, closed {{ $switchPreview['excluded']['closed'] }}, verification {{ $switchPreview['excluded']['verification'] }}, open attendance {{ $switchPreview['excluded']['attendance'] }}.</p>
            <form method="post" action="{{ route('whatsapp.settings.switch_ai') }}">
                @csrf
                <label class="d-block mb-2">
                    <input type="checkbox" name="confirm" value="1"> I confirm this switch for eligible conversations only
                </label>
                <button class="btn btn-outline-primary" type="submit">Switch eligible conversations to AI</button>
            </form>
        </div>
    </div>
</section>
@endsection
