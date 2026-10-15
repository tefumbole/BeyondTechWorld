<?php

namespace App\Hotspot;

use App\Jobs\ProvisionHotspotAccess;

class HotspotProvisioner
{
    protected $routers;
    protected $radius;

    public function __construct(HotspotRouterService $routers, HotspotRadiusService $radius)
    {
        $this->routers = $routers;
        $this->radius = $radius;
    }

    public function grant(HotspotSubscription $subscription)
    {
        $package = HotspotPackage::find($subscription->package_id);
        if (! $package) {
            return;
        }
        try {
            $this->radius->sync($subscription, $package);
        } catch (\Throwable $e) {
            HotspotAuditLog::write('radius.fail', substr($e->getMessage(), 0, 180));
        }
        $router = $this->routerFor($subscription);
        if (! $router) {
            $subscription->provision_error = 'No router is saved for this location yet.';
            $subscription->save();

            return;
        }
        try {
            $this->routers->pushUser($router, $subscription, $package);
        } catch (\Throwable $e) {
            $subscription->provision_error = substr($e->getMessage(), 0, 180);
            $subscription->save();
            $router->status = 'offline';
            $router->last_error = $subscription->provision_error;
            $router->save();
            ProvisionHotspotAccess::dispatch($subscription->id);
            HotspotAuditLog::write('provision.retry', $subscription->provision_error, $router->id);
        }
    }

    public function retry(HotspotSubscription $subscription)
    {
        if ($subscription->state !== 'active') {
            return;
        }
        $package = HotspotPackage::find($subscription->package_id);
        $router = $this->routerFor($subscription);
        if (! $package || ! $router) {
            throw new HotspotLinkException('The package or router is missing.');
        }
        $this->radius->sync($subscription, $package);
        $this->routers->pushUser($router, $subscription, $package);
    }

    public function revoke(HotspotSubscription $subscription)
    {
        try {
            $this->radius->revoke($subscription->username);
        } catch (\Throwable $e) {
        }
        $router = $subscription->router_id
            ? HotspotRouter::find($subscription->router_id)
            : $this->routerFor($subscription);
        if (! $router) {
            return;
        }
        $this->routers->removeUser($router, $subscription->username);
    }

    public function routerFor(HotspotSubscription $subscription)
    {
        $query = HotspotRouter::query()->orderBy('id');
        if ($subscription->location_id) {
            $query->where('location_id', $subscription->location_id);
        }

        return $query->first();
    }
}
