<?php

namespace App\Support;

/**
 * Turns a local file into a path on this site's public address.
 * WhatsApp media templates only allow the path after the website to change.
 */
class TwilioMedia
{
    public static function baseUrl()
    {
        $base = trim((string) config('services.whatsapp.media_base_url', 'https://beyondtechworld.com'));

        return rtrim($base !== '' ? $base : 'https://beyondtechworld.com', '/');
    }

    public static function relativePublicPath($localPath)
    {
        if (! is_file($localPath)) {
            return '';
        }
        $real = realpath($localPath);
        if ($real === false) {
            return '';
        }

        $publicRoot = realpath(public_path());
        if ($publicRoot && strpos($real, $publicRoot.'/') === 0) {
            return self::encode(substr($real, strlen($publicRoot) + 1));
        }

        $storagePublic = realpath(storage_path('app/public'));
        if ($storagePublic && strpos($real, $storagePublic.'/') === 0) {
            return self::encode('storage/'.substr($real, strlen($storagePublic) + 1));
        }

        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($real));
        if ($name === '' || strpos($name, '.') === false) {
            return '';
        }
        $dir = public_path('wa-media/'.bin2hex(random_bytes(8)));
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return '';
        }
        $dest = $dir.'/'.$name;
        if (! @copy($real, $dest)) {
            return '';
        }
        $publicRoot = realpath(public_path());
        $destReal = realpath($dest);
        if (! $publicRoot || ! $destReal || strpos($destReal, $publicRoot.'/') !== 0) {
            return '';
        }

        return self::encode(substr($destReal, strlen($publicRoot) + 1));
    }

    protected static function encode($relative)
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $relative)), function ($part) {
            return $part !== '';
        }));
        if (count($parts) === 0) {
            return '';
        }

        return implode('/', array_map('rawurlencode', $parts));
    }
}
