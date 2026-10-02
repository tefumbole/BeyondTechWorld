<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Company configuration that does not belong as a column on cloud_tenants.
 * Do not store WhatsApp, OpenAI, or payment secrets here.
 * Branding keys may be stored later. Existing Beyond invoices do not read this table.
 */
class CloudTenantSetting extends Model
{
    protected $table = 'cloud_tenant_settings';

    protected $fillable = [
        'cloud_tenant_id',
        'key',
        'value',
        'type',
    ];

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }
}
