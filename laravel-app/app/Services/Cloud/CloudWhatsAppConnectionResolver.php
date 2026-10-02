<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudWhatsAppConnection;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks choose a company from the provider connection, never from the sender's phone.
 */
class CloudWhatsAppConnectionResolver
{
    public function tenantForPayload(array $payload)
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return null;
        }
        $this->ensureInternalConnection();
        $session = $this->sessionId($payload);
        $query = CloudWhatsAppConnection::whereIn('status', ['ACTIVE', 'CONNECTED']);
        if ($session !== '') {
            $match = (clone $query)->where('provider_connection_id', $session)->first();
            if ($match && $match->cloudTenant && $match->cloudTenant->status === CloudTenantStatus::ACTIVE) {
                return $match->cloudTenant;
            }
            if ($session !== '' && $query->count() > 1) {
                return null;
            }
        }
        $rows = $query->get();
        if ($rows->count() === 1 && $rows->first()->cloudTenant) {
            return $rows->first()->cloudTenant;
        }

        return null;
    }

    public function soleTenantId()
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            $legacy = app(CloudTenantResolver::class)->legacyInternal();

            return $legacy ? $legacy->id : null;
        }
        $this->ensureInternalConnection();
        $rows = CloudWhatsAppConnection::where('status', 'ACTIVE')->get();
        if ($rows->count() !== 1) {
            return null;
        }

        return (int) $rows->first()->cloud_tenant_id;
    }

    public function sessionId(array $payload)
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        foreach (['sessionId', 'session_id', 'session'] as $key) {
            if (! empty($payload[$key]) && ! is_array($payload[$key])) {
                return trim((string) $payload[$key]);
            }
            if (! empty($data[$key]) && ! is_array($data[$key])) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    public function ensureInternalConnection()
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return null;
        }
        if (CloudWhatsAppConnection::query()->exists()) {
            return CloudWhatsAppConnection::query()->orderBy('id')->first();
        }
        $tenant = app(CloudTenantResolver::class)->legacyInternal();
        if (! $tenant) {
            return null;
        }
        $session = trim((string) config('services.whatsapp.wasender_session_id'));

        return CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $tenant->id,
            'provider' => 'wasender',
            'provider_connection_id' => $session !== '' ? $session : 'beyondtechworld',
            'display_name' => $tenant->system_name ?: $tenant->name,
            'status' => 'ACTIVE',
            'credentials_reference' => 'services.whatsapp.wasender_api_key',
            'connected_at' => now(),
        ]);
    }
}
