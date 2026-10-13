<?php

namespace App\Cloud;

/**
 * Subscription checkout methods already used in this application.
 * MOMO is Campay (payment_options MOMO). VISA is the existing Stripe card checkout.
 */
class CloudPaymentMethodCode
{
    const MOMO = 'MOMO';
    const MTN = 'MTN';
    const ORANGE = 'ORANGE';
    const VISA = 'VISA';
    const CRYPTO = 'CRYPTO';

    public static function all()
    {
        return [self::MOMO, self::MTN, self::ORANGE, self::VISA, self::CRYPTO];
    }
}
