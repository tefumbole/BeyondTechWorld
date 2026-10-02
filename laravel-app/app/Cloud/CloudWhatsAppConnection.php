<?php

namespace App\Cloud;

use App\Services\Cloud\CloudTenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The WhatsApp account that belongs to one CloudTenant.
 * The provider secret stays in config. This row stores only a reference name.
 */
class CloudWhatsAppConnection extends Model
{
    protected $table = 'cloud_whatsapp_connections';

    protected $fillable = [
        'cloud_tenant_id',
        'provider',
        'provider_connection_id',
        'phone_number',
        'display_name',
        'status',
        'credentials_reference',
        'connected_at',
        'last_health_check_at',
    ];

    protected $dates = ['connected_at', 'last_health_check_at'];

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function tenant()
    {
        $context = app(CloudTenantContext::class);
        if (! $context->has() || (int) $context->id() !== (int) $this->cloud_tenant_id) {
            return $this->cloudTenant;
        }

        return $context->get();
    }
}
