<?php

namespace App\Services\Assistant\Providers;

use App\Contracts\Ai\AiProviderInterface;

class NullAiProvider implements AiProviderInterface
{
    public $fail = false;

    public $scripted = [];

    public function complete(array $messages, array $options = [])
    {
        if ($this->fail) {
            return $this->result(false, null, null, null, 'provider failure');
        }
        if ($this->scripted !== []) {
            $next = array_shift($this->scripted);
            $toolCalls = isset($next['tool_calls']) ? $next['tool_calls'] : null;
            $content = isset($next['content'])
                ? $next['content']
                : (isset($next['reply']) ? json_encode(['reply' => $next['reply'], 'handover' => ! empty($next['handover']), 'clarify' => ! empty($next['clarify']), 'tool' => isset($next['tool']) ? $next['tool'] : '', 'tool_params' => isset($next['tool_params']) ? $next['tool_params'] : []]) : json_encode($next));
            $json = is_array($next) ? $next : null;
            if (isset($next['reply']) && ! isset($next['content'])) {
                $json = [
                    'reply' => $next['reply'],
                    'handover' => ! empty($next['handover']),
                    'clarify' => ! empty($next['clarify']),
                    'tool' => isset($next['tool']) ? $next['tool'] : '',
                    'tool_params' => isset($next['tool_params']) ? $next['tool_params'] : [],
                    'call_request' => ! empty($next['call_request']),
                    'summary' => isset($next['summary']) ? $next['summary'] : null,
                    'name' => isset($next['name']) ? $next['name'] : null,
                    'organization' => isset($next['organization']) ? $next['organization'] : null,
                    'event' => isset($next['event']) ? $next['event'] : null,
                ];
            }

            return $this->result(true, $content, $json, $toolCalls, null);
        }

        return $this->result(true, '{}', [], null, null);
    }

    public function isConfigured()
    {
        return true;
    }

    public function name()
    {
        return 'null';
    }

    protected function result($ok, $content, $json, $toolCalls, $error)
    {
        return [
            'ok' => $ok,
            'content' => $content,
            'json' => $json,
            'tool_calls' => $toolCalls,
            'error' => $error,
            'provider' => 'null',
            'model' => 'null',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'latency_ms' => 1,
        ];
    }
}
