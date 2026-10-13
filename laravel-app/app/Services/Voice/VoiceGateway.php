<?php

namespace App\Services\Voice;

use App\Contracts\Voice\VoiceProvider;
use App\Support\WhatsAppPhone;
use App\Voice\VoiceCall;
use App\Voice\VoiceCallback;
use App\Voice\VoiceConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound voice channel for the existing MAI tool registry.
 * Tenant comes from the called number's VoiceConnection, never from the caller.
 * This phase answers only through the stub provider.
 */
class VoiceGateway
{
    protected $provider;
    protected $script;

    public function __construct(VoiceProvider $provider, VoiceScriptedTurn $script)
    {
        $this->provider = $provider;
        $this->script = $script;
    }

    public function incoming($calledNumber, $callerPhone, $providerCallId = null, $providerName = 'stub')
    {
        $called = $this->normalize($calledNumber);
        $caller = $this->normalize($callerPhone);
        $providerName = $providerName ? (string) $providerName : 'stub';

        if ($providerCallId) {
            $existing = VoiceCall::where('provider_call_id', (string) $providerCallId)->orderBy('id')->first();
            if ($existing) {
                return [
                    'ok' => $existing->status === 'ANSWERED',
                    'reason' => 'duplicate',
                    'speech' => '',
                    'call' => $existing,
                    'duplicate' => true,
                ];
            }
        }

        if (! MaiVoiceSwitch::open()) {
            return $this->reject(null, $called, $caller, $providerCallId, 'voice_off', $providerName);
        }

        $connection = VoiceConnection::where('called_number', $called)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $connection) {
            return $this->reject(null, $called, $caller, $providerCallId, 'unknown_number', $providerName);
        }

        if (! $connection->ai_enabled) {
            return $this->reject($connection, $called, $caller, $providerCallId, 'ai_disabled', $providerName);
        }

        if (strtoupper((string) $connection->tenant_type) !== 'INTERNAL' || $connection->cloud_tenant_id === null) {
            return $this->reject($connection, $called, $caller, $providerCallId, 'customer_voice_disabled', $providerName);
        }

        $openCalls = VoiceCall::where('status', 'ANSWERED')->count();
        if ($openCalls >= (int) config('voice.max_concurrent', 2)) {
            return $this->reject($connection, $called, $caller, $providerCallId, 'concurrency', $providerName);
        }

        $recent = VoiceCall::where('caller_phone', $caller)
            ->where('voice_connection_id', $connection->id)
            ->where('created_at', '>=', $this->now()->copy()->subHour())
            ->count();
        if ($caller !== '' && $recent >= (int) config('voice.max_calls_per_caller_per_hour', 8)) {
            return $this->reject($connection, $called, $caller, $providerCallId, 'rate_limit', $providerName);
        }

        $known = $this->knownName($connection, $caller);
        $now = $this->now();
        $call = new VoiceCall();
        $call->cloud_tenant_id = $connection->cloud_tenant_id;
        $call->voice_connection_id = $connection->id;
        $call->provider = $providerName;
        $call->provider_call_id = $providerCallId;
        $call->caller_phone = $caller;
        $call->called_number = $called;
        $call->status = 'ANSWERED';
        $call->status_reason = 'answered';
        $call->handling = 'MAI';
        $call->transfer_status = 'NONE';
        $call->recording = false;
        $call->identity_verified = false;
        $call->caller_name = $known;
        $call->started_at = $now;
        $call->answered_at = $now;
        $call->state = json_encode(['tools' => []]);
        $call->transcript = json_encode([]);
        $call->save();

        $call->conversation_id = $this->openConversation($connection, $call, $known);
        $speech = $this->greeting($connection, $known);
        $this->append($call, 'assistant', $speech);
        $call->save();

