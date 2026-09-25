<?php

namespace App\Services\Assistant;

use App\Services\WhatsApp\WhatsAppConversationService;
use App\User;
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

        return $conversation;
    }
}
