<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class BeyondAuthenticate
{
    public function handle($request, Closure $next)
    {
        if (! Auth::guard('beyond')->check()) {
            return redirect()->guest('/login');
        }

        return $next($request);
    }
}
