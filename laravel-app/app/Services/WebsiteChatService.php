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
    const ONBOARD_NEED_PHONE = 'need_phone';
    const ONBOARD_NEED_NAME = 'need_name';
    const ONBOARD_READY = 'ready';

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
            $conversation->load('contact');
            $this->ensureOnboardingPrompt($conversation);

            return $this->sessionPayload($token, $conversation);
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
        $conversation->setRelation('contact', $contact);
        $this->ensureOnboardingPrompt($conversation);

        return array_merge($this->sessionPayload($token, $conversation), ['created' => true]);
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
            'onboarding' => $this->onboardingState($conversation->contact),
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

        $state = $this->onboardingState($conversation->contact);
        $message = $this->storeInbound($conversation, $body);

        try {
            app(WhatsAppLeadService::class)->considerIncoming(
                $conversation->contact->fresh(),
                $conversation,
                $message
            );
        } catch (\Exception $e) {
        }

        if ($state === self::ONBOARD_NEED_PHONE) {
            return $this->handlePhoneStep($conversation, $token, $message, $body);
        }

        if ($state === self::ONBOARD_NEED_NAME) {
            return $this->handleNameStep($conversation, $token, $message, $body);
        }

        $assistantResult = null;
        $conversation = $conversation->fresh(['contact']);
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

        return $this->turnResponse($token, $conversation, $message, $assistantResult);
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

    public function onboardingState($contact)
    {
        if (! $contact) {
            return self::ONBOARD_NEED_PHONE;
        }
        $phone = (string) $contact->normalized_phone;
        if ($phone === '' || $this->isWebsitePlaceholder($phone)) {
            return self::ONBOARD_NEED_PHONE;
        }
        $name = trim((string) $contact->wa_name);
        if ($name === '' || strcasecmp($name, 'Website visitor') === 0) {
            return self::ONBOARD_NEED_NAME;
        }

        return self::ONBOARD_READY;
    }

    protected function isWebsitePlaceholder($phone)
    {
        // Synthetic contact keys are "web…" until the visitor shares a real MSISDN.
        return strpos((string) $phone, 'web') === 0;
    }

    protected function sessionPayload($token, WhatsAppConversation $conversation)
    {
        return [
            'success' => true,
            'token' => $token,
            'conversation_id' => $conversation->id,
            'mode' => $conversation->mode,
            'onboarding' => $this->onboardingState($conversation->contact),
            'assistant_name' => $this->assistantName(),
            'greeting' => $this->greetingText(),
            'continue_whatsapp' => $this->continueWhatsAppEnabled(),
        ];
    }

    protected function ensureOnboardingPrompt(WhatsAppConversation $conversation)
    {
        // Phone capture is handled by the widget UI (country + number form),
        // not by an assistant chat prompt.
    }

    protected function handlePhoneStep(WhatsAppConversation $conversation, $token, WhatsAppMessage $inbound, $body)
    {
        $phone = $this->extractPhone($body);
        if ($phone === null) {
            $this->postAssistant(
                $conversation,
                'Please share a valid WhatsApp number first (e.g. 675321739 or +237 6 75 32 17 39).'
            );

            return $this->turnResponse($token, $conversation, $inbound, null);
        }

        $lookup = app(PeopleDirectoryService::class)->lookupPhoneForForm($phone);
        $isCameroon = strpos($phone, '237') === 0;
        $campayName = trim((string) ($lookup['original_name'] ?? ''));
        $systemName = trim((string) ($lookup['system_name'] ?? ''));

        // Cameroon: greet with CAMPay/anema only — never the system directory name.
        // Non-Cameroon: CAMPay/PawaPay first, then system name.
        $preferred = $isCameroon
            ? $campayName
            : ($campayName !== '' ? $campayName : $systemName);

        $this->bindPhone($conversation, $phone);
        $conversation = $conversation->fresh(['contact']);
        $contact = $conversation->contact;

        if ($preferred !== '') {
            $contact->wa_name = $preferred;
            $contact->save();
            $first = $this->firstName($preferred);
            $reply = $first !== ''
                ? 'Hi '.$first.', how can I help you today?'
                : 'Hi, how can I help you today?';
        } else {
            $contact->wa_name = '';
            $contact->save();
            $reply = "Thanks! I couldn't find a name for that number. What name should I call you?";
        }

        try {
            $this->conversations->syncIdentityLinks($contact->fresh());
        } catch (\Exception $e) {
        }

        $this->postAssistant($conversation->fresh(['contact']), $reply);

        return $this->turnResponse($token, $conversation->fresh(['contact']), $inbound, null, [
            'onboarding' => $this->onboardingState($conversation->fresh()->contact),
            'identified' => [
                'phone' => $phone,
                'cameroon' => $isCameroon,
                'name_source' => $preferred !== ''
                    ? ($campayName !== '' && $preferred === $campayName ? ($lookup['source'] ?? 'campay') : 'system')
                    : null,
                'system_associated' => $systemName !== '',
                'system_name' => $systemName !== '' ? $systemName : null,
                'display_name' => $preferred !== '' ? $preferred : null,
            ],
        ]);
    }

    protected function handleNameStep(WhatsAppConversation $conversation, $token, WhatsAppMessage $inbound, $body)
    {
        // If they pasted a phone again, re-run identify instead of treating it as a name.
        $phone = $this->extractPhone($body);
        if ($phone !== null) {
            return $this->handlePhoneStep($conversation, $token, $inbound, $body);
        }

        $name = $this->extractPersonName($body);
        if ($name === null) {
            $this->postAssistant($conversation, 'What name should I call you?');

            return $this->turnResponse($token, $conversation, $inbound, null);
        }

        $contact = $conversation->contact;
        $contact->wa_name = $name;
        $contact->save();
        try {
            $this->conversations->syncIdentityLinks($contact->fresh());
        } catch (\Exception $e) {
        }

        $first = $this->firstName($name);
        $reply = $first !== ''
            ? 'Hi '.$first.', how can I help you today?'
            : 'Hi, how can I help you today?';
        $this->postAssistant($conversation->fresh(['contact']), $reply);

        return $this->turnResponse($token, $conversation->fresh(['contact']), $inbound, null);
    }

    protected function bindPhone(WhatsAppConversation $conversation, $normalized)
    {
        $contact = $conversation->contact;
        $existing = WhatsAppContact::where('normalized_phone', $normalized)->first();
        if ($existing && (int) $existing->id !== (int) $contact->id) {
            $conversation->contact_id = $existing->id;
            $conversation->save();
            WhatsAppMessage::where('conversation_id', $conversation->id)
                ->where('contact_id', $contact->id)
                ->update(['contact_id' => $existing->id]);
            if ($this->isWebsitePlaceholder((string) $contact->normalized_phone)) {
                try {
                    $contact->delete();
                } catch (\Exception $e) {
                }
            }
            $conversation->setRelation('contact', $existing->fresh());

            return;
        }

        $contact->normalized_phone = $normalized;
        $contact->display_phone = WhatsAppPhone::display($normalized);
        if (trim((string) $contact->wa_name) === 'Website visitor') {
            $contact->wa_name = '';
        }
        $contact->save();
        $conversation->setRelation('contact', $contact->fresh());
    }

    protected function extractPhone($body)
    {
        $raw = trim((string) $body);
        if ($raw === '') {
            return null;
        }

        // Whole message is a phone (with optional spaces / punctuation).
        try {
            $candidate = WhatsAppPhone::normalize($raw);
            if (strlen($candidate) >= 11) {
                return $candidate;
            }
        } catch (\InvalidArgumentException $e) {
        }

        if (! preg_match('/(?:\+|00)?(?:237)?\s*[67]\d[\d\s\-]{7,}/', $raw, $m)) {
            return null;
        }

        try {
            return WhatsAppPhone::normalize($m[0]);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    protected function extractPersonName($body)
    {
        $raw = trim(preg_replace('/\s+/', ' ', (string) $body));
        if ($raw === '') {
            return null;
        }
        // Reject pure numbers / short junk.
        if (preg_match('/^\+?\d[\d\s\-]{6,}$/', $raw)) {
            return null;
        }
        $clean = trim(preg_replace('/[^A-Za-zÀ-ÿ\'\-\.\s]/', '', $raw));
        if (mb_strlen($clean) < 2 || mb_strlen($clean) > 80) {
            return null;
        }
        // "My name is Jean" / "Je m'appelle Marie"
        if (preg_match('/(?:my name is|i am|i\'m|je m[\'’]?appelle|je suis)\s+(.+)$/iu', $clean, $m)) {
            $clean = trim($m[1]);
        }

        return $clean !== '' ? $clean : null;
    }

    protected function firstName($full)
    {
        $skip = ['mr', 'mrs', 'ms', 'dr', 'engr', 'sr', 'prof', 'ltd', 'limited', 'inc', 'plc', 'company', 'enterprise', 'group', 'church', 'school', 'the', 'and'];
        foreach (preg_split('/\s+/', trim((string) $full)) as $word) {
            $clean = preg_replace('/[^A-Za-zÀ-ÿ]/', '', $word);
            if (strlen($clean) < 2 || in_array(strtolower($clean), $skip, true)) {
                continue;
            }

            return ucfirst(strtolower($clean));
        }

        return '';
    }

    protected function ensureWebsiteContact($token)
    {
        // normalized_phone is varchar(32): keep a short synthetic key until identity is collected.
        $synthetic = 'web'.substr((string) $token, 0, 29);
        $contact = WhatsAppContact::firstOrNew(['normalized_phone' => $synthetic]);
        if (! $contact->exists) {
            $contact->display_phone = 'Website visitor';
            $contact->wa_name = 'Website visitor';
        }
        $contact->save();

        return $contact;
    }

    protected function storeInbound(WhatsAppConversation $conversation, $body)
    {
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

        return $message;
    }

    protected function postAssistant(WhatsAppConversation $conversation, $body)
    {
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'provider_message_id' => 'webmsg:'.(string) Str::uuid(),
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_SENT,
            'sender_type' => 'ASSISTANT',
            'sent_at' => now(),
        ]);

        $preview = mb_substr($body, 0, 120);
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->save();

        return $message;
    }

    protected function turnResponse($token, WhatsAppConversation $conversation, WhatsAppMessage $inbound, $assistantResult = null, array $extra = [])
    {
        $poll = $this->messages($token, $inbound->id - 1);
        $outbound = array_values(array_filter($poll['messages'] ?? [], function ($row) use ($inbound) {
            return (int) $row['id'] >= (int) $inbound->id;
        }));

        return array_merge([
            'success' => true,
            'token' => $token,
            'conversation_id' => $conversation->id,
            'mode' => $conversation->fresh()->mode,
            'onboarding' => $this->onboardingState($conversation->fresh()->contact),
            'messages' => $outbound,
            'assistant' => is_array($assistantResult) ? [
                'skipped' => ! empty($assistantResult['skipped']),
                'sent' => ! empty($assistantResult['sent']),
            ] : null,
        ], $extra);
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
