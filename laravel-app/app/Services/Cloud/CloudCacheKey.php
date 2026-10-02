<?php

namespace App\Services\Cloud;

class CloudCacheKey
{
    public static function make($key)
    {
        $tenant = app(CloudTenantContext::class)->get();
        $uuid = $tenant && $tenant->uuid ? $tenant->uuid : 'none';

        return 'cloud:'.$uuid.':'.$key;
    }
}
