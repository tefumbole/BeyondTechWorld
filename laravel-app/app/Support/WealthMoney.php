<?php

namespace App\Support;

class WealthMoney
{
    public static function of($value)
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return function_exists('bcadd')
            ? bcadd((string) $value, '0', 2)
            : number_format(round((float) $value, 2), 2, '.', '');
    }

    public static function add($a, $b)
    {
        if (function_exists('bcadd')) {
            return bcadd(self::of($a), self::of($b), 2);
        }

        return self::of((float) $a + (float) $b);
    }

    public static function sub($a, $b)
    {
        if (function_exists('bcsub')) {
            return bcsub(self::of($a), self::of($b), 2);
        }

        return self::of((float) $a - (float) $b);
    }

    public static function mul($a, $b)
    {
        if (function_exists('bcmul')) {
            return bcmul(self::of($a), (string) $b, 2);
        }

        return self::of((float) $a * (float) $b);
    }

    public static function percentOf($amount, $percent)
    {
        if (function_exists('bcmul') && function_exists('bcdiv')) {
            return bcdiv(bcmul(self::of($amount), self::of($percent), 4), '100', 2);
        }

        return self::of(((float) $amount * (float) $percent) / 100);
    }

    public static function ratio($part, $whole)
    {
        $whole = self::of($whole);
        if ((float) $whole == 0.0) {
            return 0.0;
        }
        if (function_exists('bcdiv') && function_exists('bcmul')) {
            return (float) bcmul(bcdiv(self::of($part), $whole, 6), '100', 2);
        }

        return round(((float) $part / (float) $whole) * 100, 2);
    }

    public static function cmp($a, $b)
    {
        if (function_exists('bccomp')) {
            return bccomp(self::of($a), self::of($b), 2);
        }

        $x = (float) $a;
        $y = (float) $b;
        if ($x < $y) {
            return -1;
        }

        return $x > $y ? 1 : 0;
    }
}
