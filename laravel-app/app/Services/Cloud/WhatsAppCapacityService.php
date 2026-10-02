<?php

namespace App\Services\Cloud;

use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudWhatsAppConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether another customer WhatsApp session may be reserved.
 * An unknown provider limit offers no customer slots.
 */
class WhatsAppCapacityService
{
    const HOLDING = ['PROVISIONING', 'AWAITING_QR', 'CONNECTING', 'CONNECTED', 'ACTIVE'];

    public function sessionLimit()
    {
        $value = config('cloud.whatsapp.session_limit');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function canUseMessaging(CloudTenant $tenant)
    {
        return app(CloudModuleAccessService::class)->canWriteCapability($tenant, 'messaging');
    }

    public function canProvisionWhatsApp(CloudTenant $tenant)
    {
        if (! config('cloud.whatsapp.provisioning_enabled')) {
            return false;
        }
        if (config('cloud.whatsapp.provisioning_policy') !== 'INCLUDED_IN_PLAN') {
            return false;
        }
        if ($tenant->type === CloudTenantType::INTERNAL) {
            return false;
        }
        if (! $this->canUseMessaging($tenant)) {
            return false;
        }
        if ($this->onTrial($tenant) && ! config('cloud.whatsapp.trial_provisioning_allowed')) {
            return false;
        }

        return $this->availableCustomerSlots() > 0;
    }

    public function canSendWhatsApp(CloudTenant $tenant)
    {
        if (! config('cloud.whatsapp.customer_send_enabled')) {
            return false;
        }
        if ($tenant->type !== CloudTenantType::CUSTOMER || ! $this->canUseMessaging($tenant)) {
            return false;
        }
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return false;
        }
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)
            ->whereIn('status', ['ACTIVE', 'CONNECTED'])
            ->orderBy('id', 'desc')
            ->first();
        if (! $connection || $connection->provider !== 'wasender') {
            return false;
        }
        if ($connection->credentials_reference === 'services.whatsapp.wasender_api_key') {
            return false;
        }
        $internal = trim((string) config('services.whatsapp.wasender_session_id'));

        return $internal === '' || (string) $connection->provider_connection_id !== $internal;
    }

    public function snapshot()
    {
        $limit = $this->sessionLimit();
        $beyond = $this->beyondSessions();
        $customers = $this->customerSessions();
        $provisioning = $this->provisioningSessions();
        $reserved = max((int) config('cloud.whatsapp.reserved_sessions', 1), $beyond);
        $open = $this->unmatchedReservations();
        $available = $limit === null ? null : max(0, $limit - $reserved - $customers - $provisioning - $open);

        return [
            'limit' => $limit,
            'beyond' => $beyond,
            'customers' => $customers,
            'provisioning' => $provisioning,
            'reserved' => $reserved,
            'available' => $available,
        ];
    }

    public function availableCustomerSlots()
    {
        $snapshot = $this->snapshot();

        return $snapshot['available'] === null ? 0 : (int) $snapshot['available'];
    }

    /**
     * Holds one customer slot. The caller must already be inside a transaction
     * after lock() so two requests cannot take the last slot.
     */
    public function reserve(CloudTenant $tenant)
    {
        if (! $this->canProvisionWhatsApp($tenant)) {
            throw new \RuntimeException('WhatsApp connection capacity currently unavailable.');
        }
        if ($this->availableCustomerSlots() < 1) {
            throw new \RuntimeException('WhatsApp connection capacity currently unavailable.');
        }

        return DB::table('cloud_whatsapp_slot_reservations')->insertGetId([
            'cloud_tenant_id' => $tenant->id,
            'status' => 'RESERVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function lock()
    {
        if (! Schema::hasTable('cloud_whatsapp_capacity_guard')) {
            return;
        }
        DB::table('cloud_whatsapp_capacity_guard')->where('id', 1)->lockForUpdate()->first();
    }

    public function release($reservationId)
    {
        if (! $reservationId || ! Schema::hasTable('cloud_whatsapp_slot_reservations')) {
            return;
        }
        DB::table('cloud_whatsapp_slot_reservations')->where('id', $reservationId)->update([
            'status' => 'RELEASED',
            'updated_at' => now(),
        ]);
    }

    public function consume($reservationId)
    {
        if (! $reservationId || ! Schema::hasTable('cloud_whatsapp_slot_reservations')) {
            return;
        }
        DB::table('cloud_whatsapp_slot_reservations')->where('id', $reservationId)->update([
            'status' => 'CONSUMED',
            'updated_at' => now(),
        ]);
    }

    public function maskedPhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 2) {
            return '**';
        }

        return str_repeat('*', strlen($digits) - 2).substr($digits, -2);
    }

    protected function onTrial(CloudTenant $tenant)
    {
        if (! Schema::hasTable('cloud_subscriptions')) {
            return false;
        }

        $planIds = CloudPlan::where('code', 'MESSAGING_MONTHLY')->pluck('id');

        return CloudSubscription::where('cloud_tenant_id', $tenant->id)
            ->whereIn('cloud_plan_id', $planIds->all())
            ->where('status', CloudSubscriptionStatus::TRIALING)
            ->exists();
    }

    protected function unmatchedReservations()
    {
        if (! Schema::hasTable('cloud_whatsapp_slot_reservations')) {
            return 0;
        }
        $query = DB::table('cloud_whatsapp_slot_reservations')->where('status', 'RESERVED');
        if (Schema::hasTable('cloud_whatsapp_connections')) {
            $held = CloudWhatsAppConnection::whereIn('status', self::HOLDING)->pluck('cloud_tenant_id')->all();
            if (count($held) > 0) {
                $query->whereNotIn('cloud_tenant_id', $held);
            }
        }

        return $query->count();
    }

    protected function beyondSessions()
    {
        return $this->countSessions(CloudTenantType::INTERNAL, self::HOLDING);
    }

    protected function customerSessions()
    {
        return $this->countSessions(CloudTenantType::CUSTOMER, ['CONNECTED', 'ACTIVE']);
    }

    protected function provisioningSessions()
    {
        return $this->countSessions(CloudTenantType::CUSTOMER, ['PROVISIONING', 'AWAITING_QR', 'CONNECTING']);
    }

    protected function countSessions($type, array $statuses)
    {
        if (! Schema::hasTable('cloud_whatsapp_connections') || ! Schema::hasTable('cloud_tenants')) {
            return 0;
        }

        return CloudWhatsAppConnection::whereIn('status', $statuses)
            ->whereIn('cloud_tenant_id', function ($query) use ($type) {
                $query->select('id')->from('cloud_tenants')->where('type', $type);
            })
            ->count();
    }
}
