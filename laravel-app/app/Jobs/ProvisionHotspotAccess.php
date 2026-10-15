<?php

namespace App\Jobs;

use App\Cloud\CloudTenant;
use App\Hotspot\HotspotProvisioner;
use App\Hotspot\HotspotSubscription;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionHotspotAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 12;

    public $timeout = 40;

    protected $subscriptionId;

    protected $revoke;

    public function __construct($subscriptionId, $revoke = false)
    {
        $this->subscriptionId = $subscriptionId;
        $this->revoke = $revoke;
        $this->onConnection('database')->onQueue('hotspot');
    }

    public function retryAfter()
    {
        return 60;
    }

    public function handle(HotspotProvisioner $provisioner, CloudTenantContext $context)
    {
        $subscription = $context->withoutIsolation(function () {
            return HotspotSubscription::find($this->subscriptionId);
        });
        if (! $subscription) {
            return;
        }
        $tenant = $subscription->cloud_tenant_id ? CloudTenant::find($subscription->cloud_tenant_id) : null;
        if ($tenant) {
            $context->set($tenant);
        }
        if ($this->revoke || $subscription->state !== 'active') {
            $provisioner->revoke($subscription);

            return;
        }
        $provisioner->retry($subscription);
    }
}
