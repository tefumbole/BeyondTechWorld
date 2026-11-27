<?php

namespace App\Http\Middleware;

use App\Support\SiteSession;

class ShareBrowserLogin
{
    public function handle($request, $next)
    {
        SiteSession::apply($request->getHost());

        return $next($request);
    }
}
