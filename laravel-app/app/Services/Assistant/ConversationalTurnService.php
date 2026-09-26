<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantKnowledge;
use App\Assistant\AssistantMemory;
use App\Contracts\Ai\AiProviderInterface;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Primary OpenAI conversational engine for Beyond Assistant (WhatsApp + Website).
 */
class ConversationalTurnService
{
    protected $provider;
    protected $tools;
    protected $registry;
    protected $selector;
    protected $prompts;

    public function __construct(
        AiProviderInterface $provider,
        AssistantToolExecutor $tools,
        AssistantToolRegistry $registry,
        AssistantToolSelector $selector,
        BeyondAssistantSystemPromptBuilder $prompts
    ) {
        $this->provider = $provider;
        $this->tools = $tools;
        $this->registry = $registry;
        $this->selector = $selector;
        $this->prompts = $prompts;
    }

    public function turn(WhatsAppConversation $conversation, array $context, AssistantMemory $memory, array $slots, $incoming)
    {
        if (! $this->provider->isConfigured()) {
            return $this->providerFailure('ai_provider_unavailable');
        }

        $roles = isset($context['roles']) ? $context['roles'] : [];
        $memParams = $memory->parameters();
        $openAiTools = $this->selector->openAiTools($roles, $memParams);
        $messages = $this->buildMessages($conversation, $context, $memory, $incoming);
        $maxIter = (int) config('assistant.max_tool_iterations', 4);
        $toolsRun = [];
        $lastToolResult = null;
        $facts = [];

        for ($i = 0; $i < $maxIter; $i++) {
            $result = $this->provider->complete($messages, [
                'tools' => $openAiTools,
                'tool_choice' => 'auto',
                'json' => false,
            ]);
            $this->recordUsage($result);

            if (empty($result['ok'])) {
                return $this->providerFailure(isset($result['error']) ? $result['error'] : 'ai_provider_failed');
            }

            // Native tool_calls path
            if (! empty($result['tool_calls']) && is_array($result['tool_calls'])) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => isset($result['content']) ? $result['content'] : null,
                    'tool_calls' => $this->rawToolCallsForReplay($result['tool_calls']),
                ];
                foreach ($result['tool_calls'] as $call) {
                    $name = isset($call['name']) ? (string) $call['name'] : '';
                    $args = isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : [];
                    $exec = $this->runTool($name, $args, $slots, $context);
                    $toolsRun[] = $name;
                    $lastToolResult = $exec['result'];
                    $facts = array_merge($facts, $exec['facts']);
                    if (! empty($exec['handover'])) {
                        return [
                            'handled' => true,
                            'reply' => $exec['reply'] !== '' ? $exec['reply'] : 'I am connecting you with a team member now.',
                            'handover' => true,
                            'call_request' => false,
                            'reason' => $exec['reason'],
                            'memory' => $facts,
                            'tool' => $name,
                            'tool_result' => $lastToolResult,
                            'tools_run' => $toolsRun,
                            'path' => 'TOOL_ASSISTED',
                        ];
                    }
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => isset($call['id']) ? $call['id'] : ('call_'.$name),
                        'content' => json_encode($this->publicResult($lastToolResult)),
                    ];
                }
                continue;
            }

            // Legacy / NullAi JSON content path (tests + providers without tool_calls)
            $json = isset($result['json']) ? $result['json'] : $this->decodeContent($result['content']);
            if (is_array($json) && (! empty($json['tool']) || ! empty($json['reply']) || ! empty($json['handover']))) {
                $facts = array_merge($facts, $this->facts($json));
                if (! empty($json['handover']) || ! empty($json['call_request'])) {
                    return [
                        'handled' => true,
                        'reply' => $this->safeReply(isset($json['reply']) ? $json['reply'] : '', ! empty($json['call_request'])),
                        'handover' => true,
                        'call_request' => ! empty($json['call_request']),
                        'reason' => ! empty($json['call_request']) ? 'call_request' : (isset($json['reason_category']) ? $json['reason_category'] : 'USER_REQUESTED_HUMAN'),
                        'memory' => $facts,
                        'tool' => null,
                        'tool_result' => null,
                        'tools_run' => $toolsRun,
                        'path' => 'HANDOVER',
                    ];
                }
                $tool = isset($json['tool']) ? (string) $json['tool'] : '';
                if ($tool !== '') {
                    $params = isset($json['tool_params']) && is_array($json['tool_params']) ? $json['tool_params'] : [];
                    $exec = $this->runTool($tool, $params, $slots, $context);
                    $toolsRun[] = $tool;
                    $lastToolResult = $exec['result'];
                    $facts = array_merge($facts, $exec['facts']);
                    if (! empty($exec['handover'])) {
                        return [
                            'handled' => true,
                            'reply' => $exec['reply'] !== '' ? $exec['reply'] : 'I am connecting you with a team member now.',
                            'handover' => true,
                            'call_request' => false,
                            'reason' => $exec['reason'],
                            'memory' => $facts,
                            'tool' => $tool,
                            'tool_result' => $lastToolResult,
                            'tools_run' => $toolsRun,
                            'path' => 'HANDOVER',
                        ];
                    }
                    $messages[] = ['role' => 'assistant', 'content' => json_encode($json)];
                    $messages[] = ['role' => 'user', 'content' => 'Tool result for '.$tool.': '.json_encode($this->publicResult($lastToolResult)).'. Reply to the customer naturally as JSON {"reply":""}.'];
                    $phrase = $this->provider->complete($messages, ['json' => true]);
                    $this->recordUsage($phrase);
                    $phrased = isset($phrase['json']) ? $phrase['json'] : $this->decodeContent(isset($phrase['content']) ? $phrase['content'] : null);
                    $reply = $phrased && ! empty($phrased['reply']) ? trim((string) $phrased['reply']) : $this->phraseTool($lastToolResult);
                    if ($reply === '') {
                        $reply = $this->phraseTool($lastToolResult);
                    }

                    return [
                        'handled' => true,
                        'reply' => $reply,
                        'handover' => false,
                        'call_request' => false,
                        'reason' => null,
                        'memory' => $facts,
                        'tool' => $tool,
                        'tool_result' => $lastToolResult,
                        'tools_run' => $toolsRun,
                        'clarify' => ! empty($json['clarify']),
                        'path' => 'TOOL_ASSISTED',
                    ];
                }
                $reply = isset($json['reply']) ? trim((string) $json['reply']) : '';
                if ($reply !== '') {
                    return [
                        'handled' => true,
                        'reply' => $reply,
                        'handover' => false,
                        'call_request' => false,
                        'reason' => null,
                        'memory' => $facts,
                        'tool' => null,
                        'tool_result' => $lastToolResult,
                        'tools_run' => $toolsRun,
                        'clarify' => ! empty($json['clarify']),
                        'path' => ! empty($json['clarify']) ? 'CLARIFIED' : 'DIRECT_AI',
                    ];
                }
            }

            $plain = isset($result['content']) ? trim((string) $result['content']) : '';
            if ($plain !== '' && $plain !== '{}') {
                return [
                    'handled' => true,
                    'reply' => $plain,
                    'handover' => false,
                    'call_request' => false,
                    'reason' => null,
                    'memory' => $facts,
                    'tool' => null,
                    'tool_result' => $lastToolResult,
                    'tools_run' => $toolsRun,
                    'path' => 'DIRECT_AI',
                ];
            }

            break;
        }

        // Soft clarify — NOT a human handover
        return [
            'handled' => true,
            'reply' => 'Could you tell me a bit more about what you need — equipment rental, training/internship, or something else?',
            'handover' => false,
            'call_request' => false,
            'reason' => null,
            'memory' => $facts,
            'tool' => null,
            'tool_result' => $lastToolResult,
            'tools_run' => $toolsRun,
            'clarify' => true,
            'path' => 'CLARIFIED',
        ];
    }

    protected function buildMessages(WhatsAppConversation $conversation, array $context, AssistantMemory $memory, $incoming)
    {
        $system = $this->prompts->build($context, $memory->parameters());
        $knowledge = $this->knowledgeText();
        if ($knowledge !== '') {
            $system .= "\nApproved company knowledge excerpts:\n".$knowledge;
        }
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($this->history($conversation) as $row) {
            $messages[] = $row;
        }
        $messages[] = [
            'role' => 'user',
            'content' => json_encode([
                'memory' => $memory->parameters(),
                'incoming' => $incoming,
                'known_name' => isset($context['contact_name']) ? $context['contact_name'] : null,
                'channel' => method_exists($conversation, 'isWebsite') && $conversation->isWebsite() ? 'website' : 'whatsapp',
            ]),
        ];

        return $messages;
    }

    protected function runTool($name, array $args, array $slots, array $context)
    {
        $name = trim((string) $name);
        $facts = [];
        if ($name === '' || ! $this->registry->get($name)) {
            return [
                'result' => ['success' => false, 'error' => 'unknown_tool'],
                'facts' => $facts,
                'handover' => false,
                'reply' => '',
                'reason' => null,
            ];
        }
        unset($args['customer_id'], $args['user_id'], $args['otp_code']);
        $params = array_merge($slots, $args);
        $result = $this->tools->execute($name, $params, $context);
        if ($name === 'request_human_handover' && ! empty($result['handed_over'])) {
            return [
                'result' => $result,
                'facts' => ['handover_state' => 'HUMAN', 'handover_reason' => isset($result['reason_category']) ? $result['reason_category'] : 'USER_REQUESTED_HUMAN'],
                'handover' => true,
                'reply' => 'I am connecting you with a team member who can help further.',
                'reason' => isset($result['reason_category']) ? $result['reason_category'] : 'USER_REQUESTED_HUMAN',
            ];
        }

        return [
            'result' => $result,
            'facts' => $facts,
            'handover' => false,
            'reply' => '',
            'reason' => null,
        ];
    }

    protected function providerFailure($reason)
    {
        return [
            'handled' => true,
            'reply' => "I am having trouble reaching my AI service right now. I've passed this to our team and someone will assist you. Your message was saved.",
            'handover' => true,
            'call_request' => false,
            'reason' => 'TOOL_FAILURE',
            'memory' => [],
            'tool' => null,
            'tool_result' => null,
            'tools_run' => [],
            'path' => 'HANDOVER',
            'provider_error' => $reason,
        ];
    }

    protected function rawToolCallsForReplay(array $calls)
    {
        $out = [];
        foreach ($calls as $call) {
            $out[] = [
                'id' => isset($call['id']) ? $call['id'] : uniqid('call_', true),
                'type' => 'function',
                'function' => [
                    'name' => isset($call['name']) ? $call['name'] : '',
                    'arguments' => json_encode(isset($call['arguments']) ? $call['arguments'] : []),
                ],
            ];
        }

        return $out;
    }

    protected function decodeContent($content)
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function facts(array $json)
    {
        $out = [];
        foreach (['name', 'organization', 'summary', 'event', 'conversation_goal', 'event_type', 'event_date', 'location', 'guest_count', 'equipment'] as $key) {
            if (! empty($json[$key])) {
                $map = $key === 'event' ? 'event_type' : $key;
                $out[$map] = is_scalar($json[$key]) ? $json[$key] : json_encode($json[$key]);
            }
        }

        return $out;
    }

    protected function safeReply($reply, $callRequest)
    {
        $reply = trim((string) $reply);
        if ($reply !== '') {
            return $reply;
        }

        return $callRequest
            ? 'I will open a call request for our team.'
            : 'I am connecting you with a team member now.';
    }

    protected function publicResult($toolResult)
    {
        if (! is_array($toolResult)) {
            return ['success' => false];
        }
        $copy = $toolResult;
        unset($copy['otp_code'], $copy['sql'], $copy['raw']);

        return $copy;
    }

    protected function phraseTool($toolResult)
    {
        if (! is_array($toolResult)) {
            return 'I could not retrieve that information right now.';
        }
        if (! empty($toolResult['message'])) {
            return (string) $toolResult['message'];
        }
        if (! empty($toolResult['success'])) {
            return 'I found the details in our system. How would you like to proceed?';
        }

        return 'I could not complete that lookup. Could you rephrase what you need?';
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
            if (! $row->body) {
                continue;
            }
            $role = $row->direction === WhatsAppMessage::DIR_IN ? 'user' : 'assistant';
            $out[] = ['role' => $role, 'content' => (string) $row->body];
        }

        return $out;
    }

    protected function recordUsage(array $result)
    {
        $key = 'assistant_openai_last_'.(! empty($result['ok']) ? 'ok' : 'fail');
        Cache::put($key, [
            'at' => now()->toDateTimeString(),
            'model' => isset($result['model']) ? $result['model'] : null,
            'latency_ms' => isset($result['latency_ms']) ? $result['latency_ms'] : null,
            'error' => isset($result['error']) ? mb_substr((string) $result['error'], 0, 200) : null,
            'input_tokens' => isset($result['input_tokens']) ? $result['input_tokens'] : null,
            'output_tokens' => isset($result['output_tokens']) ? $result['output_tokens'] : null,
        ], now()->addDays(7));
        if (! empty($result['ok']) && isset($result['latency_ms'])) {
            $samples = Cache::get('assistant_openai_latency_samples', []);
            $samples[] = (int) $result['latency_ms'];
            $samples = array_slice($samples, -20);
            Cache::put('assistant_openai_latency_samples', $samples, now()->addDays(7));
        }
    }
}
