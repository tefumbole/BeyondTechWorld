<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantKnowledge;
use App\Assistant\AssistantMemory;
use App\Contracts\Ai\AiProviderInterface;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;

class ConversationalTurnService
{
    protected $provider;
    protected $tools;
    protected $registry;

    public function __construct(AiProviderInterface $provider, AssistantToolExecutor $tools, AssistantToolRegistry $registry)
    {
        $this->provider = $provider;
        $this->tools = $tools;
        $this->registry = $registry;
    }

    public function turn(WhatsAppConversation $conversation, array $context, AssistantMemory $memory, array $slots, $incoming)
    {
        if (! $this->provider->isConfigured()) {
            return $this->fallback('ai_provider_unavailable');
        }
        $first = $this->provider->complete($this->messages($conversation, $context, $memory, $incoming), ['json' => true]);
        if (empty($first['ok'])) {
            return $this->fallback('ai_provider_failed');
        }
        $json = $this->decode($first);
        if ($json === null || (empty($json['reply']) && empty($json['tool']))) {
            return ['handled' => false];
        }
        $facts = $this->facts($json);
        if (! empty($json['summary'])) {
            $facts['summary'] = mb_substr((string) $json['summary'], 0, 400);
        }
        if (! empty($json['call_request']) || ! empty($json['handover'])) {
            return [
                'handled' => true,
                'reply' => $this->safeReply(isset($json['reply']) ? $json['reply'] : '', ! empty($json['call_request'])),
                'handover' => true,
                'call_request' => ! empty($json['call_request']),
                'reason' => ! empty($json['call_request']) ? 'call_request' : 'customer_request',
                'memory' => $facts,
                'tool' => null,
                'tool_result' => null,
            ];
        }
        $tool = isset($json['tool']) ? (string) $json['tool'] : '';
        if ($tool !== '' && ! $this->toolAllowed($tool)) {
            return [
                'handled' => true,
                'reply' => 'I can talk that through, but that action needs a person on our team.',
                'handover' => true,
                'call_request' => false,
                'reason' => 'privileged',
                'memory' => $facts,
                'tool' => $tool,
                'tool_result' => ['success' => false, 'error' => 'privileged'],
            ];
        }
        $toolResult = null;
        $reply = isset($json['reply']) ? trim((string) $json['reply']) : '';
        if ($tool !== '') {
            $params = isset($json['tool_params']) && is_array($json['tool_params']) ? $json['tool_params'] : [];
            unset($params['customer_id'], $params['user_id'], $params['otp_code']);
            $params = array_merge($slots, $params);
            $toolResult = $this->tools->execute($tool, $params, $context);
            $second = $this->provider->complete($this->phraseMessages($incoming, $tool, $toolResult), ['json' => true]);
            if (empty($second['ok'])) {
                return $this->fallback('ai_provider_failed');
            }
            $phrased = $this->decode($second);
            $reply = $phrased && ! empty($phrased['reply']) ? trim((string) $phrased['reply']) : $this->phraseTool($toolResult);
            if ($reply === '') {
                $reply = $this->phraseTool($toolResult);
            }
        }
        if ($reply === '') {
            return ['handled' => false];
        }

        return [
            'handled' => true,
            'reply' => $reply,
            'handover' => false,
            'call_request' => false,
            'reason' => null,
            'memory' => $facts,
            'tool' => $tool !== '' ? $tool : null,
            'tool_result' => $toolResult,
            'clarify' => ! empty($json['clarify']),
        ];
    }

    protected function fallback($reason)
    {
        return [
            'handled' => true,
            'reply' => "Thanks for your message. I've passed this to our team and someone will assist you.",
            'handover' => true,
            'call_request' => false,
            'reason' => $reason,
            'memory' => [],
            'tool' => null,
            'tool_result' => null,
        ];
    }

