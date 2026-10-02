<?php

namespace App\Services\Cloud;

use App\Cloud\CloudInternalEntitlement;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModule;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantSetting;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the one BeyondTechWorld INTERNAL company.
 * A second run updates that same row and does not insert another company.
 */
class CloudInternalTenantService
{
    const SLUG = 'beyondtechworld';

    const NAME = 'BeyondTechWorld';

    public function ensure()
    {
        $identity = $this->identity();
        $tenant = $this->findExisting();
        $created = false;

        if (! $tenant) {
            $tenant = new CloudTenant();
            $tenant->slug = self::SLUG;
            $created = true;
        }

        $tenant->name = self::NAME;
        $tenant->legal_name = $identity['legal_name'];
        $tenant->system_name = $identity['system_name'];
        $tenant->type = CloudTenantType::INTERNAL;
        $tenant->status = CloudTenantStatus::ACTIVE;
        $tenant->email = $identity['email'];
        $tenant->phone = $identity['phone'];
        $tenant->city = $identity['city'];
        $tenant->address = $identity['address'];
        $tenant->currency = 'XAF';
        $tenant->timezone = 'Africa/Douala';
        $tenant->save();

        $memberships = $this->syncMemberships($tenant);
        $entitlements = $this->syncEntitlements($tenant);
        $this->rememberPolicy($tenant);

        return [
            'created' => $created,
            'tenant' => $tenant,
            'identity' => $identity,
            'memberships' => $memberships,
            'entitlements' => $entitlements,
            'subscriptions' => Schema::hasTable('cloud_subscriptions')
                ? (int) DB::table('cloud_subscriptions')->where('cloud_tenant_id', $tenant->id)->count()
                : 0,
        ];
    }

    protected function findExisting()
    {
        $bySlug = CloudTenant::where('slug', self::SLUG)->first();
        if ($bySlug && $bySlug->type !== CloudTenantType::INTERNAL) {
            throw new \RuntimeException('Slug beyondtechworld belongs to a company that is not INTERNAL.');
        }
        if ($bySlug) {
            return $bySlug;
        }

        $internal = CloudTenant::where('type', CloudTenantType::INTERNAL)->orderBy('id')->get();
        if ($internal->count() > 1) {
            throw new \RuntimeException('More than one INTERNAL CloudTenant already exists.');
        }

        return $internal->first();
    }

    /**
     * Company facts come from the default biller and the site title.
     * Missing facts stay empty. Currency is XAF because that is the Cloud currency.
     */
    public function identity()
    {
        $setting = null;
        $biller = null;
        if (Schema::hasTable('general_settings')) {
            $setting = DB::table('general_settings')->orderBy('id')->first();
        }
        if (Schema::hasTable('billers')) {
            if ($setting && ! empty($setting->default_biller_id)) {
                $biller = DB::table('billers')->where('id', $setting->default_biller_id)->first();
            }
            if (! $biller) {
                $biller = DB::table('billers')->orderBy('id')->first();
            }
        }

        $siteTitle = $setting && ! empty($setting->site_title) ? $setting->site_title : null;
        $legal = null;
        if ($biller && ! empty($biller->company_name)) {
            $legal = $biller->company_name;
        } elseif ($biller && ! empty($biller->name)) {
            $legal = $biller->name;
        }

        return [
            'system_name' => $siteTitle,
            'legal_name' => $legal,
            'email' => $biller && ! empty($biller->email) ? $biller->email : null,
            'phone' => $biller && ! empty($biller->phone_number) ? $biller->phone_number : null,
            'address' => $biller && ! empty($biller->address) ? $biller->address : null,
            'city' => $biller && ! empty($biller->city) ? $biller->city : null,
            'site_title' => $siteTitle,
            'biller_id' => $biller ? $biller->id : null,
            'settings_currency' => $setting && isset($setting->currency) ? $setting->currency : null,
            'conflicts' => $this->conflicts($siteTitle, $legal, $setting),
        ];
    }

