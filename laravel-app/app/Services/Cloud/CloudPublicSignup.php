<?php

namespace App\Services\Cloud;

/**
 * Public company signup switch.
 * A file in storage overrides config so operations can close signup
 * without deploying code or rebuilding the config cache.
 * Missing file: use config('cloud.public_onboarding'), which defaults to closed.
 */
class CloudPublicSignup
{
    public static function path()
    {
        return storage_path('app/cloud-public-signup');
    }

    public static function open()
    {
        $path = self::path();
        if (is_file($path)) {
            return trim((string) file_get_contents($path)) === '1';
        }

        return (bool) config('cloud.public_onboarding');
    }

    public static function set($open)
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Public signup could not be updated.');
        }
        if (file_put_contents($path, $open ? '1' : '0') === false) {
            throw new \RuntimeException('Public signup could not be updated.');
        }
        @chmod($path, 0640);
    }
}
