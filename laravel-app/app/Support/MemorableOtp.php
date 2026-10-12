<?php

namespace App\Support;

/**
 * A six-digit code shaped as AB AB CD, for example 47 47 82.
 * The repeated pair is the part a person can hold in memory.
 */
class MemorableOtp
{
    public static function code()
    {
        for ($try = 0; $try < 20; $try++) {
            $pair = random_int(13, 98);
            $last = random_int(13, 98);
            if ($pair === $last || $pair % 11 === 0 || $last % 11 === 0) {
                continue;
            }

            return sprintf('%02d%02d%02d', $pair, $pair, $last);
        }

        return sprintf('%02d%02d%02d', 47, 47, random_int(13, 98));
    }

    public static function display($code)
    {
        $digits = preg_replace('/\D+/', '', (string) $code);
        if (strlen($digits) !== 6) {
            return $digits;
        }

        return substr($digits, 0, 2).' '.substr($digits, 2, 2).' '.substr($digits, 4, 2);
    }
}
