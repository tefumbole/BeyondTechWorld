<?php

namespace App\Cloud;

/**
 * Role inside one CloudTenant. This is not the platform super admin and not Spatie ERP roles.
 * role_id on the membership row is reserved for a later link to existing RBAC.
 */
class CloudMembershipRole
{
    const OWNER = 'OWNER';
    const ADMIN = 'ADMIN';
    const STAFF = 'STAFF';

    public static function all()
    {
        return [self::OWNER, self::ADMIN, self::STAFF];
    }
}
