<?php

namespace App\Http\Middleware;

use App\Support\SiteSession;
use Symfony\Component\HttpFoundation\Cookie;

class ShareBrowserLogin
{
    public function handle($request, $next)
    {
        $shared = SiteSession::apply($request->getHost());
        $response = $next($request);
        if (! $shared) {
            return $response;
        }
        // The old cookie was stored for this host only and would hide the shared one.
        $response->headers->setCookie(new Cookie(
            config('session.cookie'),
            null,
            1,
            '/',
            null,
            false,
            true,
            false,
            'lax'
        ));

        return $response;
    }
}
