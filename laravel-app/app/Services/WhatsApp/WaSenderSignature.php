<?php

namespace App\Services\WhatsApp;

use App\Cloud\CloudWhatsAppConnection;
use Illuminate\Support\Facades\Schema;

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
        if (self::check(self::secret(), $rawBody, $header)) {
            return true;
        }
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return false;
        }
        $rows = CloudWhatsAppConnection::where('provider', 'wasender')
            ->where('credentials_reference', 'wasender.session')
            ->whereIn('status', ['CONNECTED', 'ACTIVE', 'AWAITING_QR'])
            ->get();
        foreach ($rows as $row) {
            $secret = $row->webhookKey();
            if ($secret !== '' && self::check($secret, $rawBody, $header)) {
                return true;
            }
        }

        return false;
    }

    protected static function check($secret, $rawBody, $header)
    {
        $secret = trim((string) $secret);
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
