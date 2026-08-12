<?php

namespace App\Support;

/**
 * BeyondTechWorld nginx root is laravel-app/ (not public/), so browser-facing
 * static assets must be under /public/... (e.g. /public/images/...).
 */
final class OnlineInvitationUrl
{
    public static function ensurePublicInAppUrl(string $url, ?string $appUrl = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        // Relative path: /images/... → /public/images/...
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            if (preg_match('#^/public(/|$)#i', $url)) {
                return $url;
            }

            return '/public'.(substr($url, 0, 1) === '/' ? $url : '/'.$url);
        }

        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $appUrl = trim((string) ($appUrl ?? env('APP_URL')));
        if ($appUrl === '' || ! preg_match('#^https?://#i', $appUrl)) {
            return $url;
        }

        $appParts = @parse_url($appUrl) ?: null;
        $urlParts = @parse_url($url) ?: null;
        if (! $appParts || ! $urlParts) {
            return $url;
        }

        $appHost = strtolower((string) ($appParts['host'] ?? ''));
        $urlHost = strtolower((string) ($urlParts['host'] ?? ''));
        $appHost = preg_replace('/^www\./i', '', $appHost);
        $urlHost = preg_replace('/^www\./i', '', $urlHost);
        if ($appHost === '' || $urlHost === '' || $appHost !== $urlHost) {
            return $url;
        }

        $urlPath = (string) ($urlParts['path'] ?? '');
        if ($urlPath === '' || preg_match('#^/public(/|$)#i', $urlPath)) {
            return $url;
        }

        $urlParts['path'] = '/public'.(substr($urlPath, 0, 1) === '/' ? $urlPath : '/'.$urlPath);

        $scheme = (string) ($urlParts['scheme'] ?? 'https');
        $host = (string) ($urlParts['host'] ?? '');
        $port = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
        $user = (string) ($urlParts['user'] ?? '');
        $pass = isset($urlParts['pass']) ? ':'.$urlParts['pass'] : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';
        $path = (string) ($urlParts['path'] ?? '');
        $query = isset($urlParts['query']) ? '?'.$urlParts['query'] : '';
        $fragment = isset($urlParts['fragment']) ? '#'.$urlParts['fragment'] : '';

        return $scheme.'://'.$auth.$host.$port.$path.$query.$fragment;
    }

    /** Absolute public URL for a path under laravel-app/public/. */
    public static function publicAsset(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        if (strpos($relativePath, 'public/') === 0) {
            $relativePath = substr($relativePath, 7);
        }
        $base = rtrim((string) env('APP_URL'), '/');

        return self::ensurePublicInAppUrl($base.'/'.$relativePath);
    }
}
