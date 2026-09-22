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
            return $this->result(false, null, null, 'provider failure');
        }
        if ($this->scripted !== []) {
            $next = array_shift($this->scripted);

            return $this->result(true, isset($next['content']) ? $next['content'] : json_encode($next), is_array($next) ? $next : null, null);
        }

        return $this->result(true, '{}', [], null);
    }

    public function isConfigured()
    {
        return true;
    }

    public function name()
    {
        return 'null';
    }

    protected function result($ok, $content, $json, $error)
    {
        return [
            'ok' => $ok,
            'content' => $content,
            'json' => $json,
            'error' => $error,
            'provider' => 'null',
            'model' => 'null',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ];
    }
}
