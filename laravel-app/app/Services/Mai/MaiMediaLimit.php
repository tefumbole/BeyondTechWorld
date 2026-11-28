<?php

namespace App\Services\Mai;

class MaiMediaLimit
{
    public static function maxBytes()
    {
        $raw = (int) config('cloud.mai_customer.media_max_bytes', 8 * 1024 * 1024);
        if ($raw < 1024) {
            return 8 * 1024 * 1024;
        }

        return $raw;
    }

    public static function tooLarge($bytes)
    {
        return (int) $bytes > self::maxBytes();
    }

    public static function label()
    {
        return max(1, (int) round(self::maxBytes() / (1024 * 1024))).' MB';
    }
}
