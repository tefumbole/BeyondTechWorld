<?php

namespace App\Http\Middleware;

use App\Cloud\CloudTenantType;
use App\Services\Cloud\CloudModuleAccessService;
use App\Services\Cloud\CloudTenantContext;
use Closure;

/**
 * CUSTOMER companies cannot open a module by typing its URL.
 * INTERNAL BeyondTechWorld is not billed and is not blocked here.
 */
class EnforceCloudModule
{
    public function handle($request, Closure $next)
    {
        $capability = $this->capabilityForPath(trim($request->path(), '/'));
        if (! $capability) {
            return $next($request);
        }
        $tenant = app(CloudTenantContext::class)->tenant();
        if (! $tenant || $tenant->type !== CloudTenantType::CUSTOMER) {
            return $next($request);
        }

        $write = ! $request->isMethod('GET') && ! $request->isMethod('HEAD');
        if (! $write && preg_match('#/(create|edit)(/|$)#', '/'.$request->path())) {
            $write = true;
        }
        $access = app(CloudModuleAccessService::class);
        $allowed = $write
            ? $access->canWriteCapability($tenant, $capability)
            : $access->canReadCapability($tenant, $capability);
        if ($allowed) {
            return $next($request);
        }

        $readable = $access->canReadCapability($tenant, $capability);
        $message = $readable
            ? 'This module is read-only until the subscription is renewed.'
            : 'This module is not included in the current subscription.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 403);
        }

        return response($message, 403);
    }

    /**
     * Unmapped paths stay open. Portal, billing, and public signup are unmapped
     * so an expired module cannot lock the company out of renewal.
     */
    public function capabilityForPath($path)
    {
        $path = strtolower($path);
        $rules = [
            ['admin/whatsapp', 'messaging'],
            ['announcements', 'messaging'],
            ['quotations', 'quotations'],
            ['quotation', 'quotations'],
            ['products', 'catalog'],
            ['product', 'catalog'],
            ['customers', 'catalog'],
            ['customer', 'catalog'],
            ['suppliers', 'catalog'],
            ['sales', 'sales'],
            ['sale', 'sales'],
            ['payments', 'sales'],
            ['payment', 'sales'],
            ['invoices', 'sales'],
            ['invoice', 'sales'],
            ['bookings', 'rentals'],
            ['booking', 'rentals'],
            ['rentals', 'rentals'],
        ];
        foreach ($rules as $rule) {
            $prefix = $rule[0];
            if ($path === $prefix || strpos($path, $prefix.'/') === 0) {
                return $rule[1];
            }
        }

        return null;
    }
}
