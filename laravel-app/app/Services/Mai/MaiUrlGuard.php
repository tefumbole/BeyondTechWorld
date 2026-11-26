<?php

namespace App\Services\Mai;

class MaiUrlGuard
{
    public function allows($url)
    {
        $parts = parse_url((string) $url);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $httpOk = (bool) config('cloud.mai_customer.webhook_allow_http');
        if ($scheme !== 'https' && ! ($httpOk && $scheme === 'http')) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($this->blockedHost($host)) {
            return false;
        }
        $addresses = $this->addresses($host);
        if ($addresses === []) {
            return false;
        }
        foreach ($addresses as $ip) {
            if (! $this->publicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    protected function blockedHost($host)
    {
        if ($host === 'localhost' || $host === 'metadata.google.internal') {
            return true;
        }
        $blocked = ['.local', '.internal', '.localhost', '.localdomain'];
        foreach ($blocked as $suffix) {
            $length = strlen($suffix);
            if (substr($host, -$length) === $suffix) {
                return true;
            }
        }

        return false;
    }

    protected function addresses($host)
    {
        $resolver = config('cloud.mai_customer.webhook_resolver');
        if (is_callable($resolver)) {
            $found = call_user_func($resolver, $host);

            return is_array($found) ? $found : [];
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $found = @gethostbynamel($host);

        return is_array($found) ? $found : [];
    }

    protected function publicIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
