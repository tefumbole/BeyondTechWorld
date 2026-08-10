<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class Active
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(Auth::check() && Auth::user()->isActive()){
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'must_set_password')
                && Auth::user()->must_set_password
                && ! $request->is('staff-set-password')
                && ! $request->is('logout')) {
                $request->session()->put('staff_must_set_password', true);

                return redirect('/staff-set-password');
            }

            return $next($request);
        }

        return redirect('/dashboard');
        
    }
}
