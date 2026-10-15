<?php

namespace App\Hotspot;

class HotspotRouterService
{
    protected $client;

    public function __construct(RouterOsClient $client)
    {
        $this->client = $client;
    }

    public function test(HotspotRouter $router)
    {
        try {
            $rows = $this->talk($router, ['/system/identity/print']);
            $identity = '';
            $version = $router->ros_version;
            foreach ($rows as $row) {
                if (! empty($row['name'])) {
                    $identity = $row['name'];
                }
                if (isset($row['trap'])) {
                    throw new HotspotLinkException($row['trap']);
                }
            }
            try {
                foreach ($this->talk($router, ['/system/resource/print']) as $row) {
                    if (! empty($row['version'])) {
                        $version = $row['version'];
                    }
                }
            } catch (\Throwable $e) {
            }
            $router->status = 'online';
            $router->last_seen_at = now();
            $router->last_error = null;
            if ($identity !== '') {
                $router->identity = $identity;
            }
            if ($version) {
                $router->ros_version = $version;
            }
            $router->save();
            HotspotAuditLog::write('router.test', 'Connection test succeeded for '.$router->name, $router->id);

            return true;
        } catch (\Throwable $e) {
            $router->status = 'offline';
            $router->last_error = substr($e->getMessage(), 0, 180);
            $router->save();
            HotspotAuditLog::write('router.test', 'Connection test failed: '.$router->last_error, $router->id);

            return false;
        }
    }

    public function activeSessions(HotspotRouter $router)
    {
        $rows = $this->talk($router, ['/ip/hotspot/active/print']);
        $seen = [];
        foreach ($rows as $row) {
            if (isset($row['trap']) || empty($row['user'])) {
                continue;
            }
            $sessionId = ! empty($row['session-id']) ? $row['session-id'] : (! empty($row['.id']) ? $row['.id'] : $row['user']);
            $seen[] = $sessionId;
            $bytesIn = isset($row['bytes-in']) ? (int) $row['bytes-in'] : 0;
            $bytesOut = isset($row['bytes-out']) ? (int) $row['bytes-out'] : 0;
            $uptime = HotspotLimits::uptimeSeconds(isset($row['uptime']) ? $row['uptime'] : '');
            $subscription = HotspotSubscription::where('username', $row['user'])->orderByDesc('id')->first();
            $session = HotspotSession::firstOrNew([
                'router_id' => $router->id,
                'acct_session_id' => $sessionId,
            ]);
            $session->username = $row['user'];
            $session->mac = isset($row['mac-address']) ? $row['mac-address'] : null;
            $session->framed_ip = isset($row['address']) ? $row['address'] : null;
            $session->bytes_in = $bytesIn;
            $session->bytes_out = $bytesOut;
            $session->uptime_seconds = $uptime;
            $session->stopped_at = null;
            if (! $session->started_at) {
                $session->started_at = now()->subSeconds($uptime);
            }
            if ($subscription) {
                $session->subscription_id = $subscription->id;
                $subscription->data_used_bytes = max((int) $subscription->data_used_bytes, $bytesIn + $bytesOut);
                $subscription->connected_seconds = max((int) $subscription->connected_seconds, $uptime);
                $subscription->save();
            }
            $session->save();
        }
        HotspotSession::where('router_id', $router->id)->whereNull('stopped_at')->whereNotIn('acct_session_id', $seen ?: ['__none__'])->update(['stopped_at' => now()]);
        $router->status = 'online';
        $router->last_seen_at = now();
        $router->last_error = null;
        $router->save();

        return HotspotSession::where('router_id', $router->id)->whereNull('stopped_at')->orderByDesc('id')->get();
    }

    public function disconnect(HotspotRouter $router, $username)
    {
        $rows = $this->talk($router, ['/ip/hotspot/active/print', '?user='.$username]);
        foreach ($rows as $row) {
            if (empty($row['.id'])) {
                continue;
            }
            $this->talk($router, ['/ip/hotspot/active/remove', '=.id='.$row['.id']]);
        }
        HotspotAuditLog::write('session.disconnect', 'Disconnected '.$username, $router->id);
    }

    public function pushProfile(HotspotRouter $router, HotspotPackage $package)
    {
        $name = HotspotLimits::profileName($package->id);
        $rate = HotspotLimits::rateLimit($package->rate_up, $package->rate_down);
        $existing = $this->findId($router, '/ip/hotspot/user/profile', $name);
        $words = $existing
            ? ['/ip/hotspot/user/profile/set', '=.id='.$existing, '=rate-limit='.$rate, '=shared-users='.(int) $package->shared_users]
            : ['/ip/hotspot/user/profile/add', '=name='.$name, '=rate-limit='.$rate, '=shared-users='.(int) $package->shared_users];
        $this->assertOk($this->talk($router, $words));
        $package->profile_pushed_at = now();
        $package->save();
        HotspotAuditLog::write('package.profile', 'Pushed rate profile '.$name.' to '.$router->name, $router->id);
    }

    public function pushUser(HotspotRouter $router, HotspotSubscription $subscription, HotspotPackage $package)
    {
        $this->pushProfile($router, $package);
        $name = $subscription->username;
        $secret = $subscription->access_secret;
        if (! $secret) {
            throw new HotspotLinkException('This subscription has no access password.');
        }
        $limit = HotspotLimits::duration($package->validity_seconds);
        $existing = $this->findId($router, '/ip/hotspot/user', $name);
        $profile = HotspotLimits::profileName($package->id);
        $words = $existing
            ? ['/ip/hotspot/user/set', '=.id='.$existing, '=password='.$secret, '=profile='.$profile, '=limit-uptime='.$limit]
            : ['/ip/hotspot/user/add', '=name='.$name, '=password='.$secret, '=profile='.$profile, '=limit-uptime='.$limit, '=comment=beyond-hotspot'];
        $this->assertOk($this->talk($router, $words));
        $subscription->router_id = $router->id;
        $subscription->provision_error = null;
        $subscription->save();
    }

    public function removeUser(HotspotRouter $router, $username)
    {
        try {
            $this->disconnect($router, $username);
        } catch (\Throwable $e) {
        }
        $existing = $this->findId($router, '/ip/hotspot/user', $username);
        if ($existing) {
            $this->assertOk($this->talk($router, ['/ip/hotspot/user/remove', '=.id='.$existing]));
        }
    }

    protected function findId(HotspotRouter $router, $path, $name)
    {
        foreach ($this->talk($router, [$path.'/print', '?name='.$name]) as $row) {
            if (! empty($row['.id']) && isset($row['name']) && $row['name'] === $name) {
                return $row['.id'];
            }
        }

        return null;
    }

    protected function talk(HotspotRouter $router, array $words)
    {
        if (! HotspotLimits::acceptsManagementHost($router->api_host)) {
            throw new HotspotLinkException('The management address must be a tunnel or private IP.');
        }
        $password = $router->api_password;
        if (! $password) {
            throw new HotspotLinkException('Save the API password before testing the link.');
        }

        return $this->client->command($router->api_host, $router->api_port ?: 8728, $router->api_user, $password, $words);
    }

    protected function assertOk(array $rows)
    {
        foreach ($rows as $row) {
            if (isset($row['trap'])) {
                throw new HotspotLinkException($row['trap']);
            }
        }
    }
}
