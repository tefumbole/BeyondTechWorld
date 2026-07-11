<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class BeyondOtpVerified
{
    public function handle($request, Closure $next)
    {
        if (! Auth::guard('beyond')->check()) {
            return redirect('/login');
        }

        if (! $request->session()->get('beyond_otp_verified')) {
            return redirect('/otp-verification');
        }

        return $next($request);
    }
}
