<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppContactLink;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use App\WhatsApp\WhatsAppSetting;

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

        return in_array($mode, [
            WhatsAppConversation::MODE_AI,
            WhatsAppConversation::MODE_HUMAN,
            WhatsAppConversation::MODE_PAUSED,
            WhatsAppConversation::MODE_CLOSED,
        ], true) ? $mode : WhatsAppConversation::MODE_HUMAN;
    }

    public function findOrCreateContact($phone, $waName = null)
    {
        $normalized = $this->identity->normalize($phone);
        if ($normalized === '') {
            return null;
        }

        $contact = WhatsAppContact::firstOrNew(['normalized_phone' => $normalized]);
        $contact->display_phone = $this->identity->display($phone);
        if ($waName && trim((string) $waName) !== '') {
            $contact->wa_name = $waName;
        }
        $contact->save();
        $this->syncIdentityLinks($contact);

        return $contact->fresh('links');
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
                if ($conversation->mode === WhatsAppConversation::MODE_CLOSED) {
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

        $conversation = $this->openConversation($contact);
        if (! empty($parsed['message_id'])) {
            $existing = WhatsAppMessage::where('provider_message_id', $parsed['message_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $preview = $this->preview($parsed['body'] ?? null, $parsed['message_type'] ?? 'TEXT');
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

        $conversation->unread_count = (int) $conversation->unread_count + 1;
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
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('assistant_activities')) {
                \App\Jobs\ProcessAssistantTurn::dispatch($message->id)->onQueue('whatsapp');
            }
        } catch (\Exception $e) {
        }

        return $message;
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

    public function reply(WhatsAppConversation $conversation, $body, $userId = null)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is empty.'];
        }

        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'STAFF',
            'sender_user_id' => $userId,
            'queued_at' => now(),
        ]);

        $result = $this->provider->sendText($contact->normalized_phone, $body);
        if (! empty($result['success'])) {
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

        $preview = $this->preview($body, 'TEXT');
        if (! $conversation->first_message) {
            $conversation->first_message = $preview;
        }
        $conversation->last_message = $preview;
        $conversation->last_activity_at = now();
        $conversation->last_outgoing_at = now();
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

    public function markRead(WhatsAppConversation $conversation)
    {
        $conversation->unread_count = 0;
        $conversation->save();
    }

    public function assign(WhatsAppConversation $conversation, $userId, $actorId = null)
    {
        $conversation->assigned_user_id = $userId ?: null;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::ASSIGNED, $userId ? 'Assigned to #'.$userId : 'Unassigned', $actorId);

        return $conversation;
    }

    public function enableAi(WhatsAppConversation $conversation, $userId = null)
    {
        $conversation->mode = WhatsAppConversation::MODE_AI;
        if ($conversation->status === WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_OPEN;
        }
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::MODE, 'AI enabled', $userId);

        return $conversation;
    }

    public function assistantReply(WhatsAppConversation $conversation, $body)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return ['success' => false, 'error' => 'Message is empty.'];
        }
        $contact = $conversation->contact;
        if (! $contact || $contact->isBlocked()) {
            return ['success' => false, 'error' => 'Contact is blocked or missing.'];
        }
        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => WhatsAppMessage::DIR_OUT,
            'type' => 'TEXT',
            'body' => $body,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'sender_type' => 'ASSISTANT',
            'queued_at' => now(),
        ]);
        $result = $this->provider->sendText($contact->normalized_phone, $body);
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
        $conversation->unread_count = 0;
        $conversation->status = WhatsAppConversation::STATUS_WAITING_CUSTOMER;
        $conversation->save();
        $this->event($conversation, WhatsAppConversationEvent::DOCUMENT, 'Document sent: '.$fileName, $userId);
        $result['message'] = $message;

        return $result;
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

    protected function preview($body, $type)
    {
        $text = trim((string) $body);
        if ($text !== '') {
            return mb_substr($text, 0, 180);
        }

        return '['.$type.']';
    }
}
