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
        $user = Auth::user();
        if ($user instanceof \App\User) {
            try {
                app(\App\Services\Cloud\CloudCompanyAccess::class)->ensureRole($user);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $context = app(CloudTenantContext::class);
        $tenant = app(CloudTenantResolver::class)->forUser($user);
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
