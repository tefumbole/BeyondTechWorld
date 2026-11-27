<?php

namespace App\Support;

class SiteSession
{
    /**
     * One browser login covers beyondtechworld.com and its subdomains.
     * A link opened on this site then keeps the same session.
     */
    public static function apply($host)
    {
        $host = strtolower((string) $host);
        if (! self::family($host)) {
            return false;
        }
        $lifetime = (int) config('session.lifetime');
        if ($lifetime < 60 * 24 * 30) {
            $lifetime = 60 * 24 * 30;
        }
        config([
            'session.cookie' => 'beyond_site_session',
            'session.domain' => '.beyondtechworld.com',
            'session.secure' => true,
            'session.same_site' => 'lax',
            'session.lifetime' => $lifetime,
        ]);

        return true;
    }

    public static function family($host)
    {
        $host = strtolower((string) $host);

        return $host === 'beyondtechworld.com'
            || (strlen($host) > 20 && substr($host, -20) === '.beyondtechworld.com');
    }
}
