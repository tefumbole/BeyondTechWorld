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
        $employeeId = null;
        $tenancyIds = [];
        if ($contact) {
            foreach ($contact->links as $link) {
                $roles[] = $link->role;
                if ($link->role === 'customer') {
                    $customerId = (int) $link->linkable_id;
                }
                if ($link->role === 'intern') {
                    $internUserId = (int) $link->linkable_id;
                }
                if ($link->role === 'employee') {
                    $employeeId = (int) $link->linkable_id;
                }
                if ($link->role === 'tenant') {
                    $tenancyIds[] = (int) $link->linkable_id;
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
        $history = $this->lines($conversation);

        return [
            'conversation' => $conversation,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => app(AssistantContactVoice::class)->preferredName($contact),
            'contact_voice' => app(AssistantContactVoice::class)->line($contact),
            'phone' => $contact ? $contact->normalized_phone : null,
            'roles' => array_values(array_unique($roles)),
            'customer_id' => $customerId,
            'intern_user_id' => $internUserId,
            'employee_id' => $employeeId,
            'tenancy_id' => count($tenancyIds) === 1 ? $tenancyIds[0] : null,
            'tenancy_ids' => $tenancyIds,
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
            'contact_voice' => isset($context['contact_voice']) ? $context['contact_voice'] : '',
            'phone' => $context['phone'],
            'roles' => $context['roles'],
            'lead' => $context['lead'],
            'mode' => $context['mode'],
            'history' => $context['history'],
            'tools' => $context['tools'],
        ];
    }

    /**
     * Messages already in this person's chats, including turns from before AI was switched on.
     *
     * @return array<int, array{direction:string,sender:?string,speaker:string,body:string,role:string}>
     */
    public function lines(WhatsAppConversation $conversation)
    {
        $limit = max(40, (int) AssistantRuntimeSettings::historyLimit());
        $query = WhatsAppMessage::query()->orderByDesc('id');
        if ($conversation->contact_id) {
            $ids = WhatsAppConversation::where('contact_id', $conversation->contact_id)->pluck('id');
            $query->whereIn('conversation_id', $ids->isEmpty() ? [$conversation->id] : $ids->all());
        } else {
            $query->where('conversation_id', $conversation->id);
        }
        $rows = $query->limit($limit)->get()->reverse();
        $out = [];
        foreach ($rows as $row) {
            $body = trim((string) $row->body);
            if ($body === '') {
                continue;
            }
            if (mb_strlen($body) > 700) {
                $body = rtrim(mb_substr($body, 0, 699)).'…';
            }
            $speaker = $this->speaker($row);
            $out[] = [
                'direction' => (string) $row->direction,
                'sender' => $row->sender_type,
                'speaker' => $speaker,
                'body' => $body,
                'role' => $speaker === 'Customer' ? 'user' : 'assistant',
            ];
        }

        return $out;
    }

    public function transcript(array $history)
    {
        $lines = [];
        foreach ($history as $row) {
            if (! is_array($row) || trim((string) (isset($row['body']) ? $row['body'] : '')) === '') {
                continue;
            }
            $who = isset($row['speaker']) ? $row['speaker'] : 'Customer';
            $lines[] = $who.': '.$row['body'];
        }
        $text = implode("\n", $lines);
        if (mb_strlen($text) <= 12000) {
            return $text;
        }

        return mb_substr($text, -12000);
    }

    protected function speaker(WhatsAppMessage $row)
    {
        if ($row->direction === WhatsAppMessage::DIR_IN || $row->sender_type === 'CONTACT') {
            return 'Customer';
        }
        if ($row->sender_type === 'ASSISTANT') {
            return 'Assistant';
        }

        return 'Staff';
    }
}
