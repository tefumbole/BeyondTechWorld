<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\DB;

/**
 * Append-only segment ledger.
 * CREDIT and REFUND add units. RESERVE subtracts units while a send is in flight.
 * RELEASE adds those units back when the provider does not accept the message.
 * A CONSUMED row uses 0 units so the hold is not charged twice.
 * Available balance is the sum of units. The account row exists only so a send can lock it.
 */
class SmsCreditLedger
{
    public function available($tenantId)
    {
        return (int) DB::table('cloud_sms_ledger')->where('cloud_tenant_id', $tenantId)->sum('units');
    }

    public function grantTest($tenantId, $units, $actorUserId, $reason)
    {
        if (app()->environment('production') || ! config('messaging.sms.allow_test_credits')) {
            throw new \RuntimeException('Test SMS credits are not available.');
        }
        $units = (int) $units;
        if ($units < 1) {
            throw new \InvalidArgumentException('Credit units must be positive.');
        }
        $this->ensureAccount($tenantId);
        $this->append($tenantId, 'CREDIT', $units, $reason, 'test-credit', $actorUserId, null);
    }

    public function reserve($tenantId, $units, $reference, $notificationId)
    {
        $units = (int) $units;
        if ($units < 1) {
            throw new \InvalidArgumentException('A message must use at least one segment.');
        }

        return DB::transaction(function () use ($tenantId, $units, $reference, $notificationId) {
            $this->ensureAccount($tenantId);
            DB::table('cloud_sms_accounts')->where('cloud_tenant_id', $tenantId)->lockForUpdate()->first();
            $held = DB::table('cloud_sms_ledger')
                ->where('cloud_tenant_id', $tenantId)
                ->where('reference', $reference)
                ->where('kind', 'RESERVE')
                ->exists();
            if ($held) {
                return false;
            }
            if ($this->available($tenantId) < $units) {
                throw new InsufficientSmsCredit('SMS credit is not enough for this message.');
            }
            $this->append($tenantId, 'RESERVE', -$units, 'send', $reference, null, $notificationId);

            return true;
        });
    }

    public function release($tenantId, $units, $reference, $notificationId)
    {
        $done = DB::table('cloud_sms_ledger')
            ->where('cloud_tenant_id', $tenantId)
            ->where('reference', $reference)
            ->where('kind', 'RELEASE')
            ->exists();
        if ($done) {
            return;
        }
        $this->append($tenantId, 'RELEASE', (int) $units, 'not_accepted', $reference, null, $notificationId);
    }

    public function markConsumed($tenantId, $reference, $notificationId)
    {
        $done = DB::table('cloud_sms_ledger')
            ->where('cloud_tenant_id', $tenantId)
            ->where('reference', $reference)
            ->where('kind', 'CONSUMED')
            ->exists();
        if ($done) {
            return;
        }
        $this->append($tenantId, 'CONSUMED', 0, 'provider_accepted', $reference, null, $notificationId);
    }

    protected function ensureAccount($tenantId)
    {
        $exists = DB::table('cloud_sms_accounts')->where('cloud_tenant_id', $tenantId)->exists();
        if (! $exists) {
            DB::table('cloud_sms_accounts')->insert([
                'cloud_tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function append($tenantId, $kind, $units, $reason, $reference, $actorUserId, $notificationId)
    {
        DB::table('cloud_sms_ledger')->insert([
            'cloud_tenant_id' => $tenantId,
            'kind' => $kind,
            'units' => (int) $units,
            'reason' => $reason,
            'reference' => $reference,
            'actor_user_id' => $actorUserId,
            'notification_id' => $notificationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
