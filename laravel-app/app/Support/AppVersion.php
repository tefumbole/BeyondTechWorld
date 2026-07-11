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
                return $fromFile;
            }
        }

        $configured = config('app.version');
        if (!empty($configured)) {
            return $configured;
        }

        return 'BCL V.2.1.4';
    }

    public static function build()
    {
        $build = config('app.version_build');
        if (!empty($build)) {
            return $build;
        }

        if (!is_dir(base_path('.git'))) {
            return null;
        }

        $sha = @trim((string) @shell_exec('git -C ' . escapeshellarg(base_path()) . ' rev-parse --short HEAD 2>/dev/null'));

        return $sha !== '' ? $sha : null;
    }

    public static function display()
    {
        $label = self::label();
        $build = self::build();

        return $build ? $label . ' · ' . $build : $label;
    }
}
