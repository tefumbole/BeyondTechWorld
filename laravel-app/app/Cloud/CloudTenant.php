<?php

namespace App\Cloud;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A Beyond Cloud company workspace.
 *
 * This is not App\Property\Tenancy. Property tenancies are people renting a unit.
 * Do not add a relationship to Product, Sale, Booking, or WhatsApp models in this phase.
 *
 * Platform tables: cloud_modules, cloud_plans.
 * Company tables: cloud_tenants, cloud_tenant_memberships, cloud_tenant_settings, cloud_subscriptions.
 */
class CloudTenant extends Model
{
    protected $table = 'cloud_tenants';

    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'system_name',
        'type',
        'status',
        'email',
        'phone',
        'country',
        'city',
        'address',
        'currency',
        'timezone',
        'logo_path',
        'hero_path',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function (CloudTenant $tenant) {
            if (! $tenant->uuid) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (! $tenant->type) {
                $tenant->type = CloudTenantType::CUSTOMER;
            }
            if (! $tenant->status) {
                $tenant->status = CloudTenantStatus::PENDING;
            }
            if (! $tenant->currency) {
                $tenant->currency = 'XAF';
            }
            if (! $tenant->timezone) {
                $tenant->timezone = 'Africa/Douala';
            }
        });
    }

    public function memberships()
    {
        return $this->hasMany(CloudTenantMembership::class, 'cloud_tenant_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(CloudSubscription::class, 'cloud_tenant_id');
    }

    public function settings()
    {
        return $this->hasMany(CloudTenantSetting::class, 'cloud_tenant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
