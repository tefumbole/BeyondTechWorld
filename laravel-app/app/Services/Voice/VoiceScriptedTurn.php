<?php

namespace App\Services\Voice;

use App\Voice\VoiceCall;

/**
 * Test-provider dialogue. It does not own prices, stock, or quotations.
 * Those answers come only from VoiceToolBridge results.
 * A live call will use ConversationalTurnService once a trunk is approved.
 */
class VoiceScriptedTurn
{
    protected $tools;

    public function __construct(VoiceToolBridge $tools)
    {
        $this->tools = $tools;
    }

    public function reply(VoiceCall $call, $text)
    {
        $state = $call->stateArray();
        $lower = mb_strtolower(trim((string) $text));
        $context = [
            'verified' => (bool) $call->identity_verified,
            'cloud_tenant_id' => $call->cloud_tenant_id,
            'caller_phone' => $call->caller_phone,
        ];

        if ($this->isHuman($lower)) {
            return ['action' => 'transfer', 'speech' => ''];
        }
        if ($this->isCallback($lower)) {
            return ['action' => 'callback', 'speech' => '', 'who' => $this->who($text)];
        }
        if ($this->isPayment($lower)) {
            return ['action' => 'payment_refused', 'speech' => ''];
        }
        if ($this->isHangup($lower)) {
            return ['action' => 'hangup', 'speech' => ''];
        }
        if (! empty($state['awaiting_message'])) {
            return ['action' => 'message', 'speech' => '', 'message' => trim((string) $text)];
        }
        if ($this->isSensitive($lower)) {
            $ran = $this->tools->run('get_customer_payment_summary', [], $context);
            $this->rememberTool($call, $ran);

            return ['action' => null, 'speech' => 'not_authorized'];
        }
        if ($this->isPrice($lower)) {
            $ran = $this->tools->run('search_rental_products', ['query' => $text], $context);
            $this->rememberTool($call, $ran);

            return ['action' => null, 'speech' => $this->priceSpeech($ran)];
        }
        if ($this->isAvailability($lower)) {
            $ran = $this->tools->run('check_rental_availability', [
                'query' => isset($state['product']) ? $state['product'] : $text,
                'event_date' => isset($state['event_date']) ? $state['event_date'] : '',
            ], $context);
            $this->rememberTool($call, $ran);

            return ['action' => null, 'speech' => $this->availabilitySpeech($ran)];
        }
        if ($this->isProduct($lower)) {
            $ran = $this->tools->run('search_rental_products', ['query' => $text], $context);
            $this->rememberTool($call, $ran);
            if (! empty($ran['result']['name'])) {
                $state['product'] = $ran['result']['name'];
                $call->state = json_encode($state);
                $call->save();

                return ['action' => null, 'speech' => 'product:'.$ran['result']['name']];
            }

            return ['action' => null, 'speech' => 'no_product'];
        }
        if ($this->isQuote($lower) && ! empty($state['event_type'])) {
            $ran = $this->tools->run('create_event_quotation_draft', [
                'event_type' => $state['event_type'],
                'event_date' => isset($state['event_date']) ? $state['event_date'] : '',
                'venue' => isset($state['venue']) ? $state['venue'] : '',
            ], $context);
            $this->rememberTool($call, $ran);
            if (empty($ran['allowed'])) {
                return ['action' => null, 'speech' => 'not_authorized'];
            }

            return ['action' => null, 'speech' => 'quote_draft'];
        }
        if ($this->isCompany($lower)) {
            $ran = $this->tools->run('get_company_information', [], $context);
            $this->rememberTool($call, $ran);
            if (! empty($ran['result']['text'])) {
                return ['action' => null, 'speech' => 'fact:'.$ran['result']['text']];
            }

            return ['action' => null, 'speech' => 'no_price'];
        }
        if (preg_match('/my name is\s+([a-z][a-z \'-]{1,40})/i', $text, $match)) {
            $state['caller_name'] = trim($match[1]);
            $call->caller_name = $state['caller_name'];
            $call->state = json_encode($state);
            $call->save();

            return ['action' => null, 'speech' => 'named:'.$state['caller_name']];
        }
        if ($this->isEventStart($lower) && empty($state['event_type'])) {
            $state['event_type'] = (strpos($lower, 'wedding') !== false) ? 'wedding' : 'event';
            $call->state = json_encode($state);
            $call->save();

            return ['action' => null, 'speech' => 'ask_date:'.$state['event_type']];
        }
        if (! empty($state['event_type']) && empty($state['event_date'])) {
            $state['event_date'] = trim((string) $text);
            $call->state = json_encode($state);
            $call->save();

            return ['action' => null, 'speech' => 'ask_venue'];
        }
        if (! empty($state['event_date']) && empty($state['venue'])) {
            $state['venue'] = trim((string) $text);
            $call->state = json_encode($state);
            $call->save();
            $ran = $this->tools->run('build_event_solution', [
                'event_type' => $state['event_type'],
                'event_date' => $state['event_date'],
                'venue' => $state['venue'],
            ], $context);
            $this->rememberTool($call, $ran);

            return [
                'action' => null,
                'speech' => 'event:'.$state['event_type'].'|'.$state['event_date'].'|'.$state['venue'],
            ];
        }

        return ['action' => null, 'speech' => 'clarify'];
    }

