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
}
