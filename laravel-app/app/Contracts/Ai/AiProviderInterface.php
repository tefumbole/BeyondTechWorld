<?php

namespace App\Contracts\Ai;

interface AiProviderInterface
{
    /**
     * @param array $messages [['role'=>'system|user|assistant','content'=>string], ...]
     * @return array{ok:bool,content:?string,json:?array,error:?string,provider:string,model:?string,input_tokens:?int,output_tokens:?int}
     */
    public function complete(array $messages, array $options = []);

    public function isConfigured();

    public function name();
}
