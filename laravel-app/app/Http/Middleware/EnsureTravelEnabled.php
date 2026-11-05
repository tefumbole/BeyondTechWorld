<?php

namespace App\Http\Middleware;

use Closure;

class EnsureTravelEnabled
{
    public function handle($request, Closure $next)
    {
        if (! config('travel.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
