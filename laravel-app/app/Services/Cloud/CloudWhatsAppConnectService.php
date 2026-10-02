<?php

namespace App\Services\Cloud;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudWhatsAppConnection;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer WhatsApp connection requests. Live WaSender sessions stay off.
 * A generated QR does not mark the connection connected.
 */
class CloudWhatsAppConnectService
{
    public function begin(CloudTenant $tenant, User $actor)
    {
        $this->assertAllowed($tenant, $actor);
        $existing = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($existing && in_array($existing->status, ['ACTIVE', 'CONNECTED'], true)) {
            throw new \RuntimeException('This company already has a WhatsApp connection.');
        }
        if ($existing && $existing->status === 'AWAITING_QR' && $existing->created_at && $existing->created_at->gt(now()->subMinutes(3))) {
            return $existing;
        }
        $recent = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($recent >= 3) {
            throw new \RuntimeException('Too many WhatsApp connection attempts. Please wait.');
        }

        $capacity = app(WhatsAppCapacityService::class);
        $reservationId = null;
        $connection = DB::transaction(function () use ($tenant, $existing, $capacity, &$reservationId) {
            $capacity->lock();
            $reservationId = $capacity->reserve($tenant);
            $connection = $existing ?: new CloudWhatsAppConnection();
            $connection->cloud_tenant_id = $tenant->id;
            $connection->provider = 'stub';
            $connection->status = 'PROVISIONING';
            $connection->credentials_reference = 'stub';
            $connection->display_name = $tenant->system_name ?: $tenant->name;
            $connection->save();

            return $connection;
        });

        $provider = app(CloudStubWhatsAppSessionProvider::class);
        $created = $provider->provision($connection);
        if (empty($created['session_id'])) {
            $connection->status = 'ERROR';
            $connection->save();
            $capacity->release($reservationId);
            $this->audit($tenant, $connection, $actor, 'provider_failed');
            throw new \RuntimeException('WhatsApp could not be started for this company.');
        }
        $connection->provider_connection_id = (string) $created['session_id'];
        $connection->status = 'AWAITING_QR';
        $connection->save();
        $capacity->consume($reservationId);
        Cache::put($this->qrKey($connection), $provider->qr($connection), now()->addMinutes(2));
        $this->audit($tenant, $connection, $actor, 'qr_requested');

        return $connection->fresh();
    }

    public function qr(CloudTenant $tenant, CloudWhatsAppConnection $connection, User $actor)
    {
        $this->assertSameCompany($tenant, $connection, $actor);
        if ($connection->status !== 'AWAITING_QR') {
            return null;
        }

        return Cache::get($this->qrKey($connection));
    }

    public function refresh(CloudTenant $tenant, CloudWhatsAppConnection $connection, User $actor)
    {
        $this->assertSameCompany($tenant, $connection, $actor);
        $remote = app(CloudStubWhatsAppSessionProvider::class)->status($connection);
        $connection->last_health_check_at = now();
        if ($remote === 'CONNECTED') {
            $connection->status = 'CONNECTED';
            $connection->connected_at = $connection->connected_at ?: now();
            Cache::forget($this->qrKey($connection));
            $this->audit($tenant, $connection, $actor, 'connected');
        } elseif ($remote === 'ERROR') {
            $connection->status = 'ERROR';
            $this->audit($tenant, $connection, $actor, 'provider_failed');
        }
        $connection->save();

        return $connection->fresh();
    }

    public function disconnect(CloudTenant $tenant, CloudWhatsAppConnection $connection, User $actor)
    {
        $this->assertSameCompany($tenant, $connection, $actor);
        app(CloudStubWhatsAppSessionProvider::class)->disconnect($connection);
        $connection->status = 'DISCONNECTED';
        $connection->save();
        Cache::forget($this->qrKey($connection));
        $this->audit($tenant, $connection, $actor, 'disconnected');

        return $connection->fresh();
    }

    public function assertAllowed(CloudTenant $tenant, User $actor)
    {
        if (! config('cloud.whatsapp_self_connect')) {
            throw new \RuntimeException('WhatsApp self-connection is not available yet.');
        }
        if ($tenant->type === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('The internal company connection is managed separately.');
        }
        $this->assertOwner($tenant, $actor);
        $capacity = app(WhatsAppCapacityService::class);
        if (! $capacity->canUseMessaging($tenant)) {
            throw new \RuntimeException('Messaging is not active for this company.');
        }
        if (! $capacity->canProvisionWhatsApp($tenant)) {
            throw new \RuntimeException('WhatsApp connection capacity currently unavailable.');
        }
    }

    protected function assertSameCompany(CloudTenant $tenant, CloudWhatsAppConnection $connection, User $actor)
    {
        $this->assertOwner($tenant, $actor);
        if ((int) $connection->cloud_tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('That WhatsApp connection belongs to another company.');
        }
    }

    protected function assertOwner(CloudTenant $tenant, User $actor)
    {
        $membership = CloudTenantMembership::where('cloud_tenant_id', $tenant->id)
            ->where('user_id', $actor->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->first();
        if (! $membership || $membership->membership_role !== CloudMembershipRole::OWNER) {
            throw new \RuntimeException('Only the company owner can connect WhatsApp.');
        }
    }

    protected function qrKey(CloudWhatsAppConnection $connection)
    {
        return 'cloud-wa-qr:'.$connection->id;
    }

    protected function audit(CloudTenant $tenant, CloudWhatsAppConnection $connection, User $actor, $event)
    {
        if (! Schema::hasTable('cloud_whatsapp_connection_events')) {
            return;
        }
        DB::table('cloud_whatsapp_connection_events')->insert([
            'cloud_tenant_id' => $tenant->id,
            'cloud_whatsapp_connection_id' => $connection->id,
            'event' => $event,
            'actor_user_id' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
