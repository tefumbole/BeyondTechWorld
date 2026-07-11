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
            if (app()->environment('local')) {
                \Log::info('[beyond-otp] Wasender not configured — OTP message: '.$message);

                return ['success' => true, 'dev' => true];
            }

            return ['success' => false, 'error' => 'WhatsApp messaging is not configured.'];
        }

        try {
            $to = $this->formatPhone($phone);
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
            curl_close($ch);

            if ($err) {
                return ['success' => false, 'error' => $err];
            }

            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['success']) && $decoded['success'] !== true) {
                return ['success' => false, 'error' => $decoded['message'] ?? $decoded['error'] ?? 'Wasender rejected message'];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
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
