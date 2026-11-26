<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\Assistant\AssistantRuntimeSettings;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppContactLink;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;

class WhatsAppConversationService
{
    protected $identity;
    protected $provider;

    public function __construct(WhatsAppIdentityService $identity, WhatsAppProviderInterface $provider)
    {
        $this->identity = $identity;
        $this->provider = $provider;
    }

    public function defaultMode()
    {
        $saved = WhatsAppSetting::getValue('default_conversation_mode');
        $mode = strtoupper((string) ($saved ?: config('services.whatsapp.default_conversation_mode', 'HUMAN')));
        if (! in_array($mode, [
            WhatsAppConversation::MODE_AI,
            WhatsAppConversation::MODE_HUMAN,
            WhatsAppConversation::MODE_PAUSED,
            WhatsAppConversation::MODE_CLOSED,
        ], true)) {
            $mode = WhatsAppConversation::MODE_HUMAN;
        }
        if (AssistantRuntimeSettings::aiFirst()) {
            try {
                if (app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled()) {
                    return WhatsAppConversation::MODE_AI;
                }
            } catch (\Exception $e) {
                return WhatsAppConversation::MODE_HUMAN;
            }

            return WhatsAppConversation::MODE_HUMAN;
        }

        return $mode;
    }

    public function findOrCreateContact($phone, $waName = null)
    {
        $normalized = $this->identity->normalize($phone);
        if ($normalized === '') {
            return null;
        }
        app(\App\Services\WhatsApp\WhatsAppContactLookup::class)->requireTenant();

        $contact = WhatsAppContact::query()->where('normalized_phone', $normalized)->first();
        if (! $contact) {
            $contact = new WhatsAppContact();
            $contact->normalized_phone = $normalized;
        }
        $contact->display_phone = $this->identity->display($phone);
        $book = app(\App\Services\BeyondWasenderService::class)->addressBookName($normalized, false);
        $digits = preg_replace('/\D+/', '', (string) $normalized);
        if ($book !== '') {
            $contact->wa_name = $book;
            \Illuminate\Support\Facades\Cache::put('wa.book.'.$digits, $book, now()->addDays(30));
        } else {
            $saved = \Illuminate\Support\Facades\Cache::get('wa.book.'.$digits);
            if (is_string($saved) && trim($saved) !== '') {
                $contact->wa_name = trim($saved);
            } elseif (trim((string) $contact->wa_name) === '' && $waName && trim((string) $waName) !== '') {
                $contact->wa_name = trim((string) $waName);
            }
        }
        $contact->save();
        $this->syncIdentityLinks($contact);

        return $contact->fresh('links');
    }

