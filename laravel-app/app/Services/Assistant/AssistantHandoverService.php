<?php

namespace App\Services\Assistant;

use App\Services\Messaging\NotificationRouter;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Support\TwilioAdminCopy;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;

class AssistantHandoverService
{
    protected $conversations;

    public function __construct(WhatsAppConversationService $conversations)
    {
        $this->conversations = $conversations;
    }

    public function toHuman(WhatsAppConversation $conversation, $reason = 'handover')
    {
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        if ($conversation->status === WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_STAFF;
        }
        $agentId = AssistantRuntimeSettings::handoverUserId();
        if ($agentId > 0 && ! $conversation->assigned_user_id && User::where('id', $agentId)->exists()) {
            $conversation->assigned_user_id = $agentId;
        }
        $conversation->save();
        WhatsAppConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => WhatsAppConversationEvent::TAKEOVER,
            'body' => 'AI handed over: '.$reason,
        ]);
        $this->forwardChat($conversation, $reason);

        return $conversation;
    }

    protected function forwardChat(WhatsAppConversation $conversation, $reason)
    {
        try {
            $contact = $conversation->contact;
            $who = 'A visitor';
            if ($contact) {
                $name = trim((string) $contact->displayName());
                $phone = trim((string) ($contact->display_phone ?: $contact->normalized_phone));
                $who = trim($name.($phone !== '' ? ' '.$phone : ''));
            }
            $lines = [];
            $messages = $conversation->messages()->orderBy('id', 'desc')->limit(12)->get()->reverse();
            foreach ($messages as $message) {
                $body = trim((string) $message->body);
                if ($body === '') {
                    continue;
                }
                $speaker = $message->direction === WhatsAppMessage::DIR_IN ? 'Customer' : 'Mbole AI';
                $lines[] = $speaker.': '.$body;
            }
            $text = "Mbole AI needs a staff member to contact this chat.\n"
                .$who."\n"
                .'Reason: '.$reason;
            if ($lines) {
                $text .= "\n\n".implode("\n", $lines);
            }
            app(NotificationRouter::class)->sendWhatsAppText(TwilioAdminCopy::PHONE, TwilioAdminCopy::clip($text));
        } catch (\Throwable $e) {
            \Log::warning('[assistant-handover] could not forward the chat: '.$e->getMessage());
        }
    }
}
