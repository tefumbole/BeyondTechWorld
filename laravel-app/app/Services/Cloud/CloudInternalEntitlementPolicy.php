<?php

namespace App\Services\Cloud;

use App\Cloud\CloudInternalEntitlement;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\Schema;

/**
 * INTERNAL companies keep their existing modules without a paid subscription.
 * Access does not read trial_ends_at and does not create a payment.
 */
class CloudInternalEntitlementPolicy
{
    public static function moduleCodes()
    {
        return [
            CloudModuleCode::WHATSAPP_HUB,
            CloudModuleCode::SALES_INVOICES,
            CloudModuleCode::RENTALS,
        ];
    }

    public function grants(CloudTenant $tenant, $moduleCode)
    {
        if ($tenant->type !== CloudTenantType::INTERNAL) {
            return false;
        }
        if (! Schema::hasTable('cloud_internal_entitlements')) {
            return false;
        }

        return CloudInternalEntitlement::where('cloud_tenant_id', $tenant->id)
            ->where('enabled', 1)
            ->whereHas('module', function ($query) use ($moduleCode) {
                $query->where('code', $moduleCode);
            })
            ->exists();
    }
}
