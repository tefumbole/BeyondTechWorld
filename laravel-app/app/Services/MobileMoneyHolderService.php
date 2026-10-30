<?php

namespace App\Services;

use App\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MoMo account-holder name lookup.
 * Campay covers MTN/Orange in Cameroon; PawaPay covers other networks when a merchant token is set.
 */
class MobileMoneyHolderService
{
    /** Short-circuit all Campay calls until this many minutes after a 403 lockout. */
    const CAMPAY_LOCKOUT_MINUTES = 30;

    /** Cache a "not found / unknown" Campay answer for this many minutes so repeat lookups do not count as failed attempts. */
    const CAMPAY_NOT_FOUND_MINUTES = 360;

    /**
     * @param  string|null  $phone
     * @param  bool  $includeLocal  Pass true on admin / signed-in callers to fall back to the local
     *                              customers table when the MoMo APIs are silent. Leave false on
     *                              public endpoints so customer names are not leaked to strangers.
     * @return array{name:?string,address:?string,source:?string}
     */
    public function lookup($phone, $includeLocal = false)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 8) {
            return ['name' => null, 'address' => null, 'source' => null];
        }

        $hit = $this->campay($digits);
        if ($hit) {
            return $hit;
        }

        $pawa = $this->pawapay($digits);
        if ($pawa) {
            return $pawa;
        }

        // Final fallback: when the MoMo APIs do not answer (lockout, timeout, unknown number),
        // show the name we already have in the local customers table so the form is still useful.
        if ($includeLocal) {
            $local = $this->localDirectory($digits);
            if ($local) {
                return $local;
            }
        }

        return ['name' => null, 'address' => null, 'source' => null];
    }

    /**
     * Look up a customer by phone with the same loose match rules as PeopleDirectoryService.
     * Keeps the service self-contained (no cross-service call) and tolerates partial numbers.
     */
    protected function localDirectory($digits)
    {
        if (strlen($digits) < 8) {
            return null;
        }
        try {
            $plus = '+'.$digits;
            $tail = substr($digits, -9);
            $customer = Customer::query()
                ->where(function ($q) use ($digits, $plus) {
                    $q->where('phone_number', $digits)
                      ->orWhere('phone_number', $plus);
                })
                ->first();
            if (! $customer && strlen($tail) >= 8) {
                try {
                    $customer = Customer::query()
                        ->whereRaw(
                            "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone_number,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                            [$tail]
                        )
                        ->orderByDesc('is_active')
                        ->orderByDesc('id')
                        ->first();
                } catch (\Throwable $e) {
                    // Non-MySQL backend or missing column: fall through.
                }
            }
            if (! $customer) {
                return null;
            }
            $name = trim((string) $customer->name);
            if ($name === '' || preg_match('/^\+?\d{8,}$/', $name)) {
                return null;
            }
            $address = trim((string) $customer->address);
            if ($address !== '' && (strtoupper($address) === 'N/A' || strtoupper($address) === 'NAN' || preg_match('/^\+?\d{8,}$/', $address))) {
                $address = '';
            }

            return [
                'name' => $name,
                'address' => $address !== '' ? $address : null,
                'source' => 'local',
            ];
        } catch (\Throwable $e) {
            Log::info('MoMo local-directory fallback failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Keep bad numbers from ever reaching Campay. Cameroon MTN/Orange are 9 digits starting with 6
     * (prefixes 65x, 67x, 68x, 69x). Other country codes are passed through to PawaPay.
     */
    protected function isCampayCandidate($digits)
    {
        $local = $digits;
        if (strpos($local, '237') === 0 && strlen($local) > 9) {
            $local = substr($local, 3);
        }
        if (strlen($local) !== 9 || $local[0] !== '6') {
            return false;
        }
        $three = substr($local, 0, 3);
        $two = substr($local, 0, 2);
        $mtn = $two === '67' || ($three >= '650' && $three <= '654') || ($three >= '680' && $three <= '683');
        $orange = $two === '69' || ($three >= '655' && $three <= '659') || ($three >= '690' && $three <= '699');

        return $mtn || $orange;
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

        // 3. Do not ask Campay for a number it cannot resolve. MTN/Orange Cameroon only.
        if (! $this->isCampayCandidate($digits)) {
            return null;
        }

        // 1. If Campay recently returned 403 ("Too many failed attempts"), stop asking for a while
        //    so a loop cannot burn more attempts and extend the lockout.
        if (Cache::has('momo_holder:campay_blocked_until')) {
            return null;
        }

        // 2. Cached "not found" answer. Avoids re-counting the same bad number as a failed attempt.
        $missKey = 'momo_holder:campay_miss:'.$digits;
        if (Cache::has($missKey)) {
            return null;
        }

        $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
        $url = $base.'/holder_info/?phone_number='.urlencode($digits);
        $result = $this->curlWithStatus('GET', $url, [
            'Authorization: Token '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ], null, 12);
        $code = $result['code'];
        $body = $result['body'];

        if ($code === 403 || $code === 429) {
            Cache::put('momo_holder:campay_blocked_until', now()->timestamp, now()->addMinutes(self::CAMPAY_LOCKOUT_MINUTES));
            Log::warning('Campay holder_info blocked ('.$code.'), pausing lookups', ['minutes' => self::CAMPAY_LOCKOUT_MINUTES]);

            return null;
        }

        $decoded = is_array($body) ? $body : null;
        $name = $this->extractName($decoded);
        $address = $this->extractAddress($decoded);
        if ($name || $address) {
            return ['name' => $name, 'address' => $address, 'source' => 'campay'];
        }

        // 404 or 200-with-no-name: remember so repeat lookups do not re-contact Campay.
        if ($code === 404 || $code === 400 || ($code === 200 && ! $decoded)) {
            Cache::put($missKey, 1, now()->addMinutes(self::CAMPAY_NOT_FOUND_MINUTES));
        }

        return null;
    }

    protected function pawapay($digits)
    {
        $token = config('services.pawapay.api_token') ?: getenv('PAWAPAY_API_TOKEN') ?: getenv('PAWAPAY_TOKEN');
        if (! $token) {
            Log::info('PawaPay name lookup skipped: PAWAPAY_API_TOKEN is empty');

            return null;
        }

        $env = strtolower((string) (config('services.pawapay.environment') ?: getenv('PAWAPAY_ENVIRONMENT') ?: 'production'));
        $base = ($env === 'sandbox')
            ? 'https://api.sandbox.pawapay.io'
            : rtrim((string) (config('services.pawapay.live_base_url') ?: 'https://api.pawapay.io'), '/');

        $msisdn = ltrim($digits, '0');
        $headers = [
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $attempts = [
            [$base.'/v2/name-lookups', json_encode(['phoneNumber' => $msisdn])],
            [$base.'/v2/name-lookups', json_encode(['phoneNumber' => '+'.$msisdn])],
            [$base.'/v1/standard/name-lookup', json_encode([
                'depositId' => (string) Str::uuid(),
                'payer' => [
                    'type' => 'MSISDN',
                    'address' => ['value' => $msisdn],
                ],
            ])],
        ];

        foreach ($attempts as $pair) {
            $body = $this->httpPost($pair[0], $headers, $pair[1], 15);
            $name = $this->extractName($body);
            $address = $this->extractAddress($body);
            if ($name || $address) {
                return ['name' => $name, 'address' => $address, 'source' => 'pawapay'];
            }
        }

        return null;
    }

    protected function extractName($decoded)
    {
        if (! is_array($decoded)) {
            return null;
        }
        $nested = [];
        foreach (['data', 'result', 'output', 'name', 'accountHolder'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $nested[] = $decoded[$key];
            }
        }
        $bags = array_merge([$decoded], $nested);
        $keys = [
            'full_name', 'fullName', 'registeredName', 'requestedName',
            'accountHolderName', 'account_holder_name', 'financialAddressName', 'name',
        ];
        foreach ($bags as $bag) {
            foreach ($keys as $key) {
                $raw = $bag[$key] ?? null;
                if (is_array($raw)) {
                    $raw = $raw['fullName'] ?? $raw['full_name'] ?? $raw['registeredName'] ?? null;
                }
                if (! is_string($raw)) {
                    continue;
                }
                $name = trim($raw);
                if ($name === '' || preg_match('/^\+?\d{8,}$/', $name)) {
                    continue;
                }

                return $name;
            }
        }

        return null;
    }

    protected function extractAddress($decoded)
    {
        if (! is_array($decoded)) {
            return null;
        }
        $nested = [];
        foreach (['data', 'result', 'output', 'accountHolder'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $nested[] = $decoded[$key];
            }
        }
        $bags = array_merge([$decoded], $nested);
        $keys = ['address', 'full_address', 'fullAddress', 'location', 'town', 'city', 'quartier', 'region'];
        $parts = [];
        foreach ($bags as $bag) {
            foreach ($keys as $key) {
                $raw = $bag[$key] ?? null;
                if (! is_string($raw)) {
                    continue;
                }
                $val = trim($raw);
                if ($val === '' || strtoupper($val) === 'N/A' || strtoupper($val) === 'NAN' || preg_match('/^\+?\d{8,}$/', $val)) {
                    continue;
                }
                if (! in_array($val, $parts, true)) {
                    $parts[] = $val;
                }
            }
            if ($parts) {
                break;
            }
        }

        return $parts ? implode(', ', $parts) : null;
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
        $result = $this->curlWithStatus($method, $url, $headers, $payload, $timeout);

        return is_array($result['body']) ? $result['body'] : null;
    }

    protected function curlWithStatus($method, $url, array $headers, $payload, $timeout)
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
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($err) {
            Log::info('MoMo holder lookup failed: '.$err);

            return ['code' => 0, 'body' => null, 'raw' => null];
        }
        if ($code >= 400) {
            Log::info('MoMo holder lookup HTTP '.$code, ['url' => preg_replace('/phone_number=[^&]*/', 'phone_number=***', $url)]);
        }

        $decoded = json_decode((string) $response, true);

        return [
            'code' => $code,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => (string) $response,
        ];
    }
}
