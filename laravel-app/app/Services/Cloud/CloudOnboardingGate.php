<?php

namespace App\Services\Cloud;

use Illuminate\Support\Facades\Cache;

/**
 * One-time production check for company signup while the public switch stays off.
 * The token is stored as a hash. This class does not log it.
 */
class CloudOnboardingGate
{
    public function issue($minutes = 20)
    {
        $token = bin2hex(random_bytes(32));
        Cache::put($this->key($token), 1, max(1, (int) $minutes));

        return $token;
    }

    public function peek($token)
    {
        return $this->valid($token);
    }

    public function consume($token)
    {
        if (! $this->valid($token)) {
            return false;
        }
        Cache::forget($this->key($token));

        return true;
    }

    protected function valid($token)
    {
        $token = (string) $token;
        if (strlen($token) < 32) {
            return false;
        }

        return Cache::has($this->key($token));
    }

    protected function key($token)
    {
        return 'cloud-onboard-gate:'.hash('sha256', (string) $token);
    }
}
