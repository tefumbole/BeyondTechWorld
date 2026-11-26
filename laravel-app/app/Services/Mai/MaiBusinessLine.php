<?php

namespace App\Services\Mai;

/**
 * The production business WhatsApp stays on WaSender.
 * A customer session cannot claim it or change its provider.
 */
class MaiBusinessLine
{
    const PROVIDER = 'wasender';

    public static function digits()
    {
        $configured = preg_replace('/\D+/', '', (string) config('cloud.mai_customer.business_phone', '237675321739'));

        return $configured !== '' ? $configured : '237675321739';
    }

    public static function is($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return false;
        }
        $business = self::digits();
        if ($digits === $business) {
            return true;
        }
        if (strlen($business) > 9 && substr($business, 0, 3) === '237' && $digits === substr($business, 3)) {
            return true;
        }

        return false;
    }
}
