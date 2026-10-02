<?php

namespace App\Services\Cloud;

use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\Schema;

/**
 * Picks the active company from a membership or the documented legacy rule.
 * Never reads cloud_tenant_id from the request as authority.
 * Never falls back to id 1.
 */
class CloudTenantResolver
{
    const SESSION_KEY = 'cloud_active_tenant_id';

    public function forUser($user)
    {
        if ($user) {
            $fromSession = $this->fromSession($user);
            if ($fromSession) {
                return $fromSession;
            }
            $memberships = $this->activeMemberships($user);
            if ($memberships->count() === 1) {
                return $memberships->first()->cloudTenant;
            }
            if ($memberships->count() > 1) {
                return null;
            }
        }

        return $this->legacyInternal();
    }

    /**
     * Artisan commands other than the queue worker.
     * While no customer company exists, bind the internal company by slug.
     * After a customer company exists, commands must opt in per company.
     */
    public function forConsole()
    {
        if (! config('cloud.legacy_internal_context')) {
            return null;
        }
        if (! Schema::hasTable('cloud_tenants')) {
            return null;
        }
        $customers = CloudTenant::where('type', CloudTenantType::CUSTOMER)
            ->where('status', CloudTenantStatus::ACTIVE)
            ->count();
        if ($customers > 0) {
            return null;
        }

        return $this->legacyInternal();
    }

    public function legacyInternal()
    {
        if (! config('cloud.legacy_internal_context')) {
            return null;
        }
        if (! Schema::hasTable('cloud_tenants')) {
            return null;
        }

        return CloudTenant::where('slug', config('cloud.internal_slug'))
            ->where('type', CloudTenantType::INTERNAL)
            ->where('status', CloudTenantStatus::ACTIVE)
            ->first();
    }

    public function bindRecordTenant($record)
    {
        if (! $record || ! isset($record->cloud_tenant_id) || ! $record->cloud_tenant_id) {
            return null;
        }
        $tenant = CloudTenant::find($record->cloud_tenant_id);
        if ($tenant) {
            app(CloudTenantContext::class)->set($tenant);
        }

        return $tenant;
    }

    public function fromSession($user)
    {
        $id = (int) session(self::SESSION_KEY);
        if ($id < 1) {
            return null;
        }
        $membership = CloudTenantMembership::where('user_id', $user->id)
            ->where('cloud_tenant_id', $id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->first();
        if (! $membership || ! $membership->cloudTenant) {
            session()->forget(self::SESSION_KEY);

            return null;
        }
        if ($membership->cloudTenant->status !== CloudTenantStatus::ACTIVE) {
            return null;
        }

        return $membership->cloudTenant;
    }

    public function activeMemberships($user)
    {
        return CloudTenantMembership::with('cloudTenant')
            ->where('user_id', $user->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->whereHas('cloudTenant', function ($query) {
                $query->where('status', CloudTenantStatus::ACTIVE);
            })
            ->get();
    }
}
