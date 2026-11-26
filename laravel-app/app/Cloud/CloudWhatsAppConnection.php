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

    protected $hidden = ['session_secret', 'webhook_secret'];

    protected $dates = ['connected_at', 'last_health_check_at'];

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function rememberSecret($sessionKey, $webhookSecret)
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'session_secret')) {
            return;
        }
        $this->session_secret = $sessionKey !== '' ? encrypt($sessionKey) : null;
        $this->webhook_secret = $webhookSecret !== '' ? encrypt($webhookSecret) : null;
        $this->save();
    }

    public function sessionKey()
    {
        return $this->openSecret('session_secret');
    }

    public function webhookKey()
    {
        return $this->openSecret('webhook_secret');
    }

    protected function openSecret($column)
    {
        if (! isset($this->attributes[$column]) || $this->attributes[$column] === null || $this->attributes[$column] === '') {
            return '';
        }
        try {
            $plain = decrypt($this->attributes[$column]);
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($plain) ? $plain : '';
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
