<?php

namespace App\Support;

class MaiPhone
{
    public static function is($request)
    {
        $agent = strtolower((string) $request->userAgent());
        if ($agent === '') {
            return false;
        }

        foreach (['android', 'iphone', 'ipad', 'ipod', 'mobile'] as $needle) {
            if (strpos($agent, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function android($request)
    {
        return strpos(strtolower((string) $request->userAgent()), 'android') !== false;
    }
}
