<?php

namespace App\Services\Assistant;

use App\WhatsApp\Lead;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;

class AssistantContextBuilder
{
    public function build(WhatsAppConversation $conversation)
    {
        $contact = $conversation->contact;
        if ($contact) {
            $contact->load('links');
        }
        $roles = [];
        $customerId = null;
        $internUserId = null;
        if ($contact) {
            foreach ($contact->links as $link) {
                $roles[] = $link->role;
                if ($link->role === 'customer') {
                    $customerId = (int) $link->linkable_id;
                }
                if ($link->role === 'intern') {
                    $internUserId = (int) $link->linkable_id;
                }
            }
        }
        $lead = null;
        if ($contact && Schema::hasTable('leads')) {
            $row = Lead::where('contact_id', $contact->id)->orderByDesc('id')->first();
            if ($row) {
                $lead = ['id' => $row->id, 'status' => $row->status, 'category' => $row->category];
            }
        }
        $history = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit((int) config('assistant.history_limit'))
            ->get()
            ->reverse()
            ->values()
            ->map(function ($m) {
                return [
                    'direction' => $m->direction,
                    'sender' => $m->sender_type,
                    'body' => mb_substr((string) $m->body, 0, 240),
                ];
            })
            ->all();

        return [
            'conversation' => $conversation,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => $contact ? $contact->displayName() : null,
            'phone' => $contact ? $contact->normalized_phone : null,
            'roles' => array_values(array_unique($roles)),
            'customer_id' => $customerId,
            'intern_user_id' => $internUserId,
            'lead' => $lead,
            'mode' => $conversation->mode,
            'assigned_user_id' => $conversation->assigned_user_id,
            'history' => $history,
            'tools' => app(AssistantToolRegistry::class)->names(),
        ];
    }

    public function promptSafe(array $context)
    {
        return [
            'conversation_id' => $context['conversation_id'],
            'contact_name' => $context['contact_name'],
            'phone' => $context['phone'],
            'roles' => $context['roles'],
            'lead' => $context['lead'],
            'mode' => $context['mode'],
            'history' => $context['history'],
            'tools' => $context['tools'],
        ];
    }
}
