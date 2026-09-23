<?php

namespace App\Services\Assistant;

use App\Assistant\IntentCatalog;
use App\Contracts\Ai\AiProviderInterface;

class AssistantIntentRouter
{
    protected $provider;

    public function __construct(AiProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function classify($text, array $context, array $memoryParams = [])
    {
        $deterministic = $this->deterministic($text, $memoryParams);
        if ($deterministic && $deterministic['confidence'] >= (float) config('assistant.confidence_high')) {
            return $deterministic;
        }
        if ($this->provider->isConfigured()) {
            $llm = $this->fromModel($text, $context, $memoryParams);
            if ($llm) {
                return $llm;
            }
        }

        return $deterministic ?: $this->make(IntentCatalog::UNKNOWN, 0.2, false, true);
    }

    public function deterministic($text, array $memoryParams = [])
    {
        $t = strtolower(trim(preg_replace('/\s+/', ' ', (string) $text)));
        if ($t === '') {
            return $this->make(IntentCatalog::UNKNOWN, 0.1, false, true);
        }
        if (preg_match('/\b(talk to (someone|a person|staff|human)|speak (to|with) (someone|staff|a person|human)|real person|this bot|not helping|human please)\b/i', $t)) {
            return $this->make(IntentCatalog::HUMAN_REQUEST, 0.99, false, false);
        }
        if (preg_match('/\b(i confirm( the)? (quote|quotation|booking)|confirm the quotation|yes,? book)\b/i', $t)) {
            return $this->make(IntentCatalog::RENTAL_CONFIRM, 0.96, true, empty($memoryParams['quotation_id']));
        }
        if (preg_match('/\b(send (me )?(a )?(quote|quotation)|give me a (quote|quotation)|i need a quote|quotation for)\b/i', $t)) {
            $ready = ! empty($memoryParams['product']) && app(\App\Services\Rental\RentalAvailabilityService::class)->resolveRange($memoryParams);

            return $this->make(IntentCatalog::RENTAL_QUOTE, 0.95, true, ! $ready);
        }
        if (preg_match('/\b(complaint|terrible|worst|angry|fraud)\b/i', $t)) {
            return $this->make(IntentCatalog::COMPLAINT, 0.86, false, false);
        }
        if (preg_match('/\b(i (have )?paid|payment (is )?confirmed|confirm my payment)\b/i', $t)) {
            return $this->make(IntentCatalog::PAYMENT_ENQUIRY, 0.9, true, false);
        }
        if (preg_match('/\b(how much do i owe|my balance|what do i owe)\b/i', $t)) {
            return $this->make(IntentCatalog::BALANCE_ENQUIRY, 0.92, true, false);
        }
        if (preg_match('/\b(your staff told me|they said (it )?cost|the speakers cost)\b/i', $t)) {
            return $this->make(IntentCatalog::PRICE_ENQUIRY, 0.88, true, false);
        }
        if (preg_match('/\b(current task|my task|internship task)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_TASK, 0.93, true, false);
        }
        if (preg_match('/\b(internship progress|my internship status)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_STATUS, 0.9, true, false);
        }
        if (preg_match('/\b(booking status|status of my booking|my booking)\b/i', $t)) {
            return $this->make(IntentCatalog::BOOKING_STATUS, 0.9, true, false);
        }
        if (preg_match('/\b(what services|services do you|what do you offer)\b/i', $t)) {
            return $this->make(IntentCatalog::SERVICE_ENQUIRY, 0.94, false, false);
        }
        if (preg_match('/\b(who are you|about (the )?company|beyondtechworld|beyond enterprise)\b/i', $t)) {
            return $this->make(IntentCatalog::COMPANY_INFORMATION, 0.9, false, false);
        }
        if (preg_match('/\b(jbl|speaker|speakers|sound|led|lighting|microphone)\b/i', $t) && preg_match('/\b(have|available|need|want|rent|price|cost)\b/i', $t)) {
            $intent = preg_match('/\b(jbl|have you|do you have|available)\b/i', $t)
                ? IntentCatalog::EQUIPMENT_AVAILABILITY
                : IntentCatalog::RENTAL_ENQUIRY;
            $dated = app(\App\Services\Rental\RentalAvailabilityService::class)->resolveRange($memoryParams);
            $needs = $intent === IntentCatalog::RENTAL_ENQUIRY && (empty($memoryParams['product']) || ! $dated);

            return $this->make($intent, 0.9, true, $needs);
        }
        if (preg_match('/^(hi|hello|hey|bonjour|good morning|good evening)[\s!.]*$/i', $t)) {
            return $this->make(IntentCatalog::GREETING, 0.97, false, false);
        }
        if (preg_match('/\b(appointment|schedule a meeting|book a meeting)\b/i', $t)) {
            return $this->make(IntentCatalog::APPOINTMENT_REQUEST, 0.8, false, true);
        }

        return null;
    }

    protected function fromModel($text, array $context, array $memoryParams)
    {
        $result = $this->provider->complete([
            ['role' => 'system', 'content' => 'Classify a WhatsApp message for BeyondTechWorld. Return JSON only: {"intent":"ENUM","confidence":0.0,"requires_erp":false,"needs_clarification":false,"slots":{}}. Intent must be one of: '.implode(',', array_keys(IntentCatalog::all())).'. Never invent ERP facts.'],
            ['role' => 'user', 'content' => json_encode(['text' => $text, 'memory' => $memoryParams, 'roles' => isset($context['roles']) ? $context['roles'] : []])],
        ]);
        if (empty($result['ok']) || empty($result['json']['intent'])) {
            return null;
        }
        $intent = strtoupper((string) $result['json']['intent']);
        if (! IntentCatalog::isKnown($intent)) {
            $intent = IntentCatalog::UNKNOWN;
        }

        return $this->make(
            $intent,
            isset($result['json']['confidence']) ? (float) $result['json']['confidence'] : 0.5,
            ! empty($result['json']['requires_erp']),
            ! empty($result['json']['needs_clarification']),
            isset($result['json']['slots']) && is_array($result['json']['slots']) ? $result['json']['slots'] : []
        );
    }

    protected function make($intent, $confidence, $requiresErp, $needsClarification, array $slots = [])
    {
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'requires_erp' => $requiresErp,
            'needs_clarification' => $needsClarification,
            'slots' => $slots,
        ];
    }
}