    protected function rememberTool(VoiceCall $call, array $ran)
    {
        $state = $call->stateArray();
        if (! isset($state['tools']) || ! is_array($state['tools'])) {
            $state['tools'] = [];
        }
        $state['tools'][] = [
            'name' => isset($ran['name']) ? $ran['name'] : '',
            'allowed' => ! empty($ran['allowed']),
        ];
        $call->state = json_encode($state);
        $call->save();
    }

    protected function priceSpeech(array $ran)
    {
        if (empty($ran['allowed'])) {
            return 'not_authorized';
        }
        if (is_array($ran['result']) && isset($ran['result']['price']) && $ran['result']['price'] !== '') {
            return 'price:'.$ran['result']['price'];
        }

        return 'no_price';
    }

    protected function availabilitySpeech(array $ran)
    {
        if (empty($ran['allowed'])) {
            return 'not_authorized';
        }
        if (is_array($ran['result']) && array_key_exists('available', $ran['result'])) {
            return ! empty($ran['result']['available']) ? 'available_yes' : 'available_no';
        }

        return 'no_product';
    }

    protected function isHuman($lower)
    {
        return (bool) preg_match('/human|someone|transfer|speak with|talk to/', $lower);
    }

    protected function isCallback($lower)
    {
        return (bool) preg_match('/call me|callback|call back/', $lower);
    }

    protected function isPayment($lower)
    {
        return (bool) preg_match('/\bi paid\b|payment succeeded|mark .*paid|payment is done/', $lower);
    }

    protected function isHangup($lower)
    {
        return (bool) preg_match("/that's all|that is all|goodbye|hang up|good bye/", $lower);
    }

    protected function isSensitive($lower)
    {
        return (bool) preg_match('/payment summary|bank detail|reset my password|account ownership/', $lower);
    }

    protected function isPrice($lower)
    {
        return (bool) preg_match('/how much|price|cost/', $lower);
    }

    protected function isAvailability($lower)
    {
        return (bool) preg_match('/available|availability|free on/', $lower);
    }

    protected function isProduct($lower)
    {
        return (bool) preg_match('/\bjbl\b|do you have|speaker/', $lower) && ! $this->isEventStart($lower);
    }

    protected function isQuote($lower)
    {
        return (bool) preg_match('/quotation|quote/', $lower);
    }

    protected function isCompany($lower)
    {
        return (bool) preg_match('/address|where are you|company information/', $lower);
    }

    protected function isEventStart($lower)
    {
        return (bool) preg_match('/wedding|sound for|event/', $lower);
    }

    protected function who($text)
    {
        if (preg_match_all('/\b([A-Z][a-z]{2,20})\b/', (string) $text, $matches)) {
            foreach ($matches[1] as $word) {
                if (! in_array($word, ['Ask', 'Please', 'Call', 'Let'], true)) {
                    return $word;
                }
            }
        }

        return 'the team';
    }
}
