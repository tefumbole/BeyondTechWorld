<?php

namespace App\Services\Rental;

use App\WhatsApp\RentalActivity;
use App\WhatsApp\RentalRequest;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class RentalRequestService
{
    protected $availability;
    protected $recommendations;

    public function __construct(RentalAvailabilityService $availability, RentalRecommendationService $recommendations)
    {
        $this->availability = $availability;
        $this->recommendations = $recommendations;
    }

    public function active(WhatsAppConversation $conversation)
    {
        if (! Schema::hasTable('whatsapp_rental_requests')) {
            return null;
        }

        return RentalRequest::where('conversation_id', $conversation->id)
            ->whereNotIn('status', [RentalRequest::CANCELLED, RentalRequest::ACCEPTED])
            ->orderByDesc('id')
            ->first();
    }

    public function capture(WhatsAppConversation $conversation, array $slots, $text, $intent)
    {
        if (! Schema::hasTable('whatsapp_rental_requests')) {
            return null;
        }
        $rentalTalk = $this->concernsRental($intent, $text);
        $active = $this->active($conversation);
        if (! $rentalTalk && ! $active) {
            return null;
        }
        if (! $rentalTalk && empty($slots['event_date']) && empty($slots['location']) && empty($slots['guests'])) {
            return $active;
        }
        $request = $rentalTalk ? $this->resolveTarget($conversation, $slots, $text) : $active;
        $this->fill($request, $conversation, $slots, $text);
        $issue = $this->availability->dateIssue($slots);
        if ($issue === 'ambiguous') {
            $request->event_date = null;
            $request->status = RentalRequest::COLLECTING;
            $request->save();
            $this->log($request, 'date_ambiguous', 'Asked for a specific date.');

            return $request;
        }
        if ($request->event_date && ($request->categories() !== [] || ! empty($slots['product']))) {
            $proposal = $this->recommendations->propose($request, $slots);
            $request->setLines(isset($proposal['lines']) ? $proposal['lines'] : []);
            $request->proposal_total = isset($proposal['total']) ? $proposal['total'] : null;
            $request->availability_checked_at = now();
            $request->availability_note = empty($proposal['success']) ? 'availability_not_confirmed' : 'checked';
            $request->status = empty($proposal['lines']) ? RentalRequest::CHECKED : RentalRequest::AWAITING_CUSTOMER;
            $request->save();
            $this->log($request, 'availability_check', $request->availability_note, $proposal);
        } else {
            $request->status = RentalRequest::COLLECTING;
            $request->save();
            $this->log($request, 'requirements_updated', 'Collected rental details.');
        }

        return $request->fresh();
    }

    public function markStaffReview(RentalRequest $request, $quotationId)
    {
        $request->quotation_id = $quotationId;
        $request->status = RentalRequest::AWAITING_STAFF;
        $request->save();
        $this->log($request, 'quotation_created', 'Draft quotation #'.$quotationId.' awaiting staff approval.');
    }

    public function log(RentalRequest $request, $type, $body, array $meta = [], $actorId = null)
    {
        if (! Schema::hasTable('whatsapp_rental_activities')) {
            return;
        }
        RentalActivity::create([
            'rental_request_id' => $request->id,
            'type' => $type,
            'body' => $body,
            'meta' => $meta ? json_encode($meta) : null,
            'actor_user_id' => $actorId,
        ]);
    }

    protected function resolveTarget(WhatsAppConversation $conversation, array $slots, $text)
    {
        $current = $this->active($conversation);
        $range = $this->availability->resolveRange($slots);
        if ($current && $range && $current->event_date && $current->event_date->toDateString() !== $range['start']) {
            $newEvent = isset($slots['event_type']) ? $slots['event_type'] : null;
            if (($newEvent && $newEvent !== $current->event_type) || preg_match('/\b(another|also|separate)\b/i', $text)) {
                return $this->blank($conversation);
            }
        }
        if ($current) {
            return $current;
        }

        return $this->blank($conversation);
    }

    protected function blank(WhatsAppConversation $conversation)
    {
        $contact = $conversation->contact;

        return RentalRequest::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact ? $contact->id : null,
            'status' => RentalRequest::COLLECTING,
            'created_by' => 'assistant',
        ]);
    }

    protected function fill(RentalRequest $request, WhatsAppConversation $conversation, array $slots, $text)
    {
        $contact = $conversation->contact;
        if ($contact) {
            $request->contact_id = $contact->id;
        }
        if (! empty($slots['event_type'])) {
            $request->event_type = $slots['event_type'];
        }
        if (! empty($slots['event_date'])) {
            $request->event_date_text = $slots['event_date'];
        }
        $range = $this->availability->resolveRange($slots);
        if ($range) {
            $request->event_date = $range['start'];
            $request->return_at = $range['end'].' 08:00:00';
        }
        if (! empty($slots['location'])) {
            $request->location = $slots['location'];
        }
        if (! empty($slots['guests'])) {
            $request->attendance = (int) $slots['guests'];
        }
        if (preg_match('/\boutdoor\b/i', $text)) {
            $request->indoor_outdoor = 'outdoor';
        } elseif (preg_match('/\bindoor\b/i', $text)) {
            $request->indoor_outdoor = 'indoor';
        }
        if (preg_match('/\b(deliver|delivery)\b/i', $text)) {
            $request->delivery_required = true;
        }
        if (preg_match('/\b(setup|set up)\b/i', $text)) {
            $request->setup_required = true;
        }
        if (preg_match('/\b(technician|operator)\b/i', $text)) {
            $request->technicians_required = true;
        }
        $categories = $request->categories();
        foreach (['sound', 'lighting', 'led'] as $category) {
            if (preg_match('/\b'.$category.'\b/i', $text) || (isset($slots['product']) && strpos(strtolower($slots['product']), $category) !== false)) {
                $categories[] = $category;
            }
        }
        if (preg_match('/\b(light|lights)\b/i', $text)) {
            $categories[] = 'lighting';
        }
        $request->categories_json = json_encode(array_values(array_unique($categories)));
        if (! empty($slots['lead_id'])) {
            $request->lead_id = $slots['lead_id'];
        }
        $request->save();
    }

    protected function concernsRental($intent, $text)
    {
        $intents = ['RENTAL_ENQUIRY', 'EQUIPMENT_AVAILABILITY', 'PRICE_ENQUIRY', 'RENTAL_QUOTE', 'RENTAL_CONFIRM', 'RENTAL_ACCEPT', 'RENTAL_REVISION', 'DISCOUNT_REQUEST'];
        if (in_array($intent, $intents, true)) {
            return true;
        }

        return (bool) preg_match('/\b(speaker|sound|led|light|rental|quotation|wedding|microphone)\b/i', (string) $text);
    }
}
