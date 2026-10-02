<?php

namespace App\Services\Cloud;

use App\Cloud\CloudModuleCode;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a company may use a module.
 * INTERNAL companies use platform entitlements. CUSTOMER companies use subscriptions.
 * Controllers must not calculate trial dates themselves.
 */
class CloudModuleAccessService
{
    public function canAccess(CloudTenant $tenant, $moduleCode, $at = null)
    {
        return $this->decision($tenant, $moduleCode, $at)['level'] !== 'none';
    }

    public function canRead(CloudTenant $tenant, $moduleCode, $at = null)
    {
        return $this->decision($tenant, $moduleCode, $at)['level'] !== 'none';
    }

    public function canWrite(CloudTenant $tenant, $moduleCode, $at = null)
    {
        return $this->decision($tenant, $moduleCode, $at)['level'] === 'full';
    }

    public function status(CloudTenant $tenant, $moduleCode, $at = null)
    {
        return $this->decision($tenant, $moduleCode, $at)['status'];
    }

    public function reason(CloudTenant $tenant, $moduleCode, $at = null)
    {
        return $this->decision($tenant, $moduleCode, $at)['reason'];
    }

    public function trialEndsAt(CloudTenant $tenant, $moduleCode)
    {
        $subscription = $this->subscriptionForModule($tenant, $moduleCode);

        return $subscription ? $subscription->trial_ends_at : null;
    }

    public function subscriptionEndsAt(CloudTenant $tenant, $moduleCode)
    {
        $subscription = $this->subscriptionForModule($tenant, $moduleCode);

        return $subscription ? $subscription->current_period_end : null;
    }

    public function canReadCapability(CloudTenant $tenant, $capability, $at = null)
    {
        foreach ($this->modulesForCapability($capability) as $code) {
            if ($this->canRead($tenant, $code, $at)) {
                return true;
            }
        }

        return false;
    }

    public function canWriteCapability(CloudTenant $tenant, $capability, $at = null)
    {
        foreach ($this->modulesForCapability($capability) as $code) {
            if ($this->canWrite($tenant, $code, $at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shared capabilities. One module does not turn the others on.
     * Catalog and quotations are shared by Sales and Rentals because those
     * screens use the same products, customers, and quotation rows.
     * Messaging is WhatsApp Hub or the Messaging plan, not Sales or Rentals.
     */
    public function modulesForCapability($capability)
    {
        $map = [
            'messaging' => [CloudModuleCode::WHATSAPP_HUB, CloudModuleCode::MESSAGING],
            'sales' => [CloudModuleCode::SALES_INVOICES],
            'rentals' => [CloudModuleCode::RENTALS],
            'catalog' => [CloudModuleCode::SALES_INVOICES, CloudModuleCode::RENTALS],
            'quotations' => [CloudModuleCode::SALES_INVOICES, CloudModuleCode::RENTALS],
        ];

        return isset($map[$capability]) ? $map[$capability] : [];
    }

    public function toolCapability($name)
    {
        $rentals = [
            'search_rental_products', 'check_rental_availability', 'get_rental_product_information',
            'create_rental_quotation', 'request_rental_booking', 'search_event_products',
            'check_event_equipment_availability', 'build_event_solution', 'calculate_event_estimate',
            'create_event_quotation_draft', 'get_customer_bookings', 'get_booking_status',
            'get_package_details',
        ];
        $sales = [
            'get_customer_quotations', 'get_customer_quotation_details', 'get_quotation_status',
            'get_quotation_review_status', 'generate_quotation_review', 'get_customer_payment_summary',
        ];
        if (in_array($name, $rentals, true)) {
            return 'rentals';
        }
        if (in_array($name, $sales, true)) {
            return 'sales';
        }

        return null;
    }

    public function decision(CloudTenant $tenant, $moduleCode, $at = null)
    {
        $at = $at ?: now();
        if ($tenant->type !== CloudTenantType::INTERNAL && $tenant->status === \App\Cloud\CloudTenantStatus::SUSPENDED) {
            return $this->pack('read', 'SUSPENDED', 'company_suspended');
        }
        if ($tenant->type === CloudTenantType::INTERNAL) {
            $granted = app(CloudInternalEntitlementPolicy::class)->grants($tenant, $moduleCode);

            return $this->pack($granted ? 'full' : 'none', $granted ? 'PLATFORM' : 'NONE', $granted ? 'platform_entitlement' : 'not_entitled');
        }

        $subscription = $this->subscriptionForModule($tenant, $moduleCode);
        if (! $subscription) {
            return $this->pack('none', 'NONE', 'no_subscription');
        }

        $status = $subscription->status;
        if ($status === CloudSubscriptionStatus::SUSPENDED) {
            return $this->pack('read', $status, 'suspended');
        }
        if ($status === CloudSubscriptionStatus::CANCELLED || $status === CloudSubscriptionStatus::EXPIRED) {
            return $this->pack('read', $status, 'historical_only');
        }
        if ($status === CloudSubscriptionStatus::PAST_DUE) {
            return $this->pack('read', $status, 'past_due');
        }
        if ($status === CloudSubscriptionStatus::TRIALING) {
            if ($subscription->trial_ends_at && $subscription->trial_ends_at->gt($at)) {
                return $this->pack('full', $status, 'trial');
            }

            return $this->pack('read', CloudSubscriptionStatus::EXPIRED, 'trial_ended');
        }
        if ($status === CloudSubscriptionStatus::ACTIVE) {
            if ($subscription->current_period_end && $subscription->current_period_end->lte($at)) {
                return $this->pack('read', CloudSubscriptionStatus::PAST_DUE, 'period_ended');
            }

            return $this->pack('full', $status, 'paid');
        }

        return $this->pack('none', $status ?: 'NONE', 'unknown_status');
    }

    public function subscriptionForModule(CloudTenant $tenant, $moduleCode)
    {
        if (! Schema::hasTable('cloud_subscriptions')) {
            return null;
        }

        return CloudSubscription::with('plan.module')
            ->where('cloud_tenant_id', $tenant->id)
            ->whereHas('plan.module', function ($query) use ($moduleCode) {
                $query->where('code', $moduleCode);
            })
            ->orderByDesc('id')
            ->first();
    }

    protected function pack($level, $status, $reason)
    {
        return [
            'level' => $level,
            'status' => $status,
            'reason' => $reason,
        ];
    }
}
