<?php

namespace App\Contracts\Ai;

interface AiProviderInterface
{
    /**
     * @param array $messages [['role'=>'system|user|assistant|tool','content'=>string, ...], ...]
     * @param array $options tools?, tool_choice?, json?, model?, temperature?, max_tokens?
     * @return array{
     *   ok:bool,
     *   content:?string,
     *   json:?array,
     *   tool_calls:?array,
     *   error:?string,
     *   provider:string,
     *   model:?string,
     *   input_tokens:?int,
     *   output_tokens:?int,
     *   latency_ms:?int
     * }
     */
    public function complete(array $messages, array $options = []);

    public function isConfigured();

    public function name();
}
