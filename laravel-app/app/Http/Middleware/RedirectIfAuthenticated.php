<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            \App\Support\AuthIntended::rememberFromRequest($request);
            $user = Auth::guard($guard)->user();
            if ($user instanceof \App\User) {
                return redirect(\App\Support\AuthIntended::afterLogin($user));
            }

            return redirect('/admin');
        }

        return $next($request);
    }
}