    protected function conflicts($siteTitle, $legal, $setting)
    {
        $conflicts = [];
        if ($siteTitle && $siteTitle !== self::NAME) {
            $conflicts[] = 'general_settings.site_title is "'.$siteTitle.'". CloudTenant.name stays BeyondTechWorld. The site title is stored as system_name.';
        }
        if ($legal && $legal !== self::NAME) {
            $conflicts[] = 'The default biller company name is "'.$legal.'". That value is stored as legal_name. The biller row is not converted into a CloudTenant.';
        }
        if ($setting && isset($setting->currency)) {
            $label = (string) $setting->currency;
            if (Schema::hasTable('currencies')) {
                $currency = DB::table('currencies')->where('id', $setting->currency)->first();
                if ($currency) {
                    $label = trim((isset($currency->code) ? $currency->code : '').' '.(isset($currency->name) ? $currency->name : ''));
                }
            }
            if (stripos($label, 'XAF') === false) {
                $conflicts[] = 'general_settings.currency resolves to "'.$label.'". CloudTenant.currency stays XAF, matching cloud_plans.';
            }
        }

        return $conflicts;
    }

    /**
     * Platform Admin (role_id 1) is not a company membership.
     * The active Owner (role_id 2) is the company owner.
     * Active internship supervisors (role_id 15) are staff.
     * Customers, vendors, and interns are not members.
     * users.role_id is never written.
     */
    public function syncMemberships(CloudTenant $tenant)
    {
        $result = ['owner' => 0, 'admin' => 0, 'staff' => 0, 'skipped_platform_admin' => 0];
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role_id')) {
            return $result;
        }

        if (Schema::hasColumn('users', 'is_active')) {
            $result['skipped_platform_admin'] = (int) DB::table('users')->where('role_id', 1)->where('is_active', 1)->count();
        }

        $owners = $this->activeUsersWithRole(2);
        foreach ($owners as $index => $user) {
            $role = $index === 0 ? 'OWNER' : 'ADMIN';
            $this->upsertMembership($tenant, $user->id, (int) $user->role_id, $role, $role === 'OWNER');
            $result[$role === 'OWNER' ? 'owner' : 'admin']++;
        }

        foreach ($this->activeUsersWithRole(15) as $user) {
            $this->upsertMembership($tenant, $user->id, (int) $user->role_id, 'STAFF', false);
            $result['staff']++;
        }

        return $result;
    }

    protected function activeUsersWithRole($roleId)
    {
        $query = DB::table('users')->where('role_id', $roleId)->orderBy('id');
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }

        return $query->get(['id', 'role_id']);
    }

    protected function upsertMembership(CloudTenant $tenant, $userId, $roleId, $membershipRole, $isOwner)
    {
        $existing = CloudTenantMembership::where('cloud_tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->first();
        if (! $existing) {
            $existing = new CloudTenantMembership();
            $existing->cloud_tenant_id = $tenant->id;
            $existing->user_id = $userId;
        }
        $existing->role_id = $roleId;
        $existing->membership_role = $membershipRole;
        $existing->is_owner = $isOwner;
        $existing->status = CloudMembershipStatus::ACTIVE;
        if (! $existing->joined_at) {
            $existing->joined_at = now();
        }
        $existing->save();
    }

    public function syncEntitlements(CloudTenant $tenant)
    {
        $codes = [];
        if (! Schema::hasTable('cloud_internal_entitlements')) {
            return $codes;
        }

        foreach (CloudInternalEntitlementPolicy::moduleCodes() as $code) {
            $module = CloudModule::where('code', $code)->first();
            if (! $module) {
                continue;
            }
            CloudInternalEntitlement::firstOrCreate([
                'cloud_tenant_id' => $tenant->id,
                'cloud_module_id' => $module->id,
            ], [
                'enabled' => true,
            ]);
            $codes[] = $code;
        }

        return $codes;
    }

    protected function rememberPolicy(CloudTenant $tenant)
    {
        if (! Schema::hasTable('cloud_tenant_settings')) {
            return;
        }
        CloudTenantSetting::firstOrCreate([
            'cloud_tenant_id' => $tenant->id,
            'key' => 'entitlement_policy',
        ], [
            'value' => 'platform_controlled_modules',
        ]);
    }
}
