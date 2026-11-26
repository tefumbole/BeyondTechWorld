<?php

namespace App\Http\Middleware;

use Closure;

class RedirectMaiHost
{
    public function handle($request, Closure $next)
    {
        $host = (string) config('cloud.mai_customer.host');
        if ($host !== '' && strcasecmp($request->getHost(), $host) === 0 && trim($request->path(), '/') === '') {
            return redirect('/mai');
        }

        return $next($request);
    }
}
