<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppCallRequest;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class WhatsAppCallRequestService
{
    public function open(WhatsAppConversation $conversation, $body)
    {
        if (! Schema::hasTable('whatsapp_call_requests')) {
            return null;
        }
        $row = new WhatsAppCallRequest();
        $row->conversation_id = $conversation->id;
        $row->contact_id = $conversation->contact_id;
        $row->status = WhatsAppCallRequest::REQUESTED;
        $row->requested_body = mb_substr((string) $body, 0, 500);
        $agentId = \App\Services\Assistant\AssistantRuntimeSettings::handoverUserId();
        if ($agentId > 0) {
            $row->assigned_user_id = $agentId;
            $row->status = WhatsAppCallRequest::ASSIGNED;
        }
        $row->save();

        return $row;
    }
}
