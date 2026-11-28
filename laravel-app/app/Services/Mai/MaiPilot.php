<?php

namespace App\Services\Mai;

/**
 * An empty list leaves every personal number able to link.
 * A list limits new MAI links to those numbers and does not touch WaSender.
 */
class MaiPilot
{
    public static function phones()
    {
        $raw = config('cloud.mai_customer.pilot_phones', []);
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $phones = [];
        foreach ($raw as $phone) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            if (preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
                $phones[] = $digits;
            }
        }

        return array_values(array_unique($phones));
    }

    public static function allows($phone)
    {
        $allowed = self::phones();
        if (! $allowed) {
            return true;
        }
        $digits = preg_replace('/\D/', '', (string) $phone);
        foreach ($allowed as $one) {
            if (substr($one, -8) === substr($digits, -8)) {
                return true;
            }
        }

        return false;
    }
}
