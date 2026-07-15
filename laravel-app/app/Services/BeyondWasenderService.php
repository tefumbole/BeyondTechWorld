<?php

namespace App\Services;

use App\Support\WhatsAppPhone;

class BeyondWasenderService
{
    public function isConfigured()
    {
        $key = config('services.whatsapp.wasender_api_key');
        $session = config('services.whatsapp.wasender_session_id');

        return ! empty($key) && ! empty($session) && strpos($key, 'your_') !== 0;
    }

    public function formatPhone($phone)
    {
        return WhatsAppPhone::forWasender($phone);
    }

    public function sendOtp($phone, $code, $label = 'Beyond Enterprise')
    {
        $message = "Your {$label} verification code is: {$code}. Valid for 10 minutes. Do not share this code.";

        return $this->sendText($phone, $message);
    }

    public function sendText($phone, $message)
    {
        if (! $this->isConfigured()) {
            // Same Wasender credentials as OTP / shareholders / bookings.
            if (app()->environment('local')) {
                \Log::info('[beyond-whatsapp] Wasender not configured — message: '.$message);

                return ['success' => true, 'dev' => true];
            }

            \Log::warning('[beyond-whatsapp] Wasender not configured (missing WASENDER_API_KEY or WASENDER_SESSION_ID)');

            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }

        try {
            $to = $this->formatPhone($phone);
            if (! $to) {
                return ['success' => false, 'error' => 'Invalid WhatsApp number'];
            }

            $base = rtrim(config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
            $url = $base.'/send-message';
            $payload = json_encode(['to' => $to, 'text' => $message]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.config('services.whatsapp.wasender_api_key'),
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 30,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                \Log::warning('[beyond-whatsapp] curl error', ['error' => $err, 'to' => $to]);

                return ['success' => false, 'error' => $err];
            }

            $decoded = json_decode($body, true);
            if ($http >= 400 || (is_array($decoded) && isset($decoded['success']) && $decoded['success'] !== true)) {
                $error = is_array($decoded)
                    ? ($decoded['message'] ?? $decoded['error'] ?? 'Wasender rejected message')
                    : ('HTTP '.$http);

                \Log::warning('[beyond-whatsapp] send failed', ['error' => $error, 'to' => $to, 'http' => $http]);

                return ['success' => false, 'error' => $error];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            \Log::warning('[beyond-whatsapp] exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function maskPhone($phone)
    {
        $formatted = $this->formatPhone($phone);
        if (strlen($formatted) < 8) {
            return $phone;
        }

        return substr($formatted, 0, 6).'****'.substr($formatted, -2);
    }
}
