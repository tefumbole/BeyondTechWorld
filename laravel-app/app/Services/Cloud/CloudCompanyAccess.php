<?php

namespace App\Services\Cloud;

use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantType;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A trial company uses the same admin panel as BeyondTechWorld.
 * Its role only receives the permissions for modules on its subscription.
 */
class CloudCompanyAccess
{
    public function customerTenant(User $user)
    {
        if (! Schema::hasTable('cloud_tenant_memberships') || ! Schema::hasTable('cloud_tenants')) {
            return null;
        }
        if (in_array((int) $user->role_id, [1, 2], true)) {
            return null;
        }

        $membership = CloudTenantMembership::with('cloudTenant')
            ->where('user_id', $user->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->whereHas('cloudTenant', function ($query) {
                $query->where('type', CloudTenantType::CUSTOMER);
            })
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->first();

        return $membership && $membership->cloudTenant ? $membership->cloudTenant : null;
    }

    public function ensureRole(User $user, $forceSync = false)
    {
        $tenant = $this->customerTenant($user);
        if (! $tenant || ! $this->permissionTablesReady()) {
            return false;
        }

        $roleId = $this->roleId($tenant);
        if ($forceSync || (int) $user->role_id !== $roleId) {
            $this->syncPermissions($roleId, $tenant);
        }
        if ((int) $user->role_id !== $roleId) {
            $user->role_id = $roleId;
            $user->save();
        }

        return true;
    }

    public function permissionNames(CloudTenant $tenant)
    {
        $access = app(CloudModuleAccessService::class);
        $names = [];
        $catalog = [
            'products-index', 'products-add', 'products-edit', 'products-delete', 'category',
            'customers-index', 'customers-add', 'customers-edit', 'customers-delete',
        ];
        $sales = ['sales-index', 'sales-add', 'sales-edit', 'sales-delete'];
        $quotes = ['quotes-index', 'quotes-add', 'quotes-edit', 'quotes-delete'];
        $rentals = ['booking_module', 'booking_create', 'booking_index'];
        $messaging = ['whatsapp_module', 'whatsapp.view', 'whatsapp.manage', 'announcements_module', 'announcement_index'];
        $invitations = [
            'invitations_module', 'invitations.view',
            'online_invitation_category', 'online_invitation_template',
            'online_invitation_event', 'online_invitation_send_invitation',
        ];

        if ($access->canRead($tenant, CloudModuleCode::SALES_INVOICES)
            || $access->canRead($tenant, CloudModuleCode::RENTALS)
            || $access->canRead($tenant, CloudModuleCode::QUOTATIONS)) {
            $names = array_merge($names, $catalog);
        }
        if ($access->canRead($tenant, CloudModuleCode::SALES_INVOICES)) {
            $names = array_merge($names, $sales, $quotes);
        }
        if ($access->canRead($tenant, CloudModuleCode::QUOTATIONS)) {
            $names = array_merge($names, $quotes);
        }
        if ($access->canRead($tenant, CloudModuleCode::RENTALS)) {
            $names = array_merge($names, $rentals);
        }
        if ($access->canRead($tenant, CloudModuleCode::MESSAGING) || $access->canRead($tenant, CloudModuleCode::WHATSAPP_HUB)) {
            $names = array_merge($names, $messaging);
        }
        if ($access->canRead($tenant, CloudModuleCode::DIGITAL_INVITATIONS)) {
            $names = array_merge($names, $invitations);
        }

        return array_values(array_unique($names));
    }

    protected function roleId(CloudTenant $tenant)
    {
        $name = 'cloud-company-'.$tenant->id;
        $existing = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function syncPermissions($roleId, CloudTenant $tenant)
    {
        $wanted = $this->permissionNames($tenant);
        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $wanted)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        foreach ($ids as $id) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $id,
                'role_id' => $roleId,
            ]);
        }
    }

    protected function permissionTablesReady()
    {
        return Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions');
    }
}
