<?php

namespace App\Contracts\Voice;

use App\Voice\VoiceCall;

/**
 * Call control the gateway is allowed to invoke.
 * The model does not receive this object and cannot invent operations.
 */
interface VoiceProvider
{
    public function name();

    /**
     * @return array{ok:bool}
     */
    public function transfer(VoiceCall $call, $target);

    /**
     * @return array{ok:bool}
     */
    public function hangup(VoiceCall $call, $reason);
}