        return ['ok' => true, 'reason' => 'answered', 'speech' => $speech, 'call' => $call];
    }

    public function hear(VoiceCall $call, $text)
    {
        $started = microtime(true);
        $call = $call->fresh();
        if ($call->status !== 'ANSWERED') {
            return $this->heard($call, '', false, $started);
        }
        if ($call->handling === 'HUMAN') {
            $this->append($call, 'caller', (string) $text);
            $call->save();

            return $this->heard($call, '', false, $started);
        }

        if ($this->overDuration($call)) {
            $connection = $this->connection($call);
            $speech = $this->line('duration', $connection);
            $this->append($call, 'assistant', $speech);
            $this->finish($call, 'duration');

            return $this->heard($call, $speech, true, $started);
        }

        $trimmed = trim((string) $text);
        if ($trimmed === '') {
            $call->silent_turns = (int) $call->silent_turns + 1;
            $connection = $this->connection($call);
            $speech = $this->line('silence', $connection);
            $this->append($call, 'assistant', $speech);
            if ((int) $call->silent_turns >= (int) config('voice.max_silent_turns', 3)) {
                $this->finish($call, 'silence');
            } else {
                $call->save();
            }

            return $this->heard($call, $speech, true, $started);
        }

        $call->silent_turns = 0;
        $this->append($call, 'caller', $trimmed);
        $call->save();

        if (config('voice.simulate_ai_failure') || config('voice.engine') === 'conversational') {
            return $this->aiFallback($call, $started);
        }

        try {
            $planned = $this->script->reply($call->fresh(), $trimmed);
        } catch (\Throwable $e) {
            return $this->aiFallback($call, $started);
        }

        $call = $call->fresh();
        $speech = $this->perform($call, $planned, $trimmed);

        return $this->heard($call->fresh(), $speech, $speech !== '', $started);
    }

    protected function perform(VoiceCall $call, array $planned, $text)
    {
        $action = isset($planned['action']) ? $planned['action'] : null;
        if ($action === 'transfer') {
            return $this->transfer($call, $text);
        }
        if ($action === 'callback') {
            return $this->callback($call, isset($planned['who']) ? $planned['who'] : 'the team', $text);
        }
        if ($action === 'message') {
            return $this->takeMessage($call, isset($planned['message']) ? $planned['message'] : $text);
        }
        if ($action === 'hangup') {
            return $this->endCall($call, 'caller_done');
        }
        if ($action === 'payment_refused') {
            $this->append($call, 'action', 'payment_claim_refused');
            $speech = $this->line('payment_refused', $this->connection($call));
            $this->append($call, 'assistant', $speech);
            $call->save();

            return $speech;
        }

        $speech = $this->render(isset($planned['speech']) ? $planned['speech'] : 'clarify', $call);
        if ($speech !== '') {
            $this->append($call, 'assistant', $speech);
        }
        $call->save();

        return $speech;
    }

    protected function transfer(VoiceCall $call, $text)
    {
        $connection = $this->connection($call);
        $target = $connection ? (string) $connection->transfer_number : '';

        try {
            $result = $this->provider->transfer($call, $target);
            $ok = ! empty($result['ok']);
        } catch (\Throwable $e) {
            $ok = false;
            $call->status_reason = 'provider_down';
        }

        if ($ok) {
            $call->handling = 'HUMAN';
            $call->transfer_status = 'SUCCESS';
            $this->markConversationHuman($call);
            $speech = $this->line('transfer_ok', $connection);
            $this->append($call, 'action', 'transfer_call');
            $this->append($call, 'assistant', $speech);
            $call->save();

            return $speech;
        }

        $call->transfer_status = 'FAILED';
        $call->handling = 'MAI';
        $state = $call->stateArray();
        $state['awaiting_message'] = true;
        $call->state = json_encode($state);
        $speech = $this->line('transfer_fail', $connection);
        $this->append($call, 'action', 'transfer_call_failed');
        $this->append($call, 'assistant', $speech);
        $call->save();

        return $speech;
    }

    protected function callback(VoiceCall $call, $who, $text)
    {
        $row = new VoiceCallback();
        $row->cloud_tenant_id = $call->cloud_tenant_id;
        $row->voice_call_id = $call->id;
        $row->caller_phone = $call->caller_phone;
        $row->caller_name = $call->caller_name;
        $row->requested_for = $who;
        $row->reason = 'callback';
        $row->message = $text;
        $row->requested_at = $this->now();
        $row->status = 'OPEN';
        $row->save();

        $connection = $this->connection($call);
        $speech = str_replace(':who', $who, $this->line('callback', $connection));
        $this->append($call, 'action', 'request_callback');
        $this->append($call, 'assistant', $speech);
        $call->save();

        return $speech;
    }

    protected function takeMessage(VoiceCall $call, $message)
    {
        $row = new VoiceCallback();
        $row->cloud_tenant_id = $call->cloud_tenant_id;
        $row->voice_call_id = $call->id;
        $row->caller_phone = $call->caller_phone;
        $row->caller_name = $call->caller_name;
        $row->requested_for = 'the team';
        $row->reason = 'message';
        $row->message = $message;
        $row->requested_at = $this->now();
        $row->status = 'OPEN';
        $row->save();

        $state = $call->stateArray();
        $state['awaiting_message'] = false;
        $call->state = json_encode($state);
        $speech = $this->line('message_saved', $this->connection($call));
        $this->append($call, 'action', 'take_message');
        $this->append($call, 'assistant', $speech);
        $call->save();

        return $speech;
    }

    protected function endCall(VoiceCall $call, $reason)
    {
        $speech = $this->line('goodbye', $this->connection($call));
        $this->append($call, 'action', 'end_call');
        $this->append($call, 'assistant', $speech);
        $this->finish($call, $reason);

        return $speech;
    }

    protected function aiFallback(VoiceCall $call, $started)
    {
        $connection = $this->connection($call);
        $speech = $this->line('ai_down', $connection);
        $this->append($call, 'assistant', $speech);
        $call->status_reason = 'ai_failure';
        $call->save();
        if ($connection && trim((string) $connection->transfer_number) !== '') {
            try {
                $result = $this->provider->transfer($call, $connection->transfer_number);
                if (! empty($result['ok'])) {
                    $call->handling = 'HUMAN';
                    $call->transfer_status = 'SUCCESS';
                    $this->markConversationHuman($call);
                    $call->save();
                }
            } catch (\Throwable $e) {
                $call->transfer_status = 'FAILED';
                $call->status_reason = 'provider_down';
                $state = $call->stateArray();
                $state['awaiting_message'] = true;
                $call->state = json_encode($state);
                $call->save();
            }
        } else {
            $state = $call->stateArray();
            $state['awaiting_message'] = true;
            $call->state = json_encode($state);
            $call->save();
        }

        return $this->heard($call->fresh(), $speech, true, $started);
    }

    protected function finish(VoiceCall $call, $reason)
    {
        try {
            $this->provider->hangup($call, $reason);
        } catch (\Throwable $e) {
            $call->status_reason = 'provider_down';
        }
        $call->status = 'ENDED';
        $call->status_reason = $reason;
        $call->ended_at = $this->now();
        if ($call->answered_at) {
            $call->duration_seconds = max(0, $call->answered_at->diffInSeconds($call->ended_at));
        }
        $call->recording = false;
        $call->save();
    }

    protected function reject($connection, $called, $caller, $providerCallId, $reason, $providerName = 'stub')
    {
        $call = new VoiceCall();
        $call->cloud_tenant_id = $connection ? $connection->cloud_tenant_id : null;
        $call->voice_connection_id = $connection ? $connection->id : null;
        $call->provider = $providerName ? (string) $providerName : 'stub';
        $call->provider_call_id = $providerCallId;
        $call->caller_phone = $caller;
        $call->called_number = $called;
        $call->status = 'REJECTED';
        $call->status_reason = $reason;
        $call->handling = 'NONE';
        $call->transfer_status = 'NONE';
        $call->recording = false;
        $call->identity_verified = false;
        $call->save();

        return ['ok' => false, 'reason' => $reason, 'speech' => '', 'call' => $call];
    }

    protected function greeting(VoiceConnection $connection, $known)
    {
        $lang = $this->lang($connection);
        $company = $connection->company_name ?: 'BeyondTechWorld';
        $assistant = $connection->assistant_name ?: config('voice.default_assistant', 'MAI');
        if (! $this->withinHours($connection)) {
            $template = $this->pack('after_hours', $lang);
        } elseif (is_string($connection->greeting) && trim($connection->greeting) !== '') {
            $template = $connection->greeting;
        } else {
            $template = $this->pack('greetings', $lang);
        }
        $speech = $this->fill($template, $company, $assistant, $known);
        if ($known) {
            $speech .= ' '.$this->fill($this->pack('welcome_back', $lang), $company, $assistant, $known);
        } else {
            $speech .= ' '.$this->fill($this->pack('ask_name', $lang), $company, $assistant, null);
        }

        return trim($speech);
    }

    protected function render($code, VoiceCall $call)
    {
        $connection = $this->connection($call);
        if (strpos((string) $code, 'product:') === 0) {
            return 'Yes. '.substr($code, 8).' is in the catalogue.';
        }
        if (strpos((string) $code, 'price:') === 0) {
            return 'The catalogue price is '.substr($code, 6).'.';
        }
        if (strpos((string) $code, 'fact:') === 0) {
            return substr($code, 5);
        }
        if (strpos((string) $code, 'named:') === 0) {
            return 'Thank you, '.substr($code, 6).'. How may I help you?';
        }
        if (strpos((string) $code, 'ask_date:') === 0) {
            return 'Certainly. What date is the '.substr($code, 9).'?';
        }
        if ($code === 'ask_venue') {
            return 'And where will the event take place?';
        }
        if (strpos((string) $code, 'event:') === 0) {
            $parts = explode('|', substr($code, 6));
            $type = isset($parts[0]) ? $parts[0] : 'event';
            $date = isset($parts[1]) ? $parts[1] : '';
            $venue = isset($parts[2]) ? $parts[2] : '';

            return 'Thank you. I have the '.$type.' on '.$date.' at '.$venue.'.';
        }
        if ($code === 'available_yes') {
            return 'The catalogue shows that as available for the date we have.';
        }
        if ($code === 'available_no') {
            return 'The catalogue shows that as not available for that date.';
        }
        if ($code === 'quote_draft') {
            return 'I have started a draft quotation. It still needs the usual approval. It is not confirmed.';
        }
        if ($code === 'not_authorized' || $code === 'no_price' || $code === 'no_product' || $code === 'clarify') {
            return $this->line($code, $connection);
        }

        return $this->line('clarify', $connection);
    }

    protected function line($key, $connection)
    {
        $lang = $connection ? $this->lang($connection) : (string) config('voice.default_language', 'en');
        $lines = config('voice.lines.'.$lang);
        if (! is_array($lines) || ! isset($lines[$key])) {
            $lines = config('voice.lines.en');
        }
        $template = is_array($lines) && isset($lines[$key]) ? $lines[$key] : '';
        $company = $connection && $connection->company_name ? $connection->company_name : 'BeyondTechWorld';
        $assistant = $connection && $connection->assistant_name ? $connection->assistant_name : 'MAI';

        return $this->fill($template, $company, $assistant, null);
    }

    protected function fill($template, $company, $assistant, $name)
    {
        return strtr((string) $template, [
            ':company' => (string) $company,
            ':assistant' => (string) $assistant,
            ':name' => (string) $name,
        ]);
    }

    protected function pack($key, $lang)
    {
        $value = config('voice.'.$key.'.'.$lang);
        if (! is_string($value) || $value === '') {
            $value = config('voice.'.$key.'.en');
        }

        return (string) $value;
    }

    protected function lang(VoiceConnection $connection)
    {
        $lang = strtolower((string) $connection->language);
        if ($lang === '') {
            $lang = (string) config('voice.default_language', 'en');
        }

        return $lang;
    }

    protected function withinHours(VoiceConnection $connection)
    {
        $hours = $connection->hours();
        if (! $hours) {
            return true;
        }
        $now = $this->now();
        $days = isset($hours['days']) && is_array($hours['days']) ? $hours['days'] : [1, 2, 3, 4, 5];
        if (! in_array((int) $now->format('N'), array_map('intval', $days), true)) {
            return false;
        }
        $open = isset($hours['open']) ? $hours['open'] : '08:00';
        $close = isset($hours['close']) ? $hours['close'] : '18:00';
        $stamp = $now->format('H:i');

        return $stamp >= $open && $stamp < $close;
    }

    protected function knownName(VoiceConnection $connection, $caller)
    {
        if ($caller === '' || ! Schema::hasTable('whatsapp_contacts') || ! Schema::hasColumn('whatsapp_contacts', 'normalized_phone')) {
            return null;
        }
        $query = DB::table('whatsapp_contacts')->where('normalized_phone', $caller);
        if (Schema::hasColumn('whatsapp_contacts', 'cloud_tenant_id')) {
            $query->where('cloud_tenant_id', $connection->cloud_tenant_id);
        }
        $rows = $query->get();
        if (count($rows) !== 1) {
            return null;
        }
        $row = $rows[0];
        if (Schema::hasColumn('whatsapp_contacts', 'call_name') && ! empty($row->call_name)) {
            return (string) $row->call_name;
        }
        if (! empty($row->wa_name)) {
            return (string) $row->wa_name;
        }

        return null;
    }

    protected function openConversation(VoiceConnection $connection, VoiceCall $call, $known)
    {
        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasTable('whatsapp_contacts')) {
            return null;
        }
        $contact = [
            'normalized_phone' => $call->caller_phone ?: ('voice-'.$call->id),
            'display_phone' => $call->caller_phone,
            'wa_name' => $known,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];
        if (Schema::hasColumn('whatsapp_contacts', 'cloud_tenant_id')) {
            $contact['cloud_tenant_id'] = $connection->cloud_tenant_id;
        }
        try {
            $contactId = DB::table('whatsapp_contacts')->insertGetId($contact);
        } catch (\Throwable $e) {
            $existing = DB::table('whatsapp_contacts')->where('normalized_phone', $contact['normalized_phone'])->first();
            if (! $existing) {
                return null;
            }
            if (Schema::hasColumn('whatsapp_contacts', 'cloud_tenant_id') && (int) $existing->cloud_tenant_id !== (int) $connection->cloud_tenant_id) {
                return null;
            }
            $contactId = $existing->id;
        }

        $row = [
            'contact_id' => $contactId,
            'mode' => 'AI',
            'status' => 'OPEN',
            'first_message' => 'voice',
            'last_message' => 'voice',
            'last_activity_at' => $this->now(),
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ];
        if (Schema::hasColumn('whatsapp_conversations', 'channel')) {
            $row['channel'] = 'voice';
        }
        if (Schema::hasColumn('whatsapp_conversations', 'cloud_tenant_id')) {
            $row['cloud_tenant_id'] = $connection->cloud_tenant_id;
        }
        if (Schema::hasColumn('whatsapp_conversations', 'unread_count')) {
            $row['unread_count'] = 0;
        }

        try {
            return DB::table('whatsapp_conversations')->insertGetId($row);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function markConversationHuman(VoiceCall $call)
    {
        if (! $call->conversation_id || ! Schema::hasTable('whatsapp_conversations')) {
            return;
        }
        DB::table('whatsapp_conversations')->where('id', $call->conversation_id)->update([
            'mode' => 'HUMAN',
            'updated_at' => $this->now(),
        ]);
    }

    protected function append(VoiceCall $call, $role, $text)
    {
        if (! config('voice.transcribe', true)) {
            return;
        }
        $rows = $call->transcriptArray();
        $rows[] = [
            'role' => $role,
            'text' => (string) $text,
            'at' => $this->now()->toDateTimeString(),
        ];
        $call->transcript = json_encode($rows);
    }

    protected function overDuration(VoiceCall $call)
    {
        if (! $call->answered_at) {
            return false;
        }
        $limit = (int) config('voice.max_duration_seconds', 600);

        return $call->answered_at->diffInSeconds($this->now()) >= $limit;
    }

    protected function connection(VoiceCall $call)
    {
        if (! $call->voice_connection_id) {
            return null;
        }

        return VoiceConnection::find($call->voice_connection_id);
    }

    protected function heard(VoiceCall $call, $speech, $spoke, $started)
    {
        $ms = (int) round((microtime(true) - $started) * 1000);
        $call->last_latency_ms = $ms;
        $call->recording = false;
        $call->save();

        return [
            'ok' => true,
            'speech' => $speech,
            'spoke' => $spoke,
            'latency_ms' => $ms,
            'call' => $call->fresh(),
        ];
    }

    protected function normalize($number)
    {
        return WhatsAppPhone::sanitizeForStorage($number);
    }

    protected function now()
    {
        $clock = config('voice.clock');
        if (is_callable($clock)) {
            $value = call_user_func($clock);
            if ($value instanceof Carbon) {
                return $value->copy();
            }
        }

        return Carbon::now();
    }
}
