<?php

namespace App\Cloud;

/**
 * Membership ends by status. The users row stays.
 */
class CloudMembershipStatus
{
    const INVITED = 'INVITED';
    const ACTIVE = 'ACTIVE';
    const SUSPENDED = 'SUSPENDED';
    const REMOVED = 'REMOVED';

    public static function all()
    {
        return [self::INVITED, self::ACTIVE, self::SUSPENDED, self::REMOVED];
    }
}
