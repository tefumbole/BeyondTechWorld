<?php

namespace App\Travel;

use App\Services\Cloud\CloudTenantContext;
use Illuminate\Support\Facades\DB;

class TravelContext
{
    public static function tenantId()
    {
        $context = app(CloudTenantContext::class)->id();
        if ($context) {
            return (int) $context;
        }
        $platform = (int) config('travel.platform_tenant_id');

        return $platform > 0 ? $platform : null;
    }

    public static function enabled($tenantId)
    {
        if (! config('travel.enabled') || ! $tenantId) {
            return false;
        }

        return DB::table('travel_settings')
            ->where('cloud_tenant_id', $tenantId)
            ->where('enabled', 1)
            ->exists();
    }

    public static function allow($tenantId)
    {
        $row = DB::table('travel_settings')->where('cloud_tenant_id', $tenantId)->first();
        if ($row) {
            DB::table('travel_settings')->where('id', $row->id)->update([
                'enabled' => 1,
                'updated_at' => now(),
            ]);

            return;
        }
        DB::table('travel_settings')->insert([
            'cloud_tenant_id' => $tenantId,
            'enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
