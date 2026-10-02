<?php

namespace App\Cloud;

/**
 * Status of one company's subscription to one plan.
 * Separate from CloudTenantStatus.
 */
class CloudSubscriptionStatus
{
    const TRIALING = 'TRIALING';
    const ACTIVE = 'ACTIVE';
    const PAST_DUE = 'PAST_DUE';
    const SUSPENDED = 'SUSPENDED';
    const CANCELLED = 'CANCELLED';
    const EXPIRED = 'EXPIRED';

    public static function all()
    {
        return [
            self::TRIALING,
            self::ACTIVE,
            self::PAST_DUE,
            self::SUSPENDED,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }
}
