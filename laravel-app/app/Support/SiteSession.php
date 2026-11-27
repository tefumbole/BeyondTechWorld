<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Drop the shared login cookie and the older host-only cookie.
     * A parent-domain cookie is not cleared by a host-only delete.
     */
    public static function expire(Response $response)
    {
        $names = [
            'beyond_site_session',
            'beyond_enterprise_session',
            (string) config('session.cookie'),
            'remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class),
            'remember_beyond_'.sha1(\Illuminate\Auth\SessionGuard::class),
        ];
        $seen = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $response->headers->setCookie(new Cookie($name, '', 1, '/', '.beyondtechworld.com', true, true, false, 'lax'));
            $response->headers->setCookie(new Cookie($name, '', 1, '/', null, false, true, false, 'lax'));
        }

        return $response;
    }
}