    /**
     * Use the name saved on the phone, the same name WhatsApp Web shows.
     */
    public function preferPhoneNames($conversations, $fetch = true)
    {
        $service = app(\App\Services\BeyondWasenderService::class);
        $map = $service->addressBookMap($fetch);
        if (! $map) {
            return;
        }
        foreach ($conversations as $row) {
            $contact = $row && isset($row->contact) ? $row->contact : null;
            if (! $contact) {
                continue;
            }
            $name = $service->addressBookName($contact->normalized_phone, false);
            if ($name === '' || trim((string) $contact->wa_name) === $name) {
                continue;
            }
            $contact->wa_name = $name;
            app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () use ($contact) {
                $contact->save();
            });
            $digits = preg_replace('/\D+/', '', (string) $contact->normalized_phone);
            if ($digits !== '') {
                \Illuminate\Support\Facades\Cache::put('wa.book.'.$digits, $name, now()->addDays(30));
            }
        }
    }

    public function syncIdentityLinks(WhatsAppContact $contact)
    {
        foreach ($this->identity->resolve($contact->normalized_phone) as $match) {
            WhatsAppContactLink::firstOrCreate([
                'contact_id' => $contact->id,
                'linkable_type' => $match['type'],
                'linkable_id' => $match['id'],
                'role' => $match['role'],
            ]);
        }
    }

    public function openConversation(WhatsAppContact $contact)
    {
        $conversation = WhatsAppConversation::where('contact_id', $contact->id)
            ->orderByDesc('id')
            ->first();

        if ($conversation) {
            if (in_array($conversation->status, [WhatsAppConversation::STATUS_CLOSED, WhatsAppConversation::STATUS_RESOLVED], true)) {
                $conversation->status = WhatsAppConversation::STATUS_WAITING_STAFF;
                if ($this->assistantContinuesClosedChat($conversation)) {
                    $conversation->mode = WhatsAppConversation::MODE_AI;
                    $conversation->assigned_user_id = null;
                } elseif ($conversation->mode === WhatsAppConversation::MODE_CLOSED) {
                    $conversation->mode = $this->initialMode();
                }
                $conversation->save();
            }

            return $conversation;
        }

        return WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'mode' => $this->initialMode(),
            'status' => WhatsAppConversation::STATUS_OPEN,
            'unread_count' => 0,
        ]);
    }

    public function recordIncoming(array $parsed)
    {
        $contact = $this->findOrCreateContact($parsed['phone'], $parsed['wa_name'] ?? null);
        if (! $contact) {
            return null;
        }
        app(WhatsAppTyping::class)->forget($contact->normalized_phone);

        $conversation = $this->openConversation($contact);
        $conversation = $this->promoteToAiIfDefault($conversation);
        if (! empty($parsed['message_id'])) {
            $existing = WhatsAppMessage::where('provider_message_id', $parsed['message_id'])->first();
            if ($existing) {
                // A chat update often saves the row first, without the text, the
                // voice-note file, or an assistant turn. Keep the fuller event.
                $this->fillFromEvent($existing, $parsed);
                $this->queueAssistantTurn($existing, false);

                return $existing;
            }
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_IN,
            'type' => $parsed['message_type'] ?? 'UNKNOWN',
            'provider_message_id' => $parsed['message_id'] ?? null,
            'body' => $parsed['body'] ?? null,
            'media_json' => ! empty($parsed['media']) ? json_encode($parsed['media']) : null,
            'status' => WhatsAppMessage::STATUS_DELIVERED,
            'sender_type' => 'CONTACT',
            'delivered_at' => now(),
        ]);
        app(WhatsAppTranslator::class)->decorate($message);
        $message = $message->fresh() ?: $message;
        $preview = $this->preview($message->hubText(), $parsed['message_type'] ?? 'TEXT');

        if ($this->phoneAlreadyRead($conversation, $parsed) || $this->staffIsViewing($conversation)) {
            $message->read_at = $message->read_at ?: now();
            $message->save();
        }
        $this->recountUnread($conversation);
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_incoming_at = now();
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_STAFF;
        }
        $conversation->save();

        try {
            app(WhatsAppLeadService::class)->considerIncoming($contact, $conversation, $message);
        } catch (\Exception $e) {
        }
        $this->queueAssistantTurn($message, false);

        return $message;
    }

    /**
     * Queue one assistant turn. The same WhatsApp message can arrive as a chat
     * update and again as messages.received; only the first copy is answered.
     */
    protected function queueAssistantTurn(WhatsAppMessage $message, $controlOnly)
    {
        $control = app(OwnerCommandService::class)->isControlCommand((string) $message->body);
        if ($controlOnly && ! $control) {
            return;
        }
        if ($message->direction !== WhatsAppMessage::DIR_IN) {
            return;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')) {
            return;
        }
        if (! $this->claimControlCommand($message)) {
            return;
        }
        try {
            \App\Jobs\ProcessAssistantTurn::dispatch($message->id)->onQueue('whatsapp');
        } catch (\Exception $e) {
            Cache::forget($this->controlCommandKey($message));
        }
    }

    protected function fillFromEvent(WhatsAppMessage $message, array $parsed)
    {
        $changed = false;
        $body = isset($parsed['body']) ? trim((string) $parsed['body']) : '';
        if ($body !== '' && trim((string) $message->body) === '') {
            $message->body = $body;
            $changed = true;
        }
        $type = isset($parsed['message_type']) ? strtoupper((string) $parsed['message_type']) : '';
        $current = strtoupper((string) $message->type);
        if ($type !== '' && $type !== 'UNKNOWN' && ($current === '' || $current === 'UNKNOWN' || $current === 'TEXT' && $type === 'AUDIO')) {
            $message->type = $type;
            $changed = true;
        }
        if (! empty($parsed['media']) && is_array($parsed['media']) && empty($message->media()['url']) && empty($message->media()['stored'])) {
            $message->media_json = json_encode(array_merge($message->media(), $parsed['media']));
            $changed = true;
        }
        if ($changed) {
            $message->save();
            app(WhatsAppTranslator::class)->decorate($message->fresh() ?: $message);
        }
    }

    protected function claimControlCommand(WhatsAppMessage $message)
    {
        try {
            return Cache::add($this->controlCommandKey($message), 1, now()->addDay());
        } catch (\Exception $e) {
            return true;
        }
    }

    protected function controlCommandKey(WhatsAppMessage $message)
    {
        return 'whatsapp.control-command.'.$message->id;
    }

    /**
     * The linked phone reports the assistant's own send back as a new outgoing
     * message. Keep the original bubble instead of showing the reply twice.
     */
    protected function absorbPhoneEcho(WhatsAppConversation $conversation, array $parsed)
    {
        $incomingId = isset($parsed['message_id']) ? (string) $parsed['message_id'] : '';
        if ($incomingId !== '') {
            $same = WhatsAppMessage::where('provider_message_id', $incomingId)
                ->where('direction', WhatsAppMessage::DIR_OUT)
                ->first();
            if ($same) {
                return true;
            }
        }
        $body = $this->echoText(isset($parsed['body']) ? $parsed['body'] : '');
        if ($body === '') {
            return false;
        }
        $recent = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_OUT)
            ->whereIn('sender_type', ['ASSISTANT', 'STAFF'])
            ->where('created_at', '>=', now()->subMinutes(3))
            ->orderByDesc('id')
            ->limit(5)
            ->get();
        foreach ($recent as $echo) {
            $candidates = [$this->echoText($echo->body), $this->echoText($echo->hubText())];
            $meta = $echo->translationMeta();
            if (! empty($meta['staff'])) {
                $candidates[] = $this->echoText($meta['staff']);
            }
            if (! in_array($body, $candidates, true)) {
                continue;
            }
            if ($incomingId !== '' && (string) $echo->provider_message_id !== $incomingId && preg_match('/^\d+$/', (string) $echo->provider_message_id)) {
                $media = $echo->media();
                if (empty($media['wasender_id'])) {
                    $media['wasender_id'] = (string) $echo->provider_message_id;
                }
                $media['wa_id'] = $incomingId;
                $echo->media_json = json_encode($media);
                $echo->provider_message_id = $incomingId;
                $echo->save();
            }

            return true;
        }

        return false;
    }

    protected function echoText($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    /**
     * Apply one chat or message from the linked phone. Timestamps come from the
     * phone, and unread is the phone's own count when Wasender sends one.
     */
    public function syncPhoneChat(array $parsed)
    {
        if (empty($parsed['phone'])) {
            return null;
        }
        $waName = empty($parsed['from_me']) && isset($parsed['wa_name']) ? $parsed['wa_name'] : null;
        $contact = $this->findOrCreateContact($parsed['phone'], $waName);
        if (! $contact) {
            return null;
        }
        $conversation = WhatsAppConversation::where('contact_id', $contact->id)->orderByDesc('id')->first();
        if (! $conversation) {
            $conversation = $this->openConversation($contact);
        }

        $stamp = isset($parsed['timestamp']) ? (int) $parsed['timestamp'] : 0;
        if ($stamp > 1000000000000) {
            $stamp = (int) floor($stamp / 1000);
        }
        $at = $stamp > 1000000000 ? \Carbon\Carbon::createFromTimestamp($stamp) : null;
        $created = false;
        if (! empty($parsed['message_id']) || ! empty($parsed['body'])) {
            $created = $this->storePhoneMessage($conversation, $contact, $parsed, $at);
        }
        if ($created && empty($parsed['from_me']) && ! empty($parsed['message_id'])) {
            $fresh = ! $at || $at->greaterThan(now()->subMinutes(10));
            $saved = $fresh
                ? WhatsAppMessage::where('provider_message_id', $parsed['message_id'])->first()
                : null;
            if ($saved) {
                $this->queueAssistantTurn($saved, true);
            }
        }

        $markReadFromPhone = false;
        if (array_key_exists('unread_count', $parsed) && (int) $parsed['unread_count'] === 0) {
            $markReadFromPhone = true;
        } elseif (! empty($parsed['from_me'])) {
            // An outgoing message straight from the phone means the owner had the
            // thread open and has seen the incoming messages. Treat it as read.
            $markReadFromPhone = true;
        }

        if ($markReadFromPhone) {
            \Illuminate\Support\Facades\Cache::put('wa.phone_read.'.$conversation->id, time(), now()->addMinutes(30));
            WhatsAppMessage::where('conversation_id', $conversation->id)
                ->where('direction', WhatsAppMessage::DIR_IN)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
        // Wasender sends unreadCount 1 on the message itself and does not send 0
        // when the phone is opened. The badge is the messages still unread.
        $this->recountUnread($conversation);

        $current = $conversation->last_activity_at;
            if ($at && (! $current || $at->greaterThan($current))) {
            $previewBody = isset($parsed['body']) ? $parsed['body'] : '';
            if (! empty($parsed['message_id'])) {
                $savedMessage = WhatsAppMessage::where('provider_message_id', $parsed['message_id'])->first();
                if ($savedMessage) {
                    $previewBody = $savedMessage->hubText();
                }
            }
            $previewType = isset($parsed['message_type']) ? $parsed['message_type'] : 'TEXT';
            if (trim((string) $previewBody) !== '' || ! in_array($previewType, ['TEXT', 'UNKNOWN', ''], true)) {
                $preview = $this->preview($previewBody, $previewType);
                $conversation->last_message = $preview;
                if (! $conversation->first_message) {
                    $conversation->first_message = $preview;
                }
            }
            $conversation->last_activity_at = $at;
            if (! empty($parsed['from_me'])) {
                $conversation->last_outgoing_at = $at;
            } else {
                $conversation->last_incoming_at = $at;
            }
        }
        $conversation->save();

        return $conversation;
    }

    protected function storePhoneMessage(WhatsAppConversation $conversation, WhatsAppContact $contact, array $parsed, $at)
    {
        $media = (! empty($parsed['media']) && is_array($parsed['media'])) ? $parsed['media'] : [];
        if (! empty($parsed['message_id'])) {
            $existing = WhatsAppMessage::where('provider_message_id', $parsed['message_id'])->first();
            if ($existing) {
                $this->fillFromEvent($existing, $parsed);

                return false;
            }
        }
        $outgoing = ! empty($parsed['from_me']);
        // A copy of a message we sent must not become an incoming unread row.
        // The client's own read receipt is not our unread.
        if ($this->absorbPhoneEcho($conversation, $parsed)) {
            return false;
        }
        $message = new WhatsAppMessage([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => $outgoing ? WhatsAppMessage::DIR_OUT : WhatsAppMessage::DIR_IN,
            'type' => isset($parsed['message_type']) ? $parsed['message_type'] : 'TEXT',
            'provider_message_id' => isset($parsed['message_id']) ? $parsed['message_id'] : null,
            'body' => isset($parsed['body']) ? $parsed['body'] : null,
            'media_json' => $media ? json_encode($media) : null,
            'status' => $outgoing ? WhatsAppMessage::STATUS_SENT : WhatsAppMessage::STATUS_DELIVERED,
            'sender_type' => $outgoing ? 'PHONE' : 'CONTACT',
        ]);
        if ($at) {
            $message->created_at = $at;
            $message->updated_at = $at;
            if ($outgoing) {
                $message->sent_at = $at;
            } else {
                $message->delivered_at = $at;
            }
        }
        $message->save();
        if (! $outgoing) {
            app(WhatsAppTranslator::class)->decorate($message);
        }

        return true;
    }

    public function applyStatus($providerMessageId, $status)
    {
        if (! $providerMessageId || ! $status) {
            return null;
        }

        $message = WhatsAppMessage::where('provider_message_id', $providerMessageId)->first();
        if (! $message) {
            return null;
        }

        $now = now();
        if ($status === WhatsAppMessage::STATUS_QUEUED && ! $message->queued_at) {
            $message->queued_at = $now;
        }
        if ($status === WhatsAppMessage::STATUS_SENT) {
            $message->sent_at = $message->sent_at ?: $now;
        }
        if ($status === WhatsAppMessage::STATUS_DELIVERED) {
            $message->delivered_at = $message->delivered_at ?: $now;
            if (! $message->sent_at) {
                $message->sent_at = $now;
            }
        }
        if ($status === WhatsAppMessage::STATUS_READ) {
            $message->read_at = $message->read_at ?: $now;
            $message->delivered_at = $message->delivered_at ?: $now;
            $message->sent_at = $message->sent_at ?: $now;
        }
        if ($status === WhatsAppMessage::STATUS_PLAYED) {
            $message->played_at = $message->played_at ?: $now;
            $message->read_at = $message->read_at ?: $now;
            $message->delivered_at = $message->delivered_at ?: $now;
        }
        if ($status === WhatsAppMessage::STATUS_FAILED) {
            $message->failed_at = $message->failed_at ?: $now;
        }

        $rank = [
            WhatsAppMessage::STATUS_QUEUED => 1,
            WhatsAppMessage::STATUS_SENT => 2,
            WhatsAppMessage::STATUS_DELIVERED => 3,
            WhatsAppMessage::STATUS_READ => 4,
            WhatsAppMessage::STATUS_PLAYED => 5,
            WhatsAppMessage::STATUS_FAILED => 6,
        ];
        $current = isset($rank[$message->status]) ? $rank[$message->status] : 0;
        $next = isset($rank[$status]) ? $rank[$status] : 0;
        if ($status === WhatsAppMessage::STATUS_FAILED || $next >= $current) {
            $message->status = $status;
        }
        $message->save();

        return $message;
    }

    public function ownerNotice(WhatsAppConversation $conversation, $body)
    {
        $body = trim((string) $body);
        $contact = $conversation->contact;
        if ($body === '' || ! $contact) {
            return ['success' => false];
        }
        $result = $this->deliverText($contact->normalized_phone, $body);
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $body,
            'status' => ! empty($result['success']) ? WhatsAppMessage::STATUS_SENT : WhatsAppMessage::STATUS_FAILED,
            'sender_type' => 'ASSISTANT',
            'provider_message_id' => isset($result['msg_id']) ? $result['msg_id'] : null,
            'sent_at' => now(),
        ]);

        return $result;
    }

    /**
     * WhatsApp a translator confirmation to every authorised command number.
     */
    public function notifyAuthorized($notice)
    {
        $notice = trim((string) $notice);
        if ($notice === '' || ! \Illuminate\Support\Facades\Schema::hasTable('whatsapp_owner_users')) {
            return;
        }
        $auth = app(OwnerAuthorizationService::class);
        $seen = [];
        foreach (\App\WhatsApp\WhatsAppOwnerUser::where('enabled', true)->get() as $row) {
            $user = \App\User::find($row->user_id);
            if (! $user || ! $auth->hasOwnerPermission($user)) {
                continue;
            }
            $phones = [
                $row->normalized_phone,
                $user->phone,
                $user->additional_phone,
            ];
            foreach ($phones as $phone) {
                $digits = preg_replace('/\D/', '', (string) $phone);
                if ($digits === '' || isset($seen[$digits])) {
                    continue;
                }
                if (! $auth->authorizePhone($digits)) {
                    continue;
                }
                $seen[$digits] = true;
                $contact = $this->findOrCreateContact($digits, $user->name);
                if (! $contact) {
                    continue;
                }
                $chat = $this->openConversation($contact);
                $this->ownerNotice($chat, $notice);
            }
        }
    }

    public function reply(WhatsAppConversation $conversation, $body, $userId = null, WhatsAppMessage $quote = null)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is empty.'];
        }
        $translator = app(WhatsAppTranslator::class);
        $translateSwitch = $translator->command($body);
        if ($translateSwitch) {
            $notice = $translator->setChat($conversation->id, $translateSwitch === 'on');
            $this->notifyAuthorized($notice);

            return [
                'success' => true,
                'notice' => $notice,
            ];
        }
        $command = app(OwnerCommandService::class)->commandToken($body);
        if ($command === 'AI OFF' || $command === 'AI ON') {
            return $this->switchModeFromReply($conversation, $command === 'AI ON', $userId);
        }
        if (AssistantRuntimeSettings::manualReplyTakesOver() && $conversation->mode === WhatsAppConversation::MODE_AI) {
            $conversation->mode = WhatsAppConversation::MODE_HUMAN;
            if ($userId) {
                $conversation->assigned_user_id = $userId;
            }
            $conversation->save();
            $this->event($conversation, WhatsAppConversationEvent::TAKEOVER, 'HUMAN_TAKEOVER_BY_REPLY', $userId);
        }

        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }

        $sendBody = $body;
        $translation = $translator->forClient($conversation, $body);
        if (is_array($translation) && ! empty($translation['failed'])) {
            $name = ! empty($translation['language_name']) ? $translation['language_name'] : 'the client’s language';

            return [
                'success' => false,
                'error' => 'Not sent. This chat stays in '.$name.', and the translation did not finish.',
            ];
        }
        if ($translation) {
            $sendBody = $translation['text'];
        }
        $quoteWasender = $quote ? $quote->wasenderId() : '';
        $quoteSnippet = '';
        if ($quote) {
            $quoteSnippet = trim(preg_replace('/\s+/u', ' ', (string) ($quote->body !== '' ? $quote->body : $quote->hubText())));
            if (function_exists('mb_substr')) {
                $quoteSnippet = mb_substr($quoteSnippet, 0, 140);
            }
            if ($quoteWasender === '' && $quoteSnippet !== '') {
                $sendBody = "↩ ".$quoteSnippet."\n".$sendBody;
            }
        }
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $sendBody,
            'media_json' => $this->replyMedia($translation, $quote, $quoteSnippet),
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
        ]);

        if ($conversation->isWebsite()) {
            $message->provider_message_id = 'webmsg:'.uniqid('', true);
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
            $result = ['success' => true, 'msg_id' => $message->provider_message_id, 'channel' => 'website'];
        } elseif (app(\App\Services\Mai\MaiProviderRouter::class)->usesMai($conversation)) {
            $result = app(\App\Services\Mai\MaiOutboundService::class)->deliver(
                $conversation,
                $sendBody,
                'hub-'.$message->id,
                $message
            );
            if (! empty($result['uncertain'])) {
                $message->error = 'Delivery was not confirmed. It was not sent again.';
                $message->save();
            } elseif (! empty($result['success'])) {
                $msgId = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
                $message->provider_message_id = $msgId ?: null;
                $message->status = WhatsAppMessage::STATUS_SENT;
                $message->sent_at = now();
                $message->save();
            } else {
                $message->status = WhatsAppMessage::STATUS_FAILED;
                $message->failed_at = now();
                $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
                $message->save();
            }
        } else {
            if ($quoteWasender !== '') {
                $result = app(\App\Services\BeyondWasenderService::class)->sendTextRaw(
                    $contact->normalized_phone,
                    $sendBody,
                    false,
                    true,
                    30,
                    true,
                    $quoteWasender
                );
            } else {
                $result = $this->provider->sendText($contact->normalized_phone, $sendBody);
            }
            if (! empty($result['success'])) {
                $msgId = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
                $message->provider_message_id = $msgId ?: null;
                $message->status = WhatsAppMessage::STATUS_SENT;
                $message->sent_at = now();
                $this->keepWasenderId($message, $msgId);
                $message->save();
            } else {
                $message->status = WhatsAppMessage::STATUS_FAILED;
                $message->failed_at = now();
                $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
                $message->save();
            }
        }

        $preview = $this->preview($message->hubText(), 'TEXT');
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        $this->stampIncomingRead($conversation);
        $conversation->unread_count = 0;
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        }
        $conversation->save();
        try {
            app(WhatsAppLeadService::class)->conversationEvent(
                $conversation->id,
                WhatsAppConversationEvent::REPLIED,
                'Staff replied',
                $userId
            );
        } catch (\Exception $e) {
        }

        $result['message'] = $message;

        return $result;
    }

    protected function replyMedia($translation, $quote, $quoteSnippet)
    {
        $media = [];
        if ($translation) {
            $media['translation'] = [
                'staff' => $translation['staff'],
                'language' => $translation['language'],
                'language_name' => $translation['language_name'],
            ];
        }
        if ($quote && $quoteSnippet !== '') {
            $media['quote'] = [
                'id' => (int) $quote->id,
                'text' => $quoteSnippet,
            ];
        }

        return $media ? json_encode($media) : null;
    }

    protected function keepWasenderId(WhatsAppMessage $message, $msgId)
    {
        if ($msgId === null || ! preg_match('/^\d+$/', (string) $msgId)) {
            return;
        }
        $media = $message->media();
        $media['wasender_id'] = (string) $msgId;
        $message->media_json = json_encode($media);
    }

    public function editOutgoing(WhatsAppConversation $conversation, WhatsAppMessage $message, $text)
    {
        $text = trim((string) $text);
        if ((int) $message->conversation_id !== (int) $conversation->id || $message->direction !== WhatsAppMessage::DIR_OUT) {
            return ['success' => false, 'error' => 'That message is not in this chat.'];
        }
        if ($text === '') {
            return ['success' => false, 'error' => 'Write the new message first.'];
        }
        if (! $message->canEditOnWhatsApp()) {
            return ['success' => false, 'error' => 'WhatsApp only lets you edit a message for 15 minutes after it is sent.'];
        }
        $translator = app(WhatsAppTranslator::class);
        $sendBody = $text;
        $translation = $translator->forClient($conversation, $text);
        if (is_array($translation) && ! empty($translation['failed'])) {
            $name = ! empty($translation['language_name']) ? $translation['language_name'] : 'their language';

            return ['success' => false, 'error' => 'Not changed. The translation into '.$name.' did not finish.'];
        }
        if ($translation) {
            $sendBody = $translation['text'];
        }
        if (! $message->isWebsiteMessage() && ! $conversation->isWebsite()) {
            $result = app(\App\Services\BeyondWasenderService::class)->editSentMessage($message->wasenderId(), $sendBody);
            if (empty($result['success'])) {
                return ['success' => false, 'error' => isset($result['error']) ? $result['error'] : 'WhatsApp did not change that message.'];
            }
        }
        $media = $message->media();
        $media['edited'] = true;
        if ($translation) {
            $media['translation'] = [
                'staff' => $translation['staff'],
                'language' => $translation['language'],
                'language_name' => $translation['language_name'],
            ];
        } else {
            unset($media['translation']);
        }
        $message->body = $sendBody;
        $message->media_json = json_encode($media);
        $message->save();

        return ['success' => true, 'message' => $message];
    }

    public function deleteMessage(WhatsAppConversation $conversation, WhatsAppMessage $message, $everyone)
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            return ['success' => false, 'error' => 'That message is not in this chat.'];
        }
        if ($everyone) {
            if (! $message->canDeleteForEveryone()) {
                return ['success' => false, 'error' => 'WhatsApp only lets you delete a message for everyone for 15 minutes after it is sent.'];
            }
            if (! $message->isWebsiteMessage() && ! $conversation->isWebsite()) {
                $result = app(\App\Services\BeyondWasenderService::class)->deleteSentMessage($message->wasenderId());
                if (empty($result['success'])) {
                    return ['success' => false, 'error' => isset($result['error']) ? $result['error'] : 'WhatsApp did not delete that message.'];
                }
            }
            $media = $message->media();
            $media['deleted'] = true;
            $message->media_json = json_encode($media);
            $message->save();

            return ['success' => true, 'message' => $message];
        }
        $message->status = WhatsAppMessage::STATUS_HIDDEN;
        $message->save();

        return ['success' => true, 'removed' => true, 'message' => $message];
    }

    public function starMessage(WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            return ['success' => false, 'error' => 'That message is not in this chat.'];
        }
        $media = $message->media();
        $media['starred'] = empty($media['starred']);
        $message->media_json = json_encode($media);
        $message->save();

        return ['success' => true, 'message' => $message];
    }

    public function forwardTo(WhatsAppConversation $target, WhatsAppMessage $source, $userId = null)
    {
        $text = trim((string) $source->body);
        if ($text === '') {
            $text = trim($source->hubText());
        }
        if ($text === '' || $source->wasDeleted()) {
            return ['success' => false, 'error' => 'There is nothing in that message to forward.'];
        }
        $contact = $target->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'That person cannot receive a message.'];
        }
        $message = WhatsAppMessage::create([
            'conversation_id' => $target->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $text,
            'media_json' => json_encode([
                'forwarded' => true,
                'from_message' => (int) $source->id,
            ]),
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
        ]);
        if ($target->isWebsite()) {
            $message->provider_message_id = 'webmsg:'.uniqid('', true);
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
            $result = ['success' => true];
        } else {
            $result = $this->provider->sendText($contact->normalized_phone, $text);
            if (! empty($result['success'])) {
                $msgId = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
                $message->provider_message_id = $msgId ?: null;
                $message->status = WhatsAppMessage::STATUS_SENT;
                $message->sent_at = now();
                $this->keepWasenderId($message, $msgId);
                $message->save();
            } else {
                $message->status = WhatsAppMessage::STATUS_FAILED;
                $message->failed_at = now();
                $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
                $message->save();

                return ['success' => false, 'error' => $message->error, 'message' => $message];
            }
        }
        $target->last_message = $this->preview($text, 'TEXT');
        $target->last_activity_at = now();
        $target->last_outgoing_at = now();
        $target->save();

        return ['success' => true, 'message' => $message];
    }

    /**
     * A recorded voice note. The client receives the words in their language,
     * and the voice note itself when the file can be sent.
     */
    public function replyVoice(WhatsAppConversation $conversation, $localPath, $originalName, $mime, $userId = null)
    {
        if (! is_file($localPath)) {
            return ['success' => false, 'error' => 'The voice note was empty.'];
        }
        if (AssistantRuntimeSettings::manualReplyTakesOver() && $conversation->mode === WhatsAppConversation::MODE_AI) {
            $conversation->mode = WhatsAppConversation::MODE_HUMAN;
            if ($userId) {
                $conversation->assigned_user_id = $userId;
            }
            $conversation->save();
            $this->event($conversation, WhatsAppConversationEvent::TAKEOVER, 'HUMAN_TAKEOVER_BY_REPLY', $userId);
        }
        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }

        $heard = app(WhatsAppVoiceTranscriber::class)->transcribePath($localPath, $mime);
        if ($heard === '') {
            return ['success' => false, 'error' => 'Could not hear that voice note. Try again, or type the message.'];
        }
        $translator = app(WhatsAppTranslator::class);
        $sendBody = $heard;
        $translation = $translator->forClient($conversation, $heard);
        if (is_array($translation) && ! empty($translation['failed'])) {
            $name = ! empty($translation['language_name']) ? $translation['language_name'] : 'the client’s language';

            return [
                'success' => false,
                'error' => 'Not sent. This chat stays in '.$name.', and the translation did not finish.',
            ];
        }
        if (is_array($translation) && ! empty($translation['text'])) {
            $sendBody = $translation['text'];
        } else {
            $translation = null;
        }

        $storedPath = $this->storeChatFile($localPath, $originalName, 'voice.webm');
        if ($storedPath === '') {
            return ['success' => false, 'error' => 'Could not store the voice note.'];
        }
        $playable = $this->voiceFileForWhatsApp($localPath);

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'AUDIO',
            'body' => $sendBody,
            'media_json' => json_encode(array_filter([
                'url' => $storedPath,
                'file_name' => (string) $originalName,
                'type' => 'AUDIO',
                'mimetype' => (string) $mime,
                'translation' => $translation ? [
                    'staff' => $heard,
                    'language' => $translation['language'],
                    'language_name' => $translation['language_name'],
                ] : [
                    'staff' => $heard,
                    'language' => $translator->staffLanguage()['code'],
                    'language_name' => $translator->staffLanguage()['name'],
                ],
            ])),
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
        ]);

        $textResult = ['success' => false, 'error' => 'Send failed.'];
        $audioResult = ['success' => false];
        if ($conversation->isWebsite()) {
            $message->provider_message_id = 'webmsg:'.uniqid('', true);
            $textResult = ['success' => true];
        } else {
            if ($playable) {
                $audioResult = $this->provider->sendAudio($contact->normalized_phone, $playable);
            }
            if ($translation || empty($audioResult['success'])) {
                $textResult = $this->provider->sendText($contact->normalized_phone, $sendBody);
            }
        }
        if (! empty($textResult['success']) || ! empty($audioResult['success'])) {
            $msgId = ! empty($textResult['msg_id']) ? (string) $textResult['msg_id'] : (! empty($audioResult['msg_id']) ? (string) $audioResult['msg_id'] : null);
            $message->provider_message_id = $msgId ?: null;
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $this->keepWasenderId($message, $msgId);
            $message->save();
        } else {
            $message->status = WhatsAppMessage::STATUS_FAILED;
            $message->failed_at = now();
            $message->error = isset($textResult['error']) ? substr((string) $textResult['error'], 0, 500) : 'send failed';
            $message->save();
            $textResult['message'] = $message;

            return $textResult;
        }

        $preview = $this->preview($message->hubText(), 'AUDIO');
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        $this->stampIncomingRead($conversation);
        $conversation->unread_count = 0;
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        }
        $conversation->save();

        return ['success' => true, 'message' => $message];
    }

    protected function storeChatFile($localPath, $originalName, $fallback)
    {
        $dir = public_path('whatsapp-chat');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return '';
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $originalName);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            $safe = $fallback;
        }
        $stored = date('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8).'-'.$safe;
        $dest = $dir.DIRECTORY_SEPARATOR.$stored;
        if (! @copy($localPath, $dest)) {
            return '';
        }

        return 'whatsapp-chat/'.$stored;
    }

    public function voiceFileForWhatsApp($path)
    {
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        if (in_array($ext, ['ogg', 'opus', 'mp3', 'm4a', 'aac'], true)) {
            return $path;
        }
        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '' || ! is_file($ffmpeg)) {
            return null;
        }
        $out = sys_get_temp_dir().'/wa-voice-'.substr(md5($path.microtime(true)), 0, 10).'.ogg';
        $cmd = escapeshellarg($ffmpeg).' -y -i '.escapeshellarg($path).' -vn -c:a libopus -b:a 32k -ac 1 '.escapeshellarg($out).' 2>/dev/null';
        exec($cmd, $lines, $code);
        if ($code !== 0 || ! is_file($out) || filesize($out) < 32) {
            return null;
        }

        return $out;
    }

    public function replyFile(WhatsAppConversation $conversation, $localPath, $originalName, $mime, $caption, $userId = null)
    {
        $caption = trim((string) $caption);
        if (! is_file($localPath)) {
            return ['success' => false, 'error' => 'Choose a photo or file to send.'];
        }
        if (AssistantRuntimeSettings::manualReplyTakesOver() && $conversation->mode === WhatsAppConversation::MODE_AI) {
            $conversation->mode = WhatsAppConversation::MODE_HUMAN;
            if ($userId) {
                $conversation->assigned_user_id = $userId;
            }
            $conversation->save();
            $this->event($conversation, WhatsAppConversationEvent::TAKEOVER, 'HUMAN_TAKEOVER_BY_REPLY', $userId);
        }

        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }

        $sendCaption = $caption;
        $translation = null;
        if ($caption !== '') {
            $translation = app(WhatsAppTranslator::class)->forClient($conversation, $caption);
            if (is_array($translation) && ! empty($translation['failed'])) {
                $name = ! empty($translation['language_name']) ? $translation['language_name'] : 'the client’s language';

                return [
                    'success' => false,
                    'error' => 'Not sent. This chat stays in '.$name.', and the translation did not finish.',
                ];
            }
            if (is_array($translation) && ! empty($translation['text'])) {
                $sendCaption = $translation['text'];
            } else {
                $translation = null;
            }
        }

        $isImage = stripos((string) $mime, 'image/') === 0;
        $type = $isImage ? 'IMAGE' : 'DOCUMENT';
        $dir = public_path('whatsapp-chat');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => 'Could not store the file.'];
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $originalName);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            $safe = $isImage ? 'photo.jpg' : 'file';
        }
        $stored = date('YmdHis').'-'.substr(md5(uniqid('', true)), 0, 8).'-'.$safe;
        $dest = $dir.DIRECTORY_SEPARATOR.$stored;
        if (! @copy($localPath, $dest)) {
            return ['success' => false, 'error' => 'Could not store the file.'];
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => $type,
            'body' => $sendCaption !== '' ? $sendCaption : null,
            'media_json' => json_encode(array_filter([
                'url' => 'whatsapp-chat/'.$stored,
                'file_name' => (string) $originalName,
                'type' => $type,
                'translation' => $translation ? [
                    'staff' => $translation['staff'],
                    'language' => $translation['language'],
                    'language_name' => $translation['language_name'],
                ] : null,
            ])),
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
        ]);

        if ($conversation->isWebsite()) {
            $message->provider_message_id = 'webmsg:'.uniqid('', true);
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
            $result = ['success' => true];
        } elseif ($isImage) {
            $result = $this->provider->sendImage($contact->normalized_phone, $dest, $sendCaption !== '' ? $sendCaption : null, true);
        } else {
            $result = $this->provider->sendDocument($contact->normalized_phone, $dest, $originalName, $sendCaption !== '' ? $sendCaption : null, true);
        }

        if (! $conversation->isWebsite()) {
            if (! empty($result['success'])) {
                $message->provider_message_id = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
                $message->status = WhatsAppMessage::STATUS_SENT;
                $message->sent_at = now();
                $message->save();
            } else {
                $message->status = WhatsAppMessage::STATUS_FAILED;
                $message->failed_at = now();
                $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
                $message->save();
            }
        }

        $preview = $sendCaption !== '' ? $this->preview($message->hubText(), $type) : '['.$type.']';
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        $this->stampIncomingRead($conversation);
        $conversation->unread_count = 0;
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        }
        $conversation->save();
        $result['message'] = $message;

        return $result;
    }

    public function sendServicePoll(WhatsAppConversation $conversation)
    {
        $contact = $conversation ? $conversation->contact : null;
        if (! $conversation || ! $contact || $conversation->mode !== WhatsAppConversation::MODE_AI) {
            return ['success' => false];
        }
        $menu = app(\App\Services\Assistant\ServiceMenu::class);

        // Website chat: interactive choices in the widget — never send a Wasender poll
        // to a visitor who is already chatting on the site.
        if ($conversation->isWebsite()) {
            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'direction' => WhatsAppMessage::DIR_OUT,
                'type' => 'TEXT',
                'provider_message_id' => 'webmsg:'.uniqid('', true),
                'body' => $menu->websitePrompt(),
                'media_json' => json_encode(['choices' => $menu->choices()]),
                'status' => WhatsAppMessage::STATUS_SENT,
                'sender_type' => 'ASSISTANT',
                'sent_at' => now(),
            ]);
            $conversation->last_message = mb_substr($menu->websitePrompt(), 0, 120);
            $conversation->last_activity_at = now();
            $conversation->last_outgoing_at = now();
            $conversation->save();

            return ['success' => true, 'channel' => 'website', 'message' => $message];
        }

        $wasender = app(\App\Services\BeyondWasenderService::class);
        if (! $wasender->isConfigured()) {
            return ['success' => false, 'error' => 'not_configured'];
        }
        $result = $wasender->sendPoll($contact->normalized_phone, 'Which service do you need?', $menu->options());
        if (empty($result['success'])) {
            return $result;
        }
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => 'Which service do you need?',
            'status' => WhatsAppMessage::STATUS_SENT,
            'sender_type' => 'ASSISTANT',
            'provider_message_id' => isset($result['msg_id']) ? (string) $result['msg_id'] : null,
            'sent_at' => now(),
        ]);

        return $result;
    }

    public function markRead(WhatsAppConversation $conversation)
    {
        app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () use ($conversation) {
            $this->stampIncomingRead($conversation);
            $conversation->unread_count = 0;
            $conversation->save();
        });
        $this->touchStaffView($conversation);
    }

    /**
     * Incoming messages from the contact that this staff member has not opened.
     * A client's Sent / Delivered / Read tick on our own message is not unread.
     */
    public function recountUnread(WhatsAppConversation $conversation)
    {
        $repliedAt = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_OUT)
            ->where('sender_type', 'STAFF')
            ->max('created_at');
        if ($repliedAt) {
            $this->staffUnreadQuery($conversation)
                ->where('created_at', '<=', $repliedAt)
                ->update(['read_at' => now()]);
        }
        $left = (int) $this->staffUnreadQuery($conversation)->count();
        $conversation->unread_count = $left;

        return $left;
    }

    /**
     * The staff member has this chat open, so a message that arrives now is read.
     */
    public function touchStaffView(WhatsAppConversation $conversation)
    {
        \Illuminate\Support\Facades\Cache::put('wa.web_open.'.$conversation->id, time(), now()->addSeconds(20));
    }

    protected function staffIsViewing(WhatsAppConversation $conversation)
    {
        return (bool) \Illuminate\Support\Facades\Cache::get('wa.web_open.'.$conversation->id);
    }

    protected function stampIncomingRead(WhatsAppConversation $conversation)
    {
        $this->staffUnreadQuery($conversation)->update(['read_at' => now()]);
    }

    protected function staffUnreadQuery(WhatsAppConversation $conversation)
    {
        return WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_IN)
            ->whereNull('read_at')
            ->where(function ($query) {
                $query->whereNull('sender_type')
                    ->orWhere('sender_type', '')
                    ->orWhere('sender_type', 'CONTACT');
            });
    }

    public function readOnWeb(WhatsAppConversation $conversation)
    {
        $wasUnread = (int) $conversation->unread_count > 0
            || $this->staffUnreadQuery($conversation)->exists();
        $this->markRead($conversation);
        if (! $wasUnread) {
            return;
        }
        $last = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_IN)
            ->whereNotNull('provider_message_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if (! $last) {
            return;
        }
        $messageId = (string) $last->provider_message_id;
        app()->terminating(function () use ($messageId) {
            try {
                $event = \App\WhatsApp\WhatsAppWebhookEvent::where('provider_event_id', $messageId)->orderByDesc('id')->first();
                if (! $event) {
                    return;
                }
                $payload = $event->payloadArray();
                $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
                $message = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : [];
                if (isset($message[0]) && is_array($message[0])) {
                    $message = $message[0];
                }
                $key = isset($message['key']) && is_array($message['key']) ? $message['key'] : [];
                if (! empty($key['fromMe'])) {
                    return;
                }
                app(\App\Services\BeyondWasenderService::class)->markRead($key);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info('[whatsapp-hub] mark read on phone failed', ['error' => $e->getMessage()]);
            }
        });
    }

    public function readOnPhone($providerMessageId, $phone)
    {
        $message = $providerMessageId
            ? WhatsAppMessage::where('provider_message_id', $providerMessageId)->first()
            : null;
        $conversation = $message ? WhatsAppConversation::find($message->conversation_id) : null;
        if (! $conversation && $phone) {
            $normalized = $this->identity->normalize($phone);
            $contact = $normalized !== '' ? WhatsAppContact::where('normalized_phone', $normalized)->first() : null;
            $conversation = $contact
                ? WhatsAppConversation::where('contact_id', $contact->id)->orderByDesc('id')->first()
                : null;
        }
        if (! $conversation) {
            return null;
        }

        $read = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_IN)
            ->whereNull('read_at');
        if ($message) {
            $read->where('created_at', '<=', $message->created_at);
        }
        $read->update(['read_at' => now()]);

        $before = (int) $conversation->unread_count;
        $left = $this->recountUnread($conversation);
        if ($before !== $left) {
            $conversation->save();
        }
        if ($left === 0) {
            \Illuminate\Support\Facades\Cache::put('wa.phone_read.'.$conversation->id, time(), now()->addMinutes(30));
        }

        return $conversation;
    }

    /**
     * The phone already marked this chat read, and this message is not newer
     * than that read. Keeps a late webhook from putting the badge back.
     */
    protected function phoneAlreadyRead(WhatsAppConversation $conversation, array $parsed)
    {
        $readAt = \Illuminate\Support\Facades\Cache::get('wa.phone_read.'.$conversation->id);
        if (! $readAt) {
            return false;
        }
        $stamp = isset($parsed['timestamp']) ? (int) $parsed['timestamp'] : time();
        if ($stamp > 1000000000000) {
            $stamp = (int) floor($stamp / 1000);
        }
        if ($stamp < 1000000000) {
            $stamp = time();
        }

        return $stamp <= ((int) $readAt) + 3;
    }

    public function assign(WhatsAppConversation $conversation, $userId, $actorId = null)
    {
        $conversation->assigned_user_id = $userId ?: null;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::ASSIGNED, $userId ? 'Assigned to #'.$userId : 'Unassigned', $actorId);

        return $conversation;
    }

    protected function switchModeFromReply(WhatsAppConversation $conversation, $aiOn, $userId = null)
    {
        $notice = app(OwnerCommandService::class)->setAssistantEnabled((bool) $aiOn, $userId);
        $fresh = $conversation->fresh() ?: $conversation;
        if ($aiOn && strtoupper((string) $fresh->mode) === WhatsAppConversation::MODE_AI) {
            $this->resumeLatestCustomerMessage($fresh);
        }

        return [
            'success' => true,
            'mode' => strtoupper((string) $fresh->mode),
            'notice' => $notice,
        ];
    }

    public function enableAi(WhatsAppConversation $conversation, $userId = null)
    {
        if (! app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled()) {
            if (strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI) {
                $conversation->mode = WhatsAppConversation::MODE_HUMAN;
                $conversation->save();
                $this->event($conversation, WhatsAppConversationEvent::MODE, 'AI is off, so this chat stayed with a person', $userId);
            }

            return $conversation;
        }
        $conversation->mode = WhatsAppConversation::MODE_AI;
        $conversation->assigned_user_id = null;
        if ($conversation->status === WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_OPEN;
        }
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::MODE, 'AI enabled', $userId);
        $this->resumeLatestCustomerMessage($conversation);

        return $conversation;
    }

    public function assistantReply(WhatsAppConversation $conversation, $body, array $media = null)
    {
        $body = \App\Support\WhatsAppMessage::plainAiReply($body);
        $body = $this->withoutRepeatGreeting($conversation, $body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is empty.'];
        }
        $conversation = WhatsAppConversation::find($conversation->id);
        if (! $conversation || $conversation->mode !== WhatsAppConversation::MODE_AI) {
            return ['success' => false, 'error' => 'mode_changed', 'discarded' => true];
        }
        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }

        $mediaJson = null;
        $hadServiceMenu = strpos($body, '1. Sound') !== false;
        if (is_array($media) && (! empty($media['choices']) || ! empty($media['ui']))) {
            $payload = array_filter([
                'choices' => isset($media['choices']) ? $media['choices'] : null,
                'ui' => isset($media['ui']) ? $media['ui'] : null,
            ], function ($v) {
                return $v !== null;
            });
            $mediaJson = json_encode($payload);
        } elseif ($conversation->isWebsite() && $hadServiceMenu) {
            $menu = app(\App\Services\Assistant\ServiceMenu::class);
            $body = trim(preg_replace('/\n*You can tap a service below.*$/s', "\n\n".$menu->websitePrompt(), $body));
            if ($body === '' || $body === $menu->websitePrompt()) {
                $body = $menu->websitePrompt();
            }
            $mediaJson = json_encode(['choices' => $menu->choices()]);
        }

        $message = WhatsAppMessage::create(array_filter([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $body,
            'media_json' => $mediaJson,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'ASSISTANT',
            'queued_at' => now(),
        ], function ($v) {
            return $v !== null;
        }));

        // Re-check mode immediately before transport commit (takeover discard).
        $conversation = WhatsAppConversation::find($conversation->id);
        if (! $conversation || $conversation->mode !== WhatsAppConversation::MODE_AI) {
            $message->status = WhatsAppMessage::STATUS_FAILED;
            $message->failed_at = now();
            $message->error = 'mode_changed';
            $message->save();

            return ['success' => false, 'error' => 'mode_changed', 'discarded' => true];
        }

        if ($conversation->isWebsite()) {
            $message->provider_message_id = 'webmsg:'.uniqid('', true);
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
            $result = ['success' => true, 'msg_id' => $message->provider_message_id, 'channel' => 'website', 'had_service_menu' => $hadServiceMenu];
        } else {
            $result = $this->deliverText($contact->normalized_phone, $body);
            if (! empty($result['success'])) {
                $message->provider_message_id = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
                $message->status = WhatsAppMessage::STATUS_SENT;
                $message->sent_at = now();
                $message->save();
            } else {
                $message->status = WhatsAppMessage::STATUS_FAILED;
                $message->failed_at = now();
                $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
                $message->save();
            }
        }
        $preview = $this->preview($body, 'TEXT');
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        if ($conversation->status !== WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        }
        $conversation->save();
        $result['message'] = $message;

        return $result;
    }

    protected function withoutRepeatGreeting(WhatsAppConversation $conversation, $body)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return $body;
        }
        $prior = WhatsAppMessage::where('conversation_id', $conversation->id)->count();
        if ($prior <= 1) {
            return $body;
        }
        $name = $this->knownPersonName($conversation);
        $namePart = $name !== '' ? '(?:'.preg_quote($name, '/').'[,!]?\s*)?' : '';
        $stripped = preg_replace('/^(?:hello|hi|hey|hiya|bonjour|bonsoir|salut|good morning|good afternoon|good evening)[,!]?\s+'.$namePart.'/iu', '', $body, 1);
        if (! is_string($stripped)) {
            return $body;
        }
        $rest = trim($stripped);
        if ($rest === '') {
            return 'How can I help you?';
        }
        if (strcasecmp($rest, $body) === 0) {
            return $body;
        }

        return $rest;
    }

    protected function knownPersonName(WhatsAppConversation $conversation)
    {
        $contact = $conversation->contact;
        if (! $contact) {
            return '';
        }
        $name = '';
        $phone = trim((string) $contact->normalized_phone);
        if ($phone !== '') {
            try {
                $name = trim((string) app(\App\Services\WhatsApp\GroupContactExportService::class)->nameForPhone($phone, false));
            } catch (\Throwable $e) {
                $name = '';
            }
        }
        if ($name === '') {
            $name = trim((string) $contact->wa_name);
        }
        if ($name === '' || preg_match('/^\+?[\d\s\-\(\)]+$/', $name)) {
            return '';
        }

        return $name;
    }

    public function takeover(WhatsAppConversation $conversation, $userId)
    {
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->assigned_user_id = $userId;
        if (in_array($conversation->status, [WhatsAppConversation::STATUS_CLOSED, WhatsAppConversation::STATUS_RESOLVED], true)) {
            $conversation->status = WhatsAppConversation::STATUS_OPEN;
        }
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::TAKEOVER, 'Taken by staff', $userId);

        return $conversation;
    }

    public function release(WhatsAppConversation $conversation, $userId)
    {
        $conversation->assigned_user_id = null;
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        if ($conversation->status === WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_OPEN;
        }
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::RELEASED, 'Released', $userId);

        return $conversation;
    }

    public function pause(WhatsAppConversation $conversation, $userId)
    {
        $conversation->mode = WhatsAppConversation::MODE_PAUSED;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::MODE, 'Paused', $userId);

        return $conversation;
    }

    public function destroy(WhatsAppConversation $conversation)
    {
        $id = (int) $conversation->id;
        \Illuminate\Support\Facades\DB::transaction(function () use ($conversation, $id) {
            foreach ([
                'whatsapp_messages',
                'whatsapp_notes',
                'whatsapp_conversation_events',
                'assistant_memories',
                'assistant_activities',
                'whatsapp_calls',
            ] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, 'conversation_id')) {
                    \Illuminate\Support\Facades\DB::table($table)->where('conversation_id', $id)->delete();
                }
            }
            foreach ([
                'quotations' => 'whatsapp_conversation_id',
                'attendances' => 'whatsapp_conversation_id',
                'internship_submissions' => 'whatsapp_conversation_id',
            ] as $table => $column) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                    \Illuminate\Support\Facades\DB::table($table)->where($column, $id)->update([$column => null]);
                }
            }
            $conversation->delete();
        });
    }

    public function close(WhatsAppConversation $conversation, $userId)
    {
        $conversation->mode = WhatsAppConversation::MODE_CLOSED;
        $conversation->status = WhatsAppConversation::STATUS_CLOSED;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::CLOSED, 'Closed', $userId);

        return $conversation;
    }

    public function reopen(WhatsAppConversation $conversation, $userId)
    {
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->status = WhatsAppConversation::STATUS_OPEN;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::REOPENED, 'Reopened', $userId);

        return $conversation;
    }

    public function addNote(WhatsAppConversation $conversation, $body, $authorId, $leadId = null)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $note = WhatsAppNote::create([
            'conversation_id' => $conversation->id,
            'lead_id' => $leadId,
            'author_id' => $authorId,
            'body' => $body,
        ]);
        $this->event($conversation, WhatsAppConversationEvent::NOTE, 'Internal note added', $authorId);

        return $note;
    }

    public function sendSecretText(WhatsAppConversation $conversation, $secret)
    {
        $secret = trim((string) $secret);
        $contact = $conversation->contact;
        if ($secret === '' || ! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Message is empty.'];
        }
        $stored = 'Verification code sent. It expires shortly. Do not share this code.';
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $stored,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'ASSISTANT',
            'queued_at' => now(),
        ]);
        $result = $this->provider->sendText($contact->normalized_phone, $secret);
        if (! empty($result['success'])) {
            $message->provider_message_id = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
        } else {
            $message->status = WhatsAppMessage::STATUS_FAILED;
            $message->failed_at = now();
            $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
            $message->save();
        }
        $conversation->last_message = $stored;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        $conversation->unread_count = 0;
        $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        $conversation->save();
        $result['message'] = $message;

        return $result;
    }

    public function sendExistingDocument(WhatsAppConversation $conversation, $localPath, $fileName, $caption, $userId, $senderType = 'STAFF')
    {
        $contact = $conversation->contact;
        if (! $contact || ! is_string($localPath) || ! is_file($localPath)) {
            return ['success' => false, 'error' => 'Document is not available.'];
        }
        $real = realpath($localPath);
        $root = realpath(base_path());
        if (! $real || strpos($real, $root) !== 0) {
            return ['success' => false, 'error' => 'Document path is not allowed.'];
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'DOCUMENT',
            'body' => $caption ?: $fileName,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => $senderType ?: 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
            'media_json' => json_encode(['path' => $fileName]),
        ]);
        $result = $this->provider->sendDocument($contact->normalized_phone, $real, $fileName, $caption);
        if (! empty($result['success'])) {
            $message->provider_message_id = isset($result['msg_id']) ? (string) $result['msg_id'] : null;
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sent_at = now();
            $message->save();
        } else {
            $message->status = WhatsAppMessage::STATUS_FAILED;
            $message->failed_at = now();
            $message->error = isset($result['error']) ? substr((string) $result['error'], 0, 500) : 'send failed';
            $message->save();
        }
        $conversation->last_message = '['.$fileName.']';
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
        if ($senderType === null || $senderType === '' || $senderType === 'STAFF') {
            $this->stampIncomingRead($conversation);
            $conversation->unread_count = 0;
        }
        $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::DOCUMENT, ! empty($result['success']) ? 'Document sent: '.$fileName : 'Document send failed: '.$fileName, $userId);
        $result['message'] = $message;

        return $result;
    }

    protected function resumeLatestCustomerMessage(WhatsAppConversation $conversation)
    {
        $latest = WhatsAppMessage::where('conversation_id', $conversation->id)->orderByDesc('id')->first();
        if (! $latest || $latest->direction !== WhatsAppMessage::DIR_IN) {
            return;
        }
        try {
            \App\Jobs\ProcessAssistantTurn::dispatch($latest->id)
                ->onConnection('database')
                ->onQueue('whatsapp');
        } catch (\Throwable $e) {
        }
    }

    protected function event(WhatsAppConversation $conversation, $type, $body, $actorId = null)
    {
        return WhatsAppConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'body' => $body,
            'actor_user_id' => $actorId,
        ]);
    }

    public function openingMode()
    {
        return $this->initialMode();
    }

    /**
     * A chat the staff already took, or already answered, stays on Human.
     */
    protected function staffKeepsHuman(WhatsAppConversation $conversation)
    {
        if (strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI) {
            return false;
        }
        if ($conversation->assigned_user_id) {
            return true;
        }
        if (strtoupper((string) $conversation->mode) !== WhatsAppConversation::MODE_HUMAN) {
            return false;
        }

        return WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('direction', WhatsAppMessage::DIR_OUT)
            ->where('sender_type', 'STAFF')
            ->exists();
    }

    /**
     * When AI is the default and the assistant is on, answer a chat that
     * nobody on staff has taken. A chat set to Human stays on Human.
     */
    public function promoteToAiIfDefault(WhatsAppConversation $conversation)
    {
        if ($conversation->mode === WhatsAppConversation::MODE_AI) {
            return $conversation;
        }
        if ($this->staffKeepsHuman($conversation)) {
            return $conversation;
        }
        try {
            if ($this->defaultMode() !== WhatsAppConversation::MODE_AI) {
                return $conversation;
            }
            if (! app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled()) {
                return $conversation;
            }
            if (app(ConversationAiSwitchService::class)->excludeReason($conversation) !== null) {
                return $conversation;
            }
            $conversation->mode = WhatsAppConversation::MODE_AI;
            $conversation->save();
            $this->event($conversation, WhatsAppConversationEvent::MODE, 'Auto-switched to AI because AI mode is on');
        } catch (\Exception $e) {
        }

        return $conversation->fresh() ?: $conversation;
    }

    /**
     * A closed chat stays closed until someone writes. That new message is answered
     * from the history already in the thread, on the website and on WhatsApp when AI is on.
     */
    protected function assistantContinuesClosedChat(WhatsAppConversation $conversation)
    {
        if (strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_PAUSED) {
            return false;
        }
        if ($conversation->isWebsite()) {
            return true;
        }
        try {
            return app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function initialMode()
    {
        $mode = $this->defaultMode();
        if ($mode === WhatsAppConversation::MODE_AI) {
            try {
                if (! app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled()) {
                    return WhatsAppConversation::MODE_HUMAN;
                }
            } catch (\Exception $e) {
                return WhatsAppConversation::MODE_HUMAN;
            }
        }

        return $mode;
    }

    protected function deliverText($phone, $body)
    {
        // AI replies and other automated sends always go through the bound
        // WhatsApp provider (WaSender). The legacy Twilio branch was removed so
        // a suspended/retired Twilio account can no longer swallow AI replies.
        if (method_exists($this->provider, 'sendPlainText')) {
            return $this->provider->sendPlainText($phone, $body);
        }

        return $this->provider->sendText($phone, $body);
    }

    protected function preview($body, $type)
    {
        $text = trim((string) $body);
        if ($text !== '') {
            return mb_substr($text, 0, 180);
        }

        return '['.$type.']';
    }
}
