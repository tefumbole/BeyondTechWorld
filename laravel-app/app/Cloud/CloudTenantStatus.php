<?php

namespace App\Cloud;

/**
 * Company workspace status. This is not a subscription status.
 * A company can be ACTIVE while one of its module subscriptions is EXPIRED.
 */
class CloudTenantStatus
{
    const PENDING = 'PENDING';
    const ACTIVE = 'ACTIVE';
    const SUSPENDED = 'SUSPENDED';
    const CANCELLED = 'CANCELLED';

    public static function all()
    {
        return [self::PENDING, self::ACTIVE, self::SUSPENDED, self::CANCELLED];
    }
}