    protected function messages(WhatsAppConversation $conversation, array $context, AssistantMemory $memory, $incoming)
    {
        $tools = implode(', ', $this->allowedNames());
        $knowledge = $this->knowledgeText();
        $history = $this->history($conversation);
        $system = 'You are '.config('assistant.display_name').', the BeyondTechWorld WhatsApp assistant. '
            .'Speak naturally. Do not invent prices, stock, availability, balances, or documents. '
            .'For equipment, prices, or availability set tool to one of: '.$tools.'. '
            .'Return JSON only: {"reply":"","tool":"","tool_params":{},"summary":"","name":"","organization":"","event":"","clarify":false,"handover":false,"call_request":false}. '
            .'Use handover only for a complaint, a discount, or an explicit request for a person. Use call_request only when they ask to be called. '
            .'Never include reasoning. Recommendations may name only products a tool returns.';

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode([
                'knowledge' => $knowledge,
                'memory' => $memory->parameters(),
                'history' => $history,
                'incoming' => $incoming,
                'known_name' => isset($context['contact_name']) ? $context['contact_name'] : null,
            ])],
        ];
    }

    protected function phraseMessages($incoming, $tool, $toolResult)
    {
        $safe = $this->publicResult($toolResult);

        return [
            ['role' => 'system', 'content' => 'Phrase this ERP tool result in plain WhatsApp language. Do not add prices or products that are not in the result. Return JSON {"reply":""}.'],
            ['role' => 'user', 'content' => json_encode([
                'incoming' => $incoming,
                'tool' => $tool,
                'result' => $safe,
            ])],
        ];
    }

    protected function allowedNames()
    {
        return [
            'get_company_information',
            'get_services',
            'search_rental_products',
            'check_rental_availability',
            'get_rental_product_information',
            'get_customer_quotations',
            'get_customer_quotation_details',
            'get_quotation_status',
        ];
    }

    protected function toolAllowed($name)
    {
        if (! in_array($name, $this->allowedNames(), true)) {
            return false;
        }
        $meta = $this->registry->get($name);

        return $meta && empty($meta['write']);
    }

    protected function knowledgeText()
    {
        if (! Schema::hasTable('assistant_knowledge')) {
            return '';
        }
        $bits = [];
        foreach (AssistantKnowledge::where('enabled', true)->orderBy('id')->limit(12)->get() as $row) {
            $bits[] = $row->title.': '.$row->content;
        }

        return implode("\n", $bits);
    }

    protected function history(WhatsAppConversation $conversation)
    {
        $rows = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(AssistantRuntimeSettings::historyLimit())
            ->get()
            ->reverse();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'direction' => $row->direction,
                'body' => mb_substr((string) $row->body, 0, 240),
            ];
        }

        return $out;
    }

    protected function decode(array $result)
    {
        if (isset($result['json']) && is_array($result['json']) && $result['json'] !== []) {
            return $result['json'];
        }
        $content = isset($result['content']) ? trim((string) $result['content']) : '';
        if ($content === '' || $content === '{}') {
            return null;
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function facts(array $json)
    {
        $out = [];
        foreach (['name', 'organization', 'event', 'dates', 'place', 'attendance', 'equipment', 'delivery', 'technician', 'quotation_id'] as $key) {
            if (! empty($json[$key]) && ! is_array($json[$key])) {
                $out[$key === 'name' ? 'captured_name' : $key] = mb_substr((string) $json[$key], 0, 120);
            }
        }

        return $out;
    }

    protected function safeReply($reply, $call)
    {
        $reply = trim((string) $reply);
        if ($call) {
            return "I've asked our team to call you. They have not called yet.";
        }
        if ($reply === '') {
            return 'A BeyondTechWorld team member will continue this conversation with you shortly.';
        }

        return $reply;
    }

    protected function publicResult($toolResult)
    {
        if (! is_array($toolResult)) {
            return [];
        }
        unset($toolResult['otp_code'], $toolResult['otp_ttl'], $toolResult['document_path']);

        return $toolResult;
    }

    protected function phraseTool($toolResult)
    {
        if (! is_array($toolResult) || empty($toolResult['success'])) {
            return 'I could not confirm that from our records. A team member can check it.';
        }
        if (! empty($toolResult['message'])) {
            return (string) $toolResult['message'];
        }
        $products = isset($toolResult['products']) ? $toolResult['products'] : [];
        if (isset($toolResult['name'])) {
            $products = [$toolResult];
        }
        if ($products === []) {
            return 'I checked our records. Tell me a bit more about what you need.';
        }
        $lines = [];
        foreach (array_slice($products, 0, 5) as $product) {
            $name = isset($product['name']) ? $product['name'] : 'Item';
            $price = isset($product['listed_day_rate']) ? $product['listed_day_rate'] : (isset($product['unit_price']) ? $product['unit_price'] : null);
            $lines[] = $price !== null ? $name.' at '.$price : $name;
        }

        return 'From the catalogue: '.implode('; ', $lines).'. This is not a confirmed booking.';
    }
}
