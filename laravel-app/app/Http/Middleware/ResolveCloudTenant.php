<?php

namespace App\Http\Middleware;

use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTenantResolver;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Sets the company before route model binding.
 * Platform Admin is not given every company. With no membership, the
 * legacy rule binds only the internal BeyondTechWorld company.
 */
class ResolveCloudTenant
{
    public function handle($request, Closure $next)
    {
        $context = app(CloudTenantContext::class);
        $tenant = app(CloudTenantResolver::class)->forUser(Auth::user());
        if ($tenant) {
            $context->set($tenant);
            $request->attributes->set('cloudTenant', $tenant);
        }
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
