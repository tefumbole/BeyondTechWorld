<?php

namespace App\Services\Assistant;

use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class AssistantLeadCapture
{
    public function apply(WhatsAppConversation $conversation, array $slots, $text)
    {
        if (! Schema::hasTable('leads')) {
            return null;
        }
        $name = isset($slots['captured_name']) ? trim((string) $slots['captured_name']) : '';
        $organization = isset($slots['organization']) ? trim((string) $slots['organization']) : '';
        $contact = $conversation->contact;
        if ($name !== '' && $contact) {
            $contact->wa_name = $name;
            $contact->save();
        }
        if ($name === '' && $organization === '' && empty($slots['event_type']) && empty($slots['product'])) {
            return null;
        }
        $lead = Lead::where('contact_id', $conversation->contact_id)
            ->whereNotIn('status', LeadCatalog::closedStatuses())
            ->orderByDesc('id')
            ->first();
        if (! $lead) {
            $phone = $contact ? $contact->normalized_phone : '';
            if ($phone === '') {
                return null;
            }
            $lead = Lead::create([
                'contact_id' => $conversation->contact_id,
                'conversation_id' => $conversation->id,
                'normalized_phone' => $phone,
                'source' => LeadCatalog::SOURCE_WHATSAPP,
                'category' => LeadCatalog::CAT_GENERAL,
                'status' => LeadCatalog::STATUS_NEW,
                'priority' => 'NORMAL',
                'first_enquiry' => mb_substr((string) $text, 0, 500),
                'last_activity_at' => now(),
            ]);
        }
        if ($name !== '' && ($lead->name === null || $lead->name === '')) {
            $lead->name = $name;
        }
        if ($organization !== '') {
            $lead->company = $organization;
        }
        $lead->latest_enquiry = mb_substr((string) $text, 0, 500);
        $lead->last_activity_at = now();
        $lead->save();

        return $lead;
    }
}
