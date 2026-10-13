<?php

namespace App\Services\Voice;

use App\Contracts\Voice\VoiceProvider;
use App\Voice\VoiceCall;

/**
 * In-process call control for tests. It never opens a socket and never buys a number.
 */
class StubVoiceProvider implements VoiceProvider
{
    public $failTransfer = false;

    public $failProvider = false;

    public $transfers = [];

    public $hangups = [];

    public function name()
    {
        return 'stub';
    }

    public function transfer(VoiceCall $call, $target)
    {
        if ($this->failProvider) {
            throw new \RuntimeException('voice_provider_down');
        }
        if ($this->failTransfer || trim((string) $target) === '') {
            return ['ok' => false];
        }
        $this->transfers[] = [
            'call_id' => $call->id,
            'target' => (string) $target,
        ];

        return ['ok' => true];
    }

    public function hangup(VoiceCall $call, $reason)
    {
        if ($this->failProvider) {
            throw new \RuntimeException('voice_provider_down');
        }
        $this->hangups[] = [
            'call_id' => $call->id,
            'reason' => (string) $reason,
        ];

        return ['ok' => true];
    }
}
