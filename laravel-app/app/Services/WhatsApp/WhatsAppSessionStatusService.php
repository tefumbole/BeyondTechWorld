<?php

namespace App\Services\WhatsApp;

use App\Services\BeyondWasenderService;
use Illuminate\Support\Facades\Cache;

class WhatsAppSessionStatusService
{
    protected $wasender;

    public function __construct(BeyondWasenderService $wasender)
    {
        $this->wasender = $wasender;
    }

    /**
     * @return array{connected:bool,status:string,session_name:?string,configured:bool,error?:string}
     */
    public function status($force = false)
    {
        $ttl = max(15, (int) config('services.whatsapp.session_status_cache_seconds', 45));
        if ($force) {
            Cache::forget('whatsapp.session_status');
        }

        return Cache::remember('whatsapp.session_status', $ttl, function () {
            return $this->fetch();
        });
    }

    protected function fetch()
    {
        $configured = $this->wasender->isConfigured();
        if (! $configured) {
            return [
                'connected' => false,
                'status' => 'NOT_CONFIGURED',
                'session_name' => null,
                'configured' => false,
            ];
        }

        $base = rtrim((string) config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $key = config('services.whatsapp.wasender_api_key');
        $sessionId = config('services.whatsapp.wasender_session_id');
        $headers = [
            'Authorization: Bearer '.$key,
            'Accept: application/json',
        ];

        $statusResponse = $this->get($base.'/status', $headers);
        $raw = null;
        if (is_array($statusResponse)) {
            $raw = $statusResponse['status']
                ?? (isset($statusResponse['data']) && is_array($statusResponse['data']) ? ($statusResponse['data']['status'] ?? null) : null);
        }

        $sessionName = null;
        $sessionResponse = $this->get($base.'/whatsapp-sessions/'.$sessionId, $headers);
        if (is_array($sessionResponse) && ! empty($sessionResponse['data']['name'])) {
            $sessionName = (string) $sessionResponse['data']['name'];
        }

        $normalized = $this->normalizeStatus($raw);
        $error = null;
        if ($statusResponse === null && $sessionResponse === null) {
            $error = 'Wasender status request failed';
            $normalized = 'ERROR';
        }

        return [
            'connected' => $normalized === 'CONNECTED',
            'status' => $normalized,
            'session_name' => $sessionName,
            'configured' => true,
            'error' => $error,
        ];
    }

    protected function normalizeStatus($raw)
    {
        $value = strtoupper(trim((string) $raw));
        if ($value === '') {
            return 'UNKNOWN';
        }
        if (in_array($value, ['CONNECTED', 'ONLINE', 'READY'], true)) {
            return 'CONNECTED';
        }
        if (in_array($value, ['CONNECTING', 'PENDING', 'QR'], true)) {
            return 'CONNECTING';
        }
        if (in_array($value, ['DISCONNECTED', 'LOGGED_OUT', 'OFFLINE'], true)) {
            return 'DISCONNECTED';
        }
        if (in_array($value, ['ERROR', 'FAILED'], true)) {
            return 'ERROR';
        }

        return $value;
    }

    protected function get($url, array $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err || ! is_string($body)) {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
