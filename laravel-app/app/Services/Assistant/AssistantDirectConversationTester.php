<?php

namespace App\Services\Assistant;

use App\Contracts\Ai\AiProviderInterface;
use App\Services\Assistant\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Admin/dev diagnostic: production system prompt + OpenAI, no WhatsApp/website routing.
 */
class AssistantDirectConversationTester
{
    public function run($message, array $options = [])
    {
        $started = microtime(true);
        $offerTools = ! empty($options['with_tools']);
        $model = AssistantAiConfig::model();
        $toolChoice = 'auto';
        $configured = AssistantAiConfig::isConfigured();
        $providerBound = app(AiProviderInterface::class);
        $providerName = method_exists($providerBound, 'name') ? $providerBound->name() : get_class($providerBound);

        if (! $configured) {
            return $this->payload([
                'http_success' => false,
                'response_source' => 'FALLBACK_ERROR',
                'model' => $model,
                'tool_choice' => $toolChoice,
                'tool_requested' => null,
                'tools_offered' => [],
                'final_text' => '',
                'error' => 'openai_api_key_missing',
                'provider_bound' => $providerName,
                'openai_key_configured' => false,
            ], $started);
        }

        $provider = new OpenAiProvider();
        $system = app(BeyondAssistantSystemPromptBuilder::class)->build([
            'channel' => 'diagnostic',
            'roles' => [],
        ], []);
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string) $message],
        ];

        $tools = [];
        if ($offerTools) {
            $tools = app(AssistantToolSelector::class)->openAiTools([], []);
        }

        $result = $provider->complete($messages, [
            'tools' => $tools,
            'tool_choice' => $toolChoice,
            'json' => false,
            'max_tokens' => max(256, (int) config('assistant.max_output_tokens', 400)),
        ]);

        $toolRequested = null;
        if (! empty($result['tool_calls'][0]['name'])) {
            $toolRequested = $result['tool_calls'][0]['name'];
        }

        $text = isset($result['content']) ? trim((string) $result['content']) : '';
        $ok = ! empty($result['ok']);
        $source = 'FALLBACK_ERROR';
        if ($ok && $toolRequested) {
            $source = 'OPENAI_TOOL_ASSISTED';
        } elseif ($ok && $text !== '') {
            $source = 'OPENAI_DIRECT';
        }

        $payload = $this->payload([
            'http_success' => $ok,
            'response_source' => $source,
            'model' => isset($result['model']) ? $result['model'] : $model,
            'tool_choice' => $toolChoice,
            'tool_requested' => $toolRequested,
            'tools_offered' => array_values(array_filter(array_map(function ($t) {
                return isset($t['function']['name']) ? $t['function']['name'] : null;
            }, $tools))),
            'final_text' => $text,
            'error' => $ok ? null : (isset($result['error']) ? $result['error'] : 'openai_failed'),
            'provider_bound' => $providerName,
            'openai_key_configured' => true,
            'openai_latency_ms' => isset($result['latency_ms']) ? $result['latency_ms'] : null,
        ], $started);

        Cache::put('assistant_direct_test_last', [
            'at' => now()->toDateTimeString(),
            'input' => mb_substr((string) $message, 0, 200),
            'response_source' => $payload['response_source'],
            'model' => $payload['model'],
            'tool_requested' => $payload['tool_requested'],
            'http_success' => $payload['http_success'],
            'error' => $payload['error'],
        ], now()->addDays(7));

        return $payload;
    }

    public function battery(array $questions = null)
    {
        $defaults = [
            'How are you today?',
            'What is a line array?',
            'What is the difference between a line array and a point source speaker?',
            'What is a VLAN?',
            'Why are subwoofers used at concerts?',
        ];
        $questions = $questions ?: $defaults;
        $rows = [];
        foreach ($questions as $q) {
            $rows[] = array_merge(['input' => $q], $this->run($q, ['with_tools' => true]));
        }

        return $rows;
    }

    protected function payload(array $data, $started)
    {
        $data['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

        return $data;
    }
}
