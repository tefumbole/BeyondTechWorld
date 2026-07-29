<?php

namespace App\Support;

use App\Delivery;

class DeliveryVerifyQr
{
    public static function token(Delivery $delivery): string
    {
        return substr(hash_hmac(
            'sha256',
            'delivery|'.$delivery->id.'|'.$delivery->reference_no,
            (string) config('app.key')
        ), 0, 20);
    }

    public static function scanUrl(Delivery $delivery): string
    {
        return url('/verify/delivery/'.$delivery->id.'/'.self::token($delivery));
    }

    public static function isValid(Delivery $delivery, string $token): bool
    {
        return hash_equals(self::token($delivery), (string) $token);
    }
}
