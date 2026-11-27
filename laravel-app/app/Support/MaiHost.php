<?php

namespace App\Support;

use Illuminate\Http\Request;

class MaiHost
{
    public static function name()
    {
        $host = strtolower(trim((string) config('cloud.mai_customer.host')));

        return $host !== '' ? $host : 'mai.beyondtechworld.com';
    }

    public static function is(Request $request = null)
    {
        $request = $request ?: request();

        return strtolower($request->getHost()) === self::name();
    }

    public static function isMainSite(Request $request = null)
    {
        $request = $request ?: request();
        $host = strtolower($request->getHost());

        return $host === 'beyondtechworld.com' || $host === 'www.beyondtechworld.com';
    }

    public static function url($path = '/')
    {
        $path = '/'.ltrim((string) $path, '/');
        if ($path === '/') {
            return 'https://'.self::name();
        }

        return 'https://'.self::name().$path;
    }
}
