<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Writes APP_TIMEZONE only when it changes.
 * A locked environment file must not block the rest of General Settings.
 */
class AppTimezone
{
    /**
     * @param  string  $timezone
     * @param  string|null  $path
     * @return string|null  Notice when the timezone could not be stored
     */
    public static function persist($timezone, $path = null)
    {
        $timezone = trim((string) $timezone);
        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return null;
        }

        $current = self::current($path);
        if ($timezone === $current) {
            return null;
        }

        if (! EnvFile::upsert(['APP_TIMEZONE' => $timezone], $path)) {
            return 'The system name was saved. The timezone stayed the same because the server cannot update the environment file.';
        }

        if ($path) {
            return null;
        }

        putenv('APP_TIMEZONE='.$timezone);
        $_ENV['APP_TIMEZONE'] = $timezone;
        $_SERVER['APP_TIMEZONE'] = $timezone;
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            // Runtime timezone is already updated for this process.
        }

        return null;
    }

    protected static function current($path)
    {
        if ($path) {
            if (! is_file($path)) {
                return '';
            }
            $content = file_get_contents($path);
            if ($content === false || ! preg_match('/^APP_TIMEZONE\s*=\s*(.*)$/m', $content, $match)) {
                return '';
            }

            return trim($match[1], " \t\"'");
        }

        $fromFile = EnvFile::get('APP_TIMEZONE');

        return trim((string) ($fromFile !== null && $fromFile !== '' ? $fromFile : config('app.timezone')));
    }
}
