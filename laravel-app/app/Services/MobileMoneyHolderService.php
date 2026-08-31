<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * MoMo account-holder name lookup (Mulema pattern).
 * Campay covers MTN/Orange in Cameroon; PawaPay covers other networks when configured.
 */
class MobileMoneyHolderService
{
    /**
     * @return array{name:?string,source:?string}
     */
    public function lookup($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 8) {
            return ['name' => null, 'source' => null];
        }

        $cc = $this->guessCountry($digits);
        if ($cc === '237') {
            $hit = $this->campay($digits);
            if ($hit) {
                return $hit;
            }

            return $this->pawapay($digits) ?: ['name' => null, 'source' => null];
        }

        $hit = $this->pawapay($digits);
        if ($hit) {
            return $hit;
        }

        return $this->campay($digits) ?: ['name' => null, 'source' => null];
    }

    protected function guessCountry($digits)
    {
        if (strpos($digits, '237') === 0) {
            return '237';
        }
        if (strpos($digits, '250') === 0) {
            return '250';
        }

        return '';
    }

    protected function campay($digits)
    {
        $token = config('services.campay.token');
        if (! $token) {
            $token = getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        }
        if (! $token) {
            return null;
        }

        $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
        $url = $base.'/holder_info/?phone_number='.urlencode($digits);
        $body = $this->httpGet($url, [
            'Authorization: Token '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ], 12);
        $name = $this->extractName($body);
        if ($name) {
            return ['name' => $name, 'source' => 'campay'];
        }

        return null;
    }

    protected function pawapay($digits)
    {
        $token = config('services.pawapay.api_token') ?: getenv('PAWAPAY_API_TOKEN');
        if (! $token) {
            return null;
        }

        $env = strtolower((string) (config('services.pawapay.environment') ?: getenv('PAWAPAY_ENVIRONMENT') ?: 'production'));
        $base = ($env === 'sandbox')
            ? 'https://api.sandbox.pawapay.io'
            : (string) (config('services.pawapay.live_base_url') ?: 'https://api.pawapay.io');

        $e164 = '+'.ltrim($digits, '+');
        $body = $this->httpPost(rtrim($base, '/').'/v2/name-lookups', [
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ], json_encode(['phoneNumber' => $e164]), 12);
        $name = $this->extractName($body);
        if ($name) {
            return ['name' => $name, 'source' => 'pawapay'];
        }

        return null;
    }

    protected function extractName($decoded)
    {
        if (! is_array($decoded)) {
            return null;
        }
        $candidates = [
            $decoded['full_name'] ?? null,
            $decoded['fullName'] ?? null,
            $decoded['name'] ?? null,
            $decoded['accountHolderName'] ?? null,
            is_array($decoded['name'] ?? null) ? ($decoded['name']['fullName'] ?? null) : null,
            is_array($decoded['data'] ?? null) ? ($decoded['data']['full_name'] ?? ($decoded['data']['name'] ?? null)) : null,
        ];
        foreach ($candidates as $raw) {
            if (! is_string($raw)) {
                continue;
            }
            $name = trim($raw);
            if ($name === '' || preg_match('/^\+?\d{8,}$/', $name)) {
                continue;
            }

            return $name;
        }

        return null;
    }

    protected function httpGet($url, array $headers, $timeout)
    {
        return $this->curl('GET', $url, $headers, null, $timeout);
    }

    protected function httpPost($url, array $headers, $payload, $timeout)
    {
        return $this->curl('POST', $url, $headers, $payload, $timeout);
    }

    protected function curl($method, $url, array $headers, $payload, $timeout)
    {
        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            Log::info('MoMo holder lookup failed: '.$err);

            return null;
        }

        $decoded = json_decode((string) $response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
