<?php

namespace App\Services\Cloud;

/**
 * Human-readable company slugs for a future /c/{slug} route.
 * Phase 1B does not register that route.
 */
class CloudSlug
{
    public static function fromName($name)
    {
        $value = mb_strtolower(trim((string) $name), 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
        $value = trim((string) $value, '-');

        return $value === '' ? 'company' : $value;
    }

    public static function reserved()
    {
        return [
            'admin', 'api', 'cloud', 'subscriptions', 'login', 'register', 'logout',
            'beyondtechworld', 'c', 'products', 'sales', 'quotations', 'bookings',
            'rentals', 'payments', 'dashboard', 'whatsapp', 'storage', 'vendor',
        ];
    }

    public static function isReserved($slug)
    {
        return in_array(strtolower((string) $slug), self::reserved(), true);
    }
}
