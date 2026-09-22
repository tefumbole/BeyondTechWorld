<?php

namespace App\Services\Assistant;

use App\Assistant\IntentCatalog;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppSetting;

class AssistantPolicyService
{
    public function globallyEnabled()
    {
        if (! config('assistant.enabled')) {
            return false;
        }
        $saved = WhatsAppSetting::getValue('assistant_enabled', '0');

        return $saved === '1' || $saved === 'true';
    }

    public function mayProcess(WhatsAppConversation $conversation)
    {
        if (! $this->globallyEnabled()) {
            return [false, 'assistant_disabled'];
        }
        $mode = strtoupper((string) $conversation->mode);
        if ($mode === WhatsAppConversation::MODE_HUMAN) {
            return [false, 'human_mode'];
        }
        if ($mode === WhatsAppConversation::MODE_PAUSED) {
            return [false, 'paused'];
        }
        if ($mode === WhatsAppConversation::MODE_CLOSED) {
            return [false, 'closed'];
        }
        if ($mode !== WhatsAppConversation::MODE_AI) {
            return [false, 'not_ai_mode'];
        }

        return [true, null];
    }

    public function decide(array $intent, array $roles)
    {
        $name = isset($intent['intent']) ? $intent['intent'] : IntentCatalog::UNKNOWN;
        if (! IntentCatalog::isKnown($name)) {
            $name = IntentCatalog::UNKNOWN;
        }
        $confidence = isset($intent['confidence']) ? (float) $intent['confidence'] : 0.0;
        $meta = IntentCatalog::meta($name);
        $high = (float) config('assistant.confidence_high');
        $low = (float) config('assistant.confidence_low');

        if (in_array($name, [IntentCatalog::HUMAN_REQUEST, IntentCatalog::COMPLAINT], true)) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_HANDOVER, $meta, 'customer_request');
        }
        if ($name === IntentCatalog::EMPLOYEE_ENQUIRY) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_HANDOVER, $meta, 'privileged');
        }
        if ($meta['sensitivity'] === 'VERIFIED') {
            return $this->result($name, $confidence, IntentCatalog::ACTION_REFUSE, $meta, 'verification_required');
        }
        if ($meta['sensitivity'] === 'PRIVILEGED') {
            return $this->result($name, $confidence, IntentCatalog::ACTION_HANDOVER, $meta, 'privileged');
        }
        if ($confidence < $low && $name !== IntentCatalog::GREETING) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_HANDOVER, $meta, 'low_confidence');
        }
        if ($confidence < $high && ! in_array($name, [IntentCatalog::GREETING, IntentCatalog::COMPANY_INFORMATION, IntentCatalog::SERVICE_ENQUIRY], true)) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_CLARIFY, $meta, 'medium_confidence');
        }
        if ($meta['sensitivity'] === 'RECOGNIZED' && ! $this->hasRole($roles, $this->rolesFor($name))) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_HANDOVER, $meta, 'identity_required');
        }
        if (! empty($intent['needs_clarification'])) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_CLARIFY, $meta, 'missing_slots');
        }
        if ($meta['requires_erp'] || ! empty($intent['requires_erp'])) {
            return $this->result($name, $confidence, IntentCatalog::ACTION_TOOL, $meta, null);
        }

        return $this->result($name, $confidence, IntentCatalog::ACTION_ANSWER, $meta, null);
    }

    public function toolAllowed($toolName, array $toolMeta, array $roles)
    {
        $sensitivity = isset($toolMeta['sensitivity']) ? $toolMeta['sensitivity'] : 'PUBLIC';
        if ($sensitivity === 'VERIFIED' || $sensitivity === 'PRIVILEGED') {
            return [false, 'policy_blocked'];
        }
        if ($sensitivity === 'RECOGNIZED') {
            $need = isset($toolMeta['roles']) ? $toolMeta['roles'] : ['customer', 'intern'];
            if (! $this->hasRole($roles, $need)) {
                return [false, 'identity_required'];
            }
        }

        return [true, null];
    }

    protected function rolesFor($intent)
    {
        if (in_array($intent, [IntentCatalog::INTERNSHIP_TASK, IntentCatalog::INTERNSHIP_STATUS], true)) {
            return ['intern'];
        }

        return ['customer'];
    }

    protected function hasRole(array $roles, array $need)
    {
        foreach ($need as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    protected function result($intent, $confidence, $action, array $meta, $reason)
    {
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'action' => $action,
            'requires_erp' => ! empty($meta['requires_erp']),
            'sensitivity' => $meta['sensitivity'],
            'reason' => $reason,
        ];
    }
}
