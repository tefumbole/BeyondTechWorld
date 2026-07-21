<?php

namespace App\Support;

class AppVersion
{
    public static function label()
    {
        $path = base_path('VERSION');
        if (is_file($path)) {
            $fromFile = trim((string) file_get_contents($path));
            if ($fromFile !== '') {
                return self::normalizeSemver($fromFile);
            }
        }

        $configured = config('app.version');
        if (! empty($configured)) {
            return self::normalizeSemver($configured);
        }

        return '2.2.0';
    }

    /**
     * Display form used on login/portals: BCL V2.2.38
     */
    public static function bcl()
    {
        return 'BCL V'.self::label();
    }

    public static function build()
    {
        $build = config('app.version_build');
        if (! empty($build)) {
            return $build;
        }

        if (! is_dir(base_path('.git'))) {
            return null;
        }

        $sha = @trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --short HEAD 2>/dev/null'));

        return $sha !== '' ? $sha : null;
    }

    public static function display()
    {
        return self::bcl();
    }

    protected static function normalizeSemver($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/^BCL\s*V\.?\s*/i', '', $value);
        $value = ltrim($value, 'vV');

        return $value !== '' ? $value : '2.2.0';
    }
}
