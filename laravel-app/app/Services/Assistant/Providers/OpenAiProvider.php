<?php

namespace App\Services\Assistant\Providers;

use App\Contracts\Ai\AiProviderInterface;

class OpenAiProvider implements AiProviderInterface
{
    public function complete(array $messages, array $options = [])
    {
        if (! $this->isConfigured()) {
            return $this->fail('AI provider is not configured.');
        }
        $payload = [
            'model' => isset($options['model']) ? $options['model'] : config('assistant.model'),
            'messages' => $messages,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : config('assistant.temperature'),
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : config('assistant.max_output_tokens'),
            'response_format' => ['type' => 'json_object'],
        ];
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
            return $this->fail($err !== '' ? $err : 'AI request failed.');
        }
        $decoded = json_decode($raw, true);
        if ($http >= 400) {
            $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'AI HTTP '.$http;

            return $this->fail($msg);
        }
        $content = isset($decoded['choices'][0]['message']['content']) ? $decoded['choices'][0]['message']['content'] : '';
        $json = json_decode($content, true);

        return [
            'ok' => true,
            'content' => $content,
            'json' => is_array($json) ? $json : null,
            'error' => null,
            'provider' => 'openai',
            'model' => isset($decoded['model']) ? $decoded['model'] : config('assistant.model'),
            'input_tokens' => isset($decoded['usage']['prompt_tokens']) ? (int) $decoded['usage']['prompt_tokens'] : null,
            'output_tokens' => isset($decoded['usage']['completion_tokens']) ? (int) $decoded['usage']['completion_tokens'] : null,
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

    protected function fail($error)
    {
        return [
            'ok' => false,
            'content' => null,
            'json' => null,
            'error' => $error,
            'provider' => 'openai',
            'model' => config('assistant.model'),
            'input_tokens' => null,
            'output_tokens' => null,
        ];
    }
}
