<?php

namespace App\Services\WhatsApp;

use App\Customer;
use App\Support\WhatsAppPhone;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadActivity;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use Illuminate\Support\Facades\Schema;

class WhatsAppLeadService
{
    protected $classifier;
    protected $identity;

    public function __construct(WhatsAppLeadClassifier $classifier, WhatsAppIdentityService $identity)
    {
        $this->classifier = $classifier;
        $this->identity = $identity;
    }

    public function considerIncoming(WhatsAppContact $contact, WhatsAppConversation $conversation, WhatsAppMessage $message)
    {
        $contact->load('links');
        if ($contact->links && $contact->links->count() > 0) {
            return null;
        }
        $body = (string) $message->body;
        if (! $this->classifier->isMeaningful($body)) {
            return null;
        }

        $lead = $this->activeLead($contact);
        $category = $this->classifier->category($body);
        if ($lead) {
            $lead->latest_enquiry = mb_substr($body, 0, 500);
            $lead->conversation_id = $conversation->id;
            $lead->last_activity_at = now();
            if (! $lead->category && $category) {
                $lead->category = $category;
            }
            $lead->save();
            $this->activity($lead, LeadActivity::ENQUIRY, $body, null, $message->id);

            return $lead;
        }

        $lead = Lead::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'name' => $contact->displayName(),
            'normalized_phone' => $contact->normalized_phone,
            'source' => LeadCatalog::SOURCE_WHATSAPP,
            'category' => $category,
            'summary' => mb_substr($body, 0, 500),
            'status' => LeadCatalog::STATUS_NEW,
            'priority' => LeadCatalog::PRIORITY_NORMAL,
            'first_enquiry' => mb_substr($body, 0, 500),
            'latest_enquiry' => mb_substr($body, 0, 500),
            'last_activity_at' => now(),
        ]);
        $this->activity($lead, LeadActivity::ENQUIRY, $body, null, $message->id);
        if ($category) {
            $this->activity($lead, LeadActivity::CLASSIFIED, $lead->categoryLabel());
        }
        $this->conversationEvent($conversation->id, WhatsAppConversationEvent::LEAD_CREATED, 'Lead created', null);

        return $lead;
    }

    public function createManual(WhatsAppContact $contact, WhatsAppConversation $conversation, $userId, array $attrs = [])
    {
        $lead = $this->activeLead($contact);
        if ($lead) {
            return $lead;
        }
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'name' => isset($attrs['name']) ? $attrs['name'] : $contact->displayName(),
            'normalized_phone' => $contact->normalized_phone,
            'company' => isset($attrs['company']) ? $attrs['company'] : null,
            'source' => LeadCatalog::SOURCE_WHATSAPP,
            'category' => isset($attrs['category']) ? $attrs['category'] : LeadCatalog::CAT_GENERAL,
            'summary' => isset($attrs['summary']) ? $attrs['summary'] : $conversation->last_message,
            'status' => LeadCatalog::STATUS_NEW,
            'priority' => isset($attrs['priority']) ? $attrs['priority'] : LeadCatalog::PRIORITY_NORMAL,
            'assigned_user_id' => $userId,
            'first_enquiry' => $conversation->last_message,
            'latest_enquiry' => $conversation->last_message,
            'last_activity_at' => now(),
        ]);
        $this->activity($lead, LeadActivity::ENQUIRY, 'Manual lead created', $userId);
        $this->conversationEvent($conversation->id, WhatsAppConversationEvent::LEAD_CREATED, 'Lead created manually', $userId);

        return $lead;
    }

    public function activeLead(WhatsAppContact $contact)
    {
        return Lead::where('contact_id', $contact->id)
            ->whereNotIn('status', LeadCatalog::closedStatuses())
            ->orderByDesc('id')
            ->first();
    }

    public function assign(Lead $lead, $userId, $actorId = null)
    {
        $lead->assigned_user_id = $userId ?: null;
        $lead->last_activity_at = now();
        $lead->save();
        $this->activity($lead, LeadActivity::ASSIGNED, $userId ? 'Assigned to staff #'.$userId : 'Unassigned', $actorId);

        return $lead;
    }

    public function changeStatus(Lead $lead, $status, $actorId = null, $lostReason = null)
    {
        $status = strtoupper((string) $status);
        if (! array_key_exists($status, LeadCatalog::statuses())) {
            return $lead;
        }
        if ($status === LeadCatalog::STATUS_CONVERTED) {
            return $lead;
        }
        $from = $lead->status;
        $lead->status = $status;
        if ($status === LeadCatalog::STATUS_LOST) {
            $lead->lost_reason = $lostReason;
        }
        $lead->last_activity_at = now();
        $lead->save();
        $this->activity($lead, LeadActivity::STATUS, $from.' → '.$status, $actorId);

        return $lead;
    }

    public function setFollowUp(Lead $lead, $when, $note, $userId, $actorId = null)
    {
        $lead->follow_up_at = $when;
        if ($userId) {
            $lead->assigned_user_id = $userId;
        }
        if ($lead->status === LeadCatalog::STATUS_NEW) {
            $lead->status = LeadCatalog::STATUS_FOLLOW_UP;
        }
        $lead->last_activity_at = now();
        $lead->save();
        $this->activity($lead, LeadActivity::FOLLOW_UP, trim((string) $note) !== '' ? $note : 'Follow-up set', $actorId);

        return $lead;
    }

    public function addNote(Lead $lead, $body, $authorId, $conversationId = null)
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        $note = WhatsAppNote::create([
            'lead_id' => $lead->id,
            'conversation_id' => $conversationId,
            'author_id' => $authorId,
            'body' => $body,
        ]);
        $this->activity($lead, LeadActivity::NOTE, $body, $authorId);
        $lead->last_activity_at = now();
        $lead->save();

        return $note;
    }

    public function findExistingCustomer($phone)
    {
        $normalized = $this->identity->normalize($phone);
        if ($normalized === '' || ! Schema::hasTable('customers')) {
            return null;
        }
        $variants = array_unique(array_filter([
            $normalized,
            ltrim($normalized, '0'),
            WhatsAppPhone::display($normalized),
        ]));

        return Customer::query()
            ->where(function ($q) {
                if (Schema::hasColumn('customers', 'is_active')) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                }
            })
            ->whereIn('phone_number', $variants)
            ->orderBy('id')
            ->first();
    }

    public function convert(Lead $lead, $actorId, $createIfMissing = true)
    {
        $existing = $this->findExistingCustomer($lead->normalized_phone);
        if ($existing) {
            $lead->converted_customer_id = $existing->id;
            $lead->converted_at = now();
            $lead->status = LeadCatalog::STATUS_CONVERTED;
            $lead->last_activity_at = now();
            $lead->save();
            $this->activity($lead, LeadActivity::CUSTOMER_LINKED, 'Linked existing customer #'.$existing->id, $actorId);
            $this->activity($lead, LeadActivity::CONVERTED, 'Converted to existing customer', $actorId);
            if ($lead->conversation_id) {
                $this->conversationEvent($lead->conversation_id, WhatsAppConversationEvent::CONVERTED, 'Lead converted', $actorId);
            }

            return ['lead' => $lead, 'customer' => $existing, 'created' => false];
        }
        if (! $createIfMissing) {
            return ['lead' => $lead, 'customer' => null, 'created' => false];
        }

        $payload = [
            'name' => $lead->name ?: $lead->normalized_phone,
            'phone_number' => $lead->normalized_phone,
            'is_active' => true,
        ];
        if (Schema::hasColumn('customers', 'company_name')) {
            $payload['company_name'] = $lead->company;
        }
        if (Schema::hasColumn('customers', 'address')) {
            $payload['address'] = 'NAN';
        }
        if (Schema::hasColumn('customers', 'city')) {
            $payload['city'] = 'NAN';
        }
        if (Schema::hasColumn('customers', 'customer_group_id')) {
            $payload['customer_group_id'] = 1;
        }
        $customer = Customer::create($payload);
        $lead->converted_customer_id = $customer->id;
        $lead->converted_at = now();
        $lead->status = LeadCatalog::STATUS_CONVERTED;
        $lead->last_activity_at = now();
        $lead->save();
        $this->activity($lead, LeadActivity::CUSTOMER_CREATED, 'Customer #'.$customer->id.' created', $actorId);
        $this->activity($lead, LeadActivity::CONVERTED, 'Converted to new customer', $actorId);
        if ($lead->conversation_id) {
            $this->conversationEvent($lead->conversation_id, WhatsAppConversationEvent::CONVERTED, 'Lead converted', $actorId);
        }

        return ['lead' => $lead, 'customer' => $customer, 'created' => true];
    }

    public function activity(Lead $lead, $type, $body, $actorId = null, $messageId = null, array $meta = [])
    {
        return LeadActivity::create([
            'lead_id' => $lead->id,
            'type' => $type,
            'body' => $body,
            'actor_user_id' => $actorId,
            'message_id' => $messageId,
            'meta' => $meta ? json_encode($meta) : null,
        ]);
    }

    public function conversationEvent($conversationId, $type, $body, $actorId = null)
    {
        if (! $conversationId) {
            return null;
        }

        return WhatsAppConversationEvent::create([
            'conversation_id' => $conversationId,
            'type' => $type,
            'body' => $body,
            'actor_user_id' => $actorId,
        ]);
    }
}
