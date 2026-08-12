<?php

namespace App\Support;

/**
 * BeyondTechWorld serves the Laravel public/ directory as the web root,
 * so asset URLs do not need a /public path prefix (unlike some Hostinger layouts).
 */
final class OnlineInvitationUrl
{
    public static function ensurePublicInAppUrl(string $url, ?string $appUrl = null): string
    {
        return trim($url);
    }
}
