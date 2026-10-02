<?php

namespace App\Http\Middleware;

use App\Services\Cloud\CloudPortalService;
use App\Services\Cloud\CloudTenantContext;
use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureCloudMembership
{
    public function handle($request, Closure $next)
    {
        $membership = app(CloudPortalService::class)->membershipFor(Auth::user());
        if (! $membership || ! $membership->cloudTenant) {
            return redirect()->route('cloud.login')->with('not_permitted', 'Sign in with a company account.');
        }
        $request->attributes->set('cloudTenant', $membership->cloudTenant);
        $request->attributes->set('cloudMembership', $membership);
        app(CloudTenantContext::class)->set($membership->cloudTenant);

        return $next($request);
    }
}
