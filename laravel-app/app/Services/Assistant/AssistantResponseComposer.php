<?php

namespace App\Services\Assistant;

use App\Assistant\IntentCatalog;
use App\Contracts\Ai\AiProviderInterface;

class AssistantResponseComposer
{
    protected $provider;

    public function __construct(AiProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function compose($intent, $action, array $toolResult, array $context, $incoming, array $memoryParams = [])
    {
        if ($action === IntentCatalog::ACTION_HANDOVER) {
            return 'A BeyondTechWorld team member will continue this conversation with you shortly.';
        }
        if ($action === IntentCatalog::ACTION_REFUSE) {
            return $this->refuse($intent);
        }
        if ($action === IntentCatalog::ACTION_CLARIFY) {
            return $this->clarify($intent, $memoryParams);
        }
        if ($intent === IntentCatalog::GREETING) {
            $name = config('assistant.identify') ? config('assistant.display_name') : 'BeyondTechWorld';

            return 'Hello, this is '.$name.'. How can we help you today?';
        }
        if (is_array($toolResult) && empty($toolResult['success']) && isset($toolResult['error'])) {
            if ($toolResult['error'] === 'not_found') {
                return 'I could not find that in our records. I can connect you with a team member if you would like.';
            }
            if ($toolResult['error'] === 'verification_required' || $toolResult['error'] === 'policy_blocked') {
                return $this->refuse($intent);
            }
        }
        $fromTool = $this->fromTool($intent, $toolResult, $memoryParams);
        if ($fromTool !== null) {
            return $fromTool;
        }

        return $this->fromModel($intent, $toolResult, $context, $incoming);
    }

    public function draft($incoming, array $context, array $toolResult = [])
    {
        $base = $this->compose(IntentCatalog::GENERAL_ENQUIRY, IntentCatalog::ACTION_ANSWER, $toolResult, $context, $incoming);
        if ($this->provider->isConfigured()) {
            $result = $this->provider->complete([
                ['role' => 'system', 'content' => 'Draft a short WhatsApp reply for BeyondTechWorld staff. JSON {"reply":"..."} only. Do not invent ERP facts. Do not send as the assistant.'],
                ['role' => 'user', 'content' => json_encode(['incoming' => $incoming, 'context' => app(AssistantContextBuilder::class)->promptSafe($context), 'tool' => $toolResult])],
            ]);
            if (! empty($result['ok']) && ! empty($result['json']['reply'])) {
                return trim((string) $result['json']['reply']);
            }
        }

        return $base;
    }

    protected function refuse($intent)
    {
        if (in_array($intent, [IntentCatalog::BALANCE_ENQUIRY, IntentCatalog::PAYMENT_ENQUIRY, IntentCatalog::RECEIPT_REQUEST], true)) {
            return 'I cannot confirm payments or balances from a WhatsApp message. A team member will verify this in our records and assist you.';
        }

        return 'That information needs a staff member. I am connecting you with the team.';
    }

    protected function clarify($intent, array $params)
    {
        if (in_array($intent, [IntentCatalog::RENTAL_ENQUIRY, IntentCatalog::EQUIPMENT_AVAILABILITY, IntentCatalog::PRICE_ENQUIRY], true)) {
            if (empty($params['event_type'])) {
                return 'What type of event is this for?';
            }
            if (empty($params['event_date'])) {
                return 'On which date do you need the equipment?';
            }
            if (empty($params['guests'])) {
                return 'Approximately how many guests are you expecting?';
            }
            if (empty($params['location'])) {
                return 'Where will the event take place?';
            }
        }
        if ($intent === IntentCatalog::BOOKING_STATUS) {
            return 'I found more than one booking. Please send the booking reference you want me to check.';
        }

        return 'Could you share a bit more detail so I can help accurately?';
    }

    protected function fromTool($intent, $toolResult, array $params)
    {
        if (! is_array($toolResult) || empty($toolResult['success'])) {
            return null;
        }
        if ($intent === IntentCatalog::COMPANY_INFORMATION || $intent === IntentCatalog::SERVICE_ENQUIRY || $intent === IntentCatalog::GENERAL_ENQUIRY) {
            $entries = isset($toolResult['entries']) ? $toolResult['entries'] : [];
            if ($entries === []) {
                return 'BeyondTechWorld offers event production, equipment rental, IT services, training and internships. How can we help?';
            }
            $bits = [];
            foreach (array_slice($entries, 0, 3) as $entry) {
                $bits[] = $entry['content'];
            }

            return implode("\n\n", $bits);
        }
        if (in_array($intent, [IntentCatalog::EQUIPMENT_AVAILABILITY, IntentCatalog::RENTAL_ENQUIRY, IntentCatalog::PRICE_ENQUIRY], true)) {
            $products = isset($toolResult['products']) ? $toolResult['products'] : [];
            if (isset($toolResult['name'])) {
                $products = [$toolResult];
            }
            if ($products === []) {
                return 'I could not find that item in our rental catalogue. A team member can confirm options for your event.';
            }
            $lines = ['Here is what I found in the catalogue. This is not a confirmed booking or date check:'];
            foreach (array_slice($products, 0, 5) as $p) {
                $line = '- '.$p['name'];
                if (! empty($p['listed_day_rate'])) {
                    $line .= ' (listed daily rate '.$p['listed_day_rate'].')';
                }
                $lines[] = $line;
            }

            return implode("\n", $lines);
        }
        if ($intent === IntentCatalog::BOOKING_STATUS) {
            if (! empty($toolResult['ambiguous'])) {
                return 'I found more than one booking. Please send the reference you want checked.';
            }
            $booking = isset($toolResult['booking']) ? $toolResult['booking'] : null;
            if ($booking) {
                return 'Booking '.$booking['reference'].' is currently '.$booking['status'].'.';
            }
        }
        if ($intent === IntentCatalog::INTERNSHIP_TASK && ! empty($toolResult['title'])) {
            return 'Your current internship task is "'.$toolResult['title'].'" ('.$toolResult['status'].').';
        }
        if ($intent === IntentCatalog::INTERNSHIP_STATUS && isset($toolResult['completed'])) {
            return 'Internship status: '.$toolResult['status'].'. Completed tasks: '.$toolResult['completed'].' of '.$toolResult['released'].'.';
        }

        return null;
    }

    protected function fromModel($intent, $toolResult, array $context, $incoming)
    {
        if (! $this->provider->isConfigured()) {
            return 'Thank you. A BeyondTechWorld team member can help with the next step if you need more detail.';
        }
        $result = $this->provider->complete([
            ['role' => 'system', 'content' => 'You are Beyond Assistant for BeyondTechWorld. Reply in JSON {"reply":"..."}. Short WhatsApp style. Never invent prices, availability, balances, payments, grades or booking facts. If a tool result is missing, say you could not find it. Do not mention being an AI model.'],
            ['role' => 'user', 'content' => json_encode([
                'intent' => $intent,
                'incoming' => $incoming,
                'tool' => $toolResult,
                'context' => app(AssistantContextBuilder::class)->promptSafe($context),
            ])],
        ]);
        if (! empty($result['ok']) && ! empty($result['json']['reply'])) {
            return trim((string) $result['json']['reply']);
        }

        return 'Thank you. I can connect you with a team member if you would like further help.';
    }
}
