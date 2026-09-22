<?php

namespace App\Services\Assistant;

use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;

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
        $conversation->save();
        WhatsAppConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => WhatsAppConversationEvent::TAKEOVER,
            'body' => 'AI handed over: '.$reason,
        ]);

        return $conversation;
    }
}
