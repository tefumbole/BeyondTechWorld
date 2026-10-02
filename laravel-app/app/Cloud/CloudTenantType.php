<?php

namespace App\Cloud;

/**
 * Machine values for cloud_tenants.type.
 * BeyondTechWorld will be INTERNAL in a later phase. Subscribing companies are CUSTOMER.
 */
class CloudTenantType
{
    const INTERNAL = 'INTERNAL';
    const CUSTOMER = 'CUSTOMER';

    public static function all()
    {
        return [self::INTERNAL, self::CUSTOMER];
    }
}
