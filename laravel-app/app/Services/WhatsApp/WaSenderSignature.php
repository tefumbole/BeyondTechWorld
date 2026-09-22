<?php

namespace App\Services\WhatsApp;

class WaSenderSignature
{
    public static function secret()
    {
        return trim((string) config('services.whatsapp.wasender_webhook_secret'));
    }

    public static function isConfigured()
    {
        return self::secret() !== '';
    }

    public static function isValid($rawBody, $header)
    {
        $secret = self::secret();
        $header = trim((string) $header);
        if ($secret === '' || $header === '') {
            return false;
        }

        if (hash_equals($secret, $header)) {
            return true;
        }

        $hmac = hash_hmac('sha256', (string) $rawBody, $secret);
        if (hash_equals($hmac, $header)) {
            return true;
        }

        if (stripos($header, 'sha256=') === 0) {
            return hash_equals($hmac, substr($header, 7));
        }

        return false;
    }
}
