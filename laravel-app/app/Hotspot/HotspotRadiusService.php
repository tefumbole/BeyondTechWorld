<?php

namespace App\Hotspot;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HotspotRadiusService
{
    public function sync(HotspotSubscription $subscription, HotspotPackage $package)
    {
        if (! Schema::hasTable('radcheck') || ! Schema::hasTable('radreply')) {
            return;
        }
        $secret = $subscription->access_secret;
        if (! $secret) {
            return;
        }
        $username = $subscription->username;
        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();
        if (Schema::hasTable('radusergroup')) {
            DB::table('radusergroup')->where('username', $username)->delete();
            DB::table('radusergroup')->insert([
                'username' => $username,
                'groupname' => 'hotspot',
                'priority' => 1,
            ]);
        }
        DB::table('radcheck')->insert([
            'username' => $username,
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => $secret,
        ]);
        $replies = [
            ['Mikrotik-Rate-Limit', HotspotLimits::rateLimit($package->rate_up, $package->rate_down)],
            ['Session-Timeout', (string) (int) $package->validity_seconds],
            ['Simultaneous-Use', (string) max(1, (int) $package->simultaneous_sessions)],
        ];
        if ($package->data_bytes) {
            $replies[] = ['Mikrotik-Total-Limit', (string) (int) $package->data_bytes];
        }
        foreach ($replies as $reply) {
            DB::table('radreply')->insert([
                'username' => $username,
                'attribute' => $reply[0],
                'op' => ':=',
                'value' => $reply[1],
            ]);
        }
    }

    public function revoke($username)
    {
        if (Schema::hasTable('radcheck')) {
            DB::table('radcheck')->where('username', $username)->delete();
        }
        if (Schema::hasTable('radreply')) {
            DB::table('radreply')->where('username', $username)->delete();
        }
        if (Schema::hasTable('radusergroup')) {
            DB::table('radusergroup')->where('username', $username)->delete();
        }
    }
}
