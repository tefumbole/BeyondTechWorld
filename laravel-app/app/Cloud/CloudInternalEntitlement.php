<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-controlled module access for an INTERNAL CloudTenant.
 * This is not a subscription, a trial, or an invoice.
 */
class CloudInternalEntitlement extends Model
{
    protected $table = 'cloud_internal_entitlements';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_module_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function module()
    {
        return $this->belongsTo(CloudModule::class, 'cloud_module_id');
    }
}
