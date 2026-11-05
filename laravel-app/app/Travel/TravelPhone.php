<?php

namespace App\Travel;

class TravelPhone
{
    public static function normalize($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strpos($digits, '00237') === 0) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9 && strpos($digits, '6') === 0) {
            $digits = '237'.$digits;
        }
        if (strlen($digits) === 10 && strpos($digits, '06') === 0) {
            $digits = '237'.substr($digits, 1);
        }
        if (! preg_match('/^2376\d{8}$/', $digits)) {
            throw new TravelException('phone', 'Enter a Cameroon WhatsApp number, for example 6XXXXXXXX.');
        }

        return $digits;
    }
}
