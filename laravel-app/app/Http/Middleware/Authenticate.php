<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            if (\Illuminate\Support\Facades\Route::has('login')) {
                return route('login');
            }
            if (\Illuminate\Support\Facades\Route::has('beyond.login')) {
                return route('beyond.login');
            }

            return url('/login');
        }
    }
}