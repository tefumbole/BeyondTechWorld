<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HotspotPackageVolumeAndPlans extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('hotspot_packages')) {
            return;
        }

        DB::statement('ALTER TABLE hotspot_packages MODIFY rate_up INT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE hotspot_packages MODIFY rate_down INT UNSIGNED NULL DEFAULT NULL');

        $location = DB::table('hotspot_locations')->where('active', 1)->orderBy('id')->first();
        $tenantId = $location ? $location->cloud_tenant_id : null;
        $now = now();
        $plans = [
            ['1 Hour', 500, 3600],
            ['1 Day', 1000, 86400],
            ['1 Week', 3000, 604800],
            ['1 Month', 10000, 2592000],
        ];

        foreach ($plans as $plan) {
            $exists = DB::table('hotspot_packages')
                ->where('name', $plan[0])
                ->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('cloud_tenant_id', $tenantId);
                })
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('hotspot_packages')->insert([
                'cloud_tenant_id' => $tenantId,
                'location_id' => null,
                'name' => $plan[0],
                'price' => $plan[1],
                'currency' => 'XAF',
                'validity_seconds' => $plan[2],
                'data_bytes' => null,
                'rate_up' => null,
                'rate_down' => null,
                'shared_users' => 5,
                'simultaneous_sessions' => 5,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('hotspot_packages')) {
            return;
        }
        DB::table('hotspot_packages')->whereNull('rate_up')->update(['rate_up' => 1024]);
        DB::table('hotspot_packages')->whereNull('rate_down')->update(['rate_down' => 2048]);
        DB::statement('ALTER TABLE hotspot_packages MODIFY rate_up INT UNSIGNED NOT NULL DEFAULT 1024');
        DB::statement('ALTER TABLE hotspot_packages MODIFY rate_down INT UNSIGNED NOT NULL DEFAULT 2048');
    }
}
