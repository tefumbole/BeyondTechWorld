<?php

namespace App\Services;

use App\Services\Assistant\AssistantRuntimeSettings;
use App\Services\Assistant\BeyondAssistantService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppLeadService;
use App\Support\WhatsAppPhone;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebsiteChatService
{
    protected $conversations;
    protected $assistant;

    public function __construct(
        WhatsAppConversationService $conversations,
        BeyondAssistantService $assistant
    ) {
        $this->conversations = $conversations;
        $this->assistant = $assistant;
    }

    public function enabled()
    {
        $saved = WhatsAppSetting::getValue('website_ai_enabled', '1');

        return $saved === '1' || $saved === 'true';
    }

    public function openOrResume($token = null, $pageContext = null)
    {
        if (! $this->enabled()) {
            return ['success' => false, 'error' => 'Website assistant is disabled.', 'code' => 503];
        }

        $token = $this->normalizeToken($token);
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }

        $conversation = null;
        if (Schema::hasColumn('whatsapp_conversations', 'session_token')) {
            $conversation = WhatsAppConversation::where('session_token', $token)
                ->where('channel', WhatsAppConversation::CHANNEL_WEBSITE)
                ->first();
        }

        if ($conversation) {
            if ($pageContext && Schema::hasColumn('whatsapp_conversations', 'page_context')) {
                $conversation->page_context = mb_substr((string) $pageContext, 0, 255);
                $conversation->save();
            }

            return [
                'success' => true,
                'token' => $token,
                'conversation_id' => $conversation->id,
                'mode' => $conversation->mode,
                'assistant_name' => $this->assistantName(),
                'greeting' => $this->greetingText(),
                'continue_whatsapp' => $this->continueWhatsAppEnabled(),
            ];
        }

        $contact = $this->ensureWebsiteContact($token);
        $conversation = WhatsAppConversation::create(array_filter([
            'contact_id' => $contact->id,
            'mode' => WhatsAppConversation::MODE_AI,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'channel' => WhatsAppConversation::CHANNEL_WEBSITE,
            'session_token' => $token,
            'page_context' => $pageContext ? mb_substr((string) $pageContext, 0, 255) : null,
            'unread_count' => 0,
            'last_activity_at' => now(),
        ], function ($v) {
            return $v !== null;
        }));

        return [
            'success' => true,
            'token' => $token,
            'conversation_id' => $conversation->id,
            'mode' => $conversation->mode,
            'assistant_name' => $this->assistantName(),
            'greeting' => $this->greetingText(),
            'continue_whatsapp' => $this->continueWhatsAppEnabled(),
            'created' => true,
        ];
    }

    public function messages($token, $afterId = 0)
    {
        $conversation = $this->findByToken($token);
        if (! $conversation) {
            return ['success' => false, 'error' => 'Session not found.', 'code' => 404];
        }

        $query = WhatsAppMessage::where('conversation_id', $conversation->id)->orderBy('id');
        if ((int) $afterId > 0) {
            $query->where('id', '>', (int) $afterId);
        }

        $rows = $query->limit(100)->get()->map(function (WhatsAppMessage $m) {
            return $this->serializeMessage($m);
        })->values()->all();

        return [
            'success' => true,
            'conversation_id' => $conversation->id,
            'mode' => $conversation->mode,
            'messages' => $rows,
            'cursor' => count($rows) ? (int) end($rows)['id'] : (int) $afterId,
        ];
    }

    public function visitorMessage($token, $body, $pageContext = null)
    {
        if (! $this->enabled()) {
            return ['success' => false, 'error' => 'Website assistant is disabled.', 'code' => 503];
        }

        $body = trim((string) $body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is empty.', 'code' => 422];
        }
        if (mb_strlen($body) > 2000) {
            return ['success' => false, 'error' => 'Message is too long.', 'code' => 422];
        }

        $opened = $this->openOrResume($token, $pageContext);
        if (empty($opened['success'])) {
            return $opened;
        }
        $token = $opened['token'];
        $conversation = WhatsAppConversation::with('contact')->find($opened['conversation_id']);
        if (! $conversation || ! $conversation->contact) {
            return ['success' => false, 'error' => 'Session not found.', 'code' => 404];
        }

        $this->maybeCapturePhone($conversation, $body);

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => WhatsAppMessage::DIR_IN,
            'type' => 'TEXT',
            'provider_message_id' => 'webmsg:'.(string) Str::uuid(),
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_DELIVERED,
            'sender_type' => 'CONTACT',
            'delivered_at' => now(),
        ]);

        $preview = mb_substr($body, 0, 120);
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_incoming_at = now();
        $conversation->unread_count = (int) $conversation->unread_count + 1;
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_STAFF;
        }
        $conversation->save();

        try {
            app(WhatsAppLeadService::class)->considerIncoming(
                $conversation->contact->fresh(),
                $conversation,
                $message
            );
        } catch (\Exception $e) {
        }

        $assistantResult = null;
        $conversation = $conversation->fresh();
        if ($conversation->mode === WhatsAppConversation::MODE_AI) {
            try {
                $assistantResult = $this->assistant->handleIncoming($message->fresh(['conversation.contact']));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[website-chat] assistant turn failed', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $poll = $this->messages($token, $message->id - 1);
        $outbound = array_values(array_filter($poll['messages'] ?? [], function ($row) use ($message) {
            return (int) $row['id'] >= (int) $message->id;
        }));

        return [
            'success' => true,
            'token' => $token,
            'conversation_id' => $conversation->id,
            'mode' => $conversation->fresh()->mode,
            'messages' => $outbound,
            'assistant' => is_array($assistantResult) ? [
                'skipped' => ! empty($assistantResult['skipped']),
                'sent' => ! empty($assistantResult['sent']),
            ] : null,
        ];
    }

    public function minimize($token)
    {
        $conversation = $this->findByToken($token);
        if ($conversation) {
            Cache::put('website_chat_minimized:'.$token, 1, now()->addDays(30));
        }

        return ['success' => true];
    }

    public function findByToken($token)
    {
        $token = $this->normalizeToken($token);
        if ($token === '' || ! Schema::hasColumn('whatsapp_conversations', 'session_token')) {
            return null;
        }

        return WhatsAppConversation::with('contact')
            ->where('session_token', $token)
            ->where('channel', WhatsAppConversation::CHANNEL_WEBSITE)
            ->first();
    }

    public function assistantName()
    {
        $name = trim((string) WhatsAppSetting::getValue('website_ai_name', ''));

        return $name !== '' ? $name : 'Mbole AI';
    }

    public function greetingText()
    {
        if (! AssistantRuntimeSettings::flag('website_ai_auto_greeting', true)) {
            return null;
        }

        return 'Hello! How can I help?';
    }

    public function continueWhatsAppEnabled()
    {
        return AssistantRuntimeSettings::flag('website_ai_continue_whatsapp', true);
    }

    public function whatsAppDeepLink($token)
    {
        $phone = preg_replace('/\D/', '', (string) config('services.whatsapp.public_number', config('services.whatsapp.business_number', '237650000000')));
        $ref = 'BTW-WEB-'.substr((string) $token, 0, 8);
        $text = rawurlencode('Hi BeyondTechWorld — continuing my website chat (ref '.$ref.').');

        return 'https://wa.me/'.$phone.'?text='.$text;
    }

    protected function ensureWebsiteContact($token)
    {
        $synthetic = 'web:'.$token;
        $contact = WhatsAppContact::firstOrNew(['normalized_phone' => $synthetic]);
        if (! $contact->exists) {
            $contact->display_phone = 'Website visitor';
            $contact->wa_name = 'Website visitor';
        }
        $contact->save();

        return $contact;
    }

    protected function maybeCapturePhone(WhatsAppConversation $conversation, $body)
    {
        $contact = $conversation->contact;
        if (! $contact || strpos((string) $contact->normalized_phone, 'web:') !== 0) {
            return;
        }

        if (! preg_match('/(?:\+|00)?(?:237)?\s*[67]\d[\d\s\-]{7,}/', $body, $m)) {
            return;
        }

        try {
            $normalized = WhatsAppPhone::normalize($m[0]);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $existing = WhatsAppContact::where('normalized_phone', $normalized)->first();
        if ($existing && (int) $existing->id !== (int) $contact->id) {
            $conversation->contact_id = $existing->id;
            $conversation->save();
            WhatsAppMessage::where('conversation_id', $conversation->id)
                ->where('contact_id', $contact->id)
                ->update(['contact_id' => $existing->id]);
            if (trim((string) $existing->wa_name) === '' || $existing->wa_name === 'Website visitor') {
                if ($contact->wa_name && $contact->wa_name !== 'Website visitor') {
                    $existing->wa_name = $contact->wa_name;
                    $existing->save();
                }
            }
            $conversation->setRelation('contact', $existing->fresh());

            return;
        }

        $contact->normalized_phone = $normalized;
        $contact->display_phone = WhatsAppPhone::display($normalized);
        $contact->save();
        try {
            $this->conversations->syncIdentityLinks($contact);
        } catch (\Exception $e) {
        }
    }

    protected function serializeMessage(WhatsAppMessage $m)
    {
        $role = 'visitor';
        if ($m->direction === WhatsAppMessage::DIR_OUT) {
            $role = $m->sender_type === 'STAFF' ? 'staff' : 'assistant';
        }

        return [
            'id' => (int) $m->id,
            'role' => $role,
            'body' => (string) $m->body,
            'created_at' => optional($m->created_at)->toIso8601String(),
            'sender_type' => $m->sender_type,
        ];
    }

    protected function normalizeToken($token)
    {
        $token = preg_replace('/[^a-zA-Z0-9]/', '', (string) $token);

        return substr($token, 0, 64);
    }
}
