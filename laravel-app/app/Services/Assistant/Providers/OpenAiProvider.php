<?php

namespace App\Services\Assistant\Providers;

use App\Contracts\Ai\AiProviderInterface;

class OpenAiProvider implements AiProviderInterface
{
    public function complete(array $messages, array $options = [])
    {
        $started = microtime(true);
        if (! $this->isConfigured()) {
            return $this->fail('AI provider is not configured.', $started);
        }

        $payload = [
            'model' => isset($options['model']) ? $options['model'] : config('assistant.model'),
            'messages' => $messages,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : config('assistant.temperature'),
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : config('assistant.max_output_tokens'),
        ];

        $useTools = ! empty($options['tools']) && is_array($options['tools']);
        if ($useTools) {
            $payload['tools'] = $options['tools'];
            $payload['tool_choice'] = isset($options['tool_choice']) ? $options['tool_choice'] : 'auto';
        } elseif (! empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init((string) config('assistant.api_url'));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) config('assistant.timeout'),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer '.config('assistant.api_key'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return $this->fail($err !== '' ? $err : 'AI request failed.', $started);
        }
        $decoded = json_decode($raw, true);
        if ($http >= 400) {
            $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'AI HTTP '.$http;

            return $this->fail($msg, $started);
        }

        $message = isset($decoded['choices'][0]['message']) ? $decoded['choices'][0]['message'] : [];
        $content = isset($message['content']) ? (string) $message['content'] : '';
        $toolCalls = [];
        if (! empty($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $call) {
                $fn = isset($call['function']) ? $call['function'] : [];
                $args = [];
                if (! empty($fn['arguments'])) {
                    $parsed = json_decode($fn['arguments'], true);
                    $args = is_array($parsed) ? $parsed : [];
                }
                $toolCalls[] = [
                    'id' => isset($call['id']) ? $call['id'] : uniqid('call_', true),
                    'name' => isset($fn['name']) ? (string) $fn['name'] : '',
                    'arguments' => $args,
                ];
            }
        }
        $json = $content !== '' ? json_decode($content, true) : null;

        return [
            'ok' => true,
            'content' => $content !== '' ? $content : null,
            'json' => is_array($json) ? $json : null,
            'tool_calls' => $toolCalls !== [] ? $toolCalls : null,
            'error' => null,
            'provider' => 'openai',
            'model' => isset($decoded['model']) ? $decoded['model'] : config('assistant.model'),
            'input_tokens' => isset($decoded['usage']['prompt_tokens']) ? (int) $decoded['usage']['prompt_tokens'] : null,
            'output_tokens' => isset($decoded['usage']['completion_tokens']) ? (int) $decoded['usage']['completion_tokens'] : null,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    public function isConfigured()
    {
        return trim((string) config('assistant.api_key')) !== '';
    }

    public function name()
    {
        return 'openai';
    }

    protected function fail($error, $started = null)
    {
        return [
            'ok' => false,
            'content' => null,
            'json' => null,
            'tool_calls' => null,
            'error' => $error,
            'provider' => 'openai',
            'model' => config('assistant.model'),
            'input_tokens' => null,
            'output_tokens' => null,
            'latency_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : null,
        ];
    }
}
