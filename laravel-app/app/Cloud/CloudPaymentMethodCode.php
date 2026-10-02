<?php

namespace App\Cloud;

/**
 * Subscription checkout methods already used in this application.
 * MOMO is Campay (payment_options MOMO). VISA is the existing Stripe card checkout.
 */
class CloudPaymentMethodCode
{
    const MOMO = 'MOMO';
    const VISA = 'VISA';

    public static function all()
    {
        return [self::MOMO, self::VISA];
    }
}
