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
            if (! empty($memoryParams['attendance_pending']) && isset($memoryParams['latitude'])) {
                return $this->make($memoryParams['attendance_pending'], 0.99, true, false);
            }

            return $this->make(IntentCatalog::UNKNOWN, 0.1, false, true);
        }
        if (preg_match('/\b(talk to (someone|a person|staff|human)|speak (to|with) (someone|staff|a person|human)|real person|this bot|not helping|human please|my supervisor|speak with my supervisor|don\'t understand this assignment|do not understand this assignment|upload is failing|disagree with my grade|i need help)\b/i', $t)) {
            return $this->make(IntentCatalog::HUMAN_REQUEST, 0.99, false, false);
        }
        $locationFollowUp = ($t === '' && isset($memoryParams['latitude']))
            || preg_match('/^\s*(yes|here)\b/i', $t);
        if (! empty($memoryParams['attendance_pending']) && $locationFollowUp) {
            return $this->make($memoryParams['attendance_pending'], 0.99, true, false);
        }
        if (preg_match('/\b(forgot to check out|forgot to check in|correct my attendance)\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_CORRECTION, 0.99, true, false);
        }
        if (preg_match('/\bcheck\s*-?\s*out\s+job\s+\d+\b|\bfinished job\s+\d+\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_OUT, 0.99, true, false);
        }
        if (preg_match('/\bcheck\s*-?\s*in\s+job\s+\d+\b|\barrived at job\s+\d+\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_IN, 0.99, true, false);
        }
        if (preg_match('/\b(my assignment|what job am i|where am i assigned|job status|happening with my assignment)\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_ASSIGNMENT, 0.98, true, false);
        }
        if (preg_match('/\b(my hours|how many hours)\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_HOURS, 0.98, true, false);
        }
        if (preg_match('/^(status|my status)\b|\bam i checked in\b|\bwhat time did i (check in|start)\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_STATUS, 0.99, true, false);
        }
        if (preg_match('/^(check\s*-?\s*out|checkout)\b|\bi am done for today\b|\bi\'?m leaving\b|\bi\'?ve finished\b|\bi have finished\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_OUT, 0.99, true, false);
        }
        if (preg_match('/^(check\s*-?\s*in|checkin)\b|\bi\'?m at work\b|\bi\'?ve arrived\b|\bi\'?m on site\b|\bi\'?m starting work\b|\bi have arrived\b|\bi am at work\b/i', $t)) {
            return $this->make(IntentCatalog::ATTENDANCE_IN, 0.99, true, false);
        }
        if (! empty($memoryParams['internship_pending']) && preg_match('/^\s*\d+\b|^(yes|confirm|submit)\b/i', $t)) {
            return $this->make($memoryParams['internship_pending'], 0.94, true, false);
        }
        if (preg_match('/\b(\d+\s*%|\bdiscount\b|make it cheaper|same price|give me the same)\b/i', $t)) {
            return $this->make(IntentCatalog::DISCOUNT_REQUEST, 0.97, false, false);
        }
        if (preg_match('/\b(i accept( the)? quotation|i approve the quotation)\b/i', $t)) {
            return $this->make(IntentCatalog::RENTAL_ACCEPT, 0.96, true, empty($memoryParams['quotation_id']));
        }
        if (preg_match('/\b(remove the|add another|instead of|don\'t need the|do not need the)\b/i', $t)) {
            return $this->make(IntentCatalog::RENTAL_REVISION, 0.9, true, empty($memoryParams['product']) && empty($memoryParams['event_date']));
        }
        if (preg_match('/\b(i confirm( the)? (quote|quotation|booking)|confirm the quotation|prepare the (formal )?quotation|yes,? (prepare|send) (the )?quotation)\b/i', $t)) {
            return $this->make(IntentCatalog::RENTAL_CONFIRM, 0.96, true, empty($memoryParams['product']) || ! app(\App\Services\Rental\RentalAvailabilityService::class)->resolveRange($memoryParams));
        }
        if (preg_match('/\b(quote|quotation)\b/i', $t) && preg_match('/\b(send|give|need|want|prepare|make|get|share)\b/i', $t)) {
            $ready = ! empty($memoryParams['product']);

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
        if (preg_match('/\b(handbook|task pdf|today\'?s material|where are the instructions|send the instructions)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_MATERIAL, 0.94, true, false);
        }
        if (preg_match('/\b(i want to submit|i have finished|here is my assignment|today\'?s work|here is my github|see attached|resubmit|i\'?ve corrected|here is the new file|day \d+ submission)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_SUBMIT, 0.95, true, false);
        }
        if (preg_match('/\b(what is my task|my task today|today\'?s assignment|what am i supposed to do|current task|send today\'?s work|tomorrow\'?s task|what is day \d+|internship task)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_TASK, 0.93, true, false);
        }
        if (preg_match('/\b(how far have i gone|what day am i on|tasks have i completed|tasks are left|internship progress|my internship status|been reviewed|supervisor check|submission status|did i pass|internship grade)\b/i', $t)) {
            return $this->make(IntentCatalog::INTERNSHIP_STATUS, 0.92, true, false);
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
            $availability = app(\App\Services\Rental\RentalAvailabilityService::class);
            $needs = $intent === IntentCatalog::RENTAL_ENQUIRY && (empty($memoryParams['product']) || $availability->dateIssue($memoryParams) !== 'ok');

            return $this->make($intent, 0.9, true, $needs);
        }
        if (preg_match('/^(hi|hello|hey|bonjour|good morning|good evening)[\s!.]*$/i', $t)) {
            return $this->make(IntentCatalog::GREETING, 0.97, false, false);
        }
        if (preg_match('/\b(appointment|schedule a meeting|book a meeting)\b/i', $t)) {
            return $this->make(IntentCatalog::APPOINTMENT_REQUEST, 0.8, false, true);
        }
        $availability = app(\App\Services\Rental\RentalAvailabilityService::class);
        if ($this->isRentalDateFollowUp($t, $memoryParams, $availability)) {
            $needs = empty($memoryParams['product']) || $availability->dateIssue($memoryParams) !== 'ok';

            return $this->make(IntentCatalog::RENTAL_ENQUIRY, 0.93, true, $needs);
        }

        return null;
    }

    protected function isRentalDateFollowUp($text, array $memoryParams, $availability)
    {
        $collecting = ! empty($memoryParams['product']) || ! empty($memoryParams['event_type']) || ! empty($memoryParams['event_date']);
        if (! $collecting) {
            return false;
        }
        $stripped = strtolower(preg_replace('/\b(\d{1,2})(st|nd|rd|th)\b/i', '$1', $text));
        if ($availability->isAmbiguousDate($stripped)) {
            return true;
        }

        return (bool) preg_match('/\b(\d{1,2}\s+(?:of\s+)?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+\d{1,2}|\d{1,2}[\/\-.]\d{1,2}(?:[\/\-.]\d{2,4})?|next\s+(?:saturday|sunday|monday|tuesday|wednesday|thursday|friday)|tomorrow)\b/i', $stripped);
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
