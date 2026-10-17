<?php

namespace App\Gbhs;

use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Support\Facades\Schema;

class GbhsTenant
{
    public static function bind()
    {
        $context = app(CloudTenantContext::class);
        if ($context->has()) {
            return $context->tenant();
        }
        if (! Schema::hasTable('cloud_tenants')) {
            return null;
        }
        $tenant = CloudTenant::where('status', 'active')->orderBy('id')->first();
        if (! $tenant) {
            $tenant = CloudTenant::orderBy('id')->first();
        }
        if ($tenant) {
            $context->set($tenant);
        }

        return $tenant;
    }

    public static function ready()
    {
        return Schema::hasTable('gbhs_settings');
    }
}
