<?php

namespace App\Hotspot;

use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudTenantContext;

class HotspotEnforcer
{
    public function run()
    {
        $context = app(CloudTenantContext::class);
        $ids = $context->withoutIsolation(function () {
            return HotspotSubscription::where('state', 'active')->pluck('cloud_tenant_id', 'id');
        });
        foreach ($ids as $subscriptionId => $tenantId) {
            $tenant = $tenantId ? CloudTenant::find($tenantId) : null;
            if ($tenant) {
                $context->set($tenant);
            }
            $subscription = HotspotSubscription::find($subscriptionId);
            if ($subscription) {
                $this->check($subscription);
            }
        }
    }

    public function retryOffline()
    {
        $context = app(CloudTenantContext::class);
        $ids = $context->withoutIsolation(function () {
            return HotspotSubscription::where('state', 'active')->whereNotNull('provision_error')->pluck('cloud_tenant_id', 'id');
        });
        foreach ($ids as $subscriptionId => $tenantId) {
            $tenant = $tenantId ? CloudTenant::find($tenantId) : null;
            if ($tenant) {
                $context->set($tenant);
            }
            $subscription = HotspotSubscription::find($subscriptionId);
            if (! $subscription) {
                continue;
            }
            try {
                app(HotspotProvisioner::class)->retry($subscription);
            } catch (\Throwable $e) {
                $subscription->provision_error = substr($e->getMessage(), 0, 180);
                $subscription->save();
            }
        }
    }

    public function check(HotspotSubscription $subscription)
    {
        $reason = HotspotLimits::endReason(
            $subscription->expires_at,
            now(),
            $subscription->data_used_bytes,
            $subscription->data_cap_bytes
        );
        if ($reason) {
            $this->close($subscription, $reason);

            return;
        }
        $template = HotspotMessageTemplate::where('key', 'reminder')->first();
        $hours = $template ? (int) $template->hours_before : 1;
        if ($hours > 0 && $subscription->expires_at && ! $subscription->reminder_sent_at) {
            $remindAt = $subscription->expires_at->copy()->subHours($hours);
            if (now()->greaterThanOrEqualTo($remindAt)) {
                app(HotspotNotifier::class)->once($subscription, 'reminder', 'reminder_sent_at');
            }
        }
    }

    public function close(HotspotSubscription $subscription, $reason)
    {
        if ($subscription->state !== 'active') {
            return;
        }
        $subscription->state = 'expired';
        $subscription->save();
        try {
            app(HotspotProvisioner::class)->revoke($subscription);
        } catch (\Throwable $e) {
            \App\Jobs\ProvisionHotspotAccess::dispatch($subscription->id, true);
        }
        $column = $reason === 'data' ? 'cap_sent_at' : 'expiry_sent_at';
        $key = $reason === 'data' ? 'data_cap' : 'expiry';
        app(HotspotNotifier::class)->once($subscription, $key, $column);
        HotspotAuditLog::write('subscription.expire', $subscription->username.' ended ('.$reason.')', $subscription->router_id);
    }

    public function refreshUsage()
    {
        $context = app(CloudTenantContext::class);
        $routers = $context->withoutIsolation(function () {
            return HotspotRouter::all();
        });
        foreach ($routers as $router) {
            $tenant = $router->cloud_tenant_id ? CloudTenant::find($router->cloud_tenant_id) : null;
            if ($tenant) {
                $context->set($tenant);
            }
            try {
                app(HotspotRouterService::class)->activeSessions($router);
                $open = HotspotSubscription::where('state', 'active')->get();
                foreach ($open as $subscription) {
                    $this->check($subscription);
                }
            } catch (\Throwable $e) {
                $router->status = 'offline';
                $router->last_error = substr($e->getMessage(), 0, 180);
                $router->save();
            }
        }
    }
}
