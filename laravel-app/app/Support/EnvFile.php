<?php

namespace App\Support;

class EnvFile
{
    /**
     * Upsert KEY=value pairs in the .env file (create missing keys, replace existing).
     *
     * @param  array<string,string|int|bool|null>  $pairs
     * @return bool
     */
    public static function upsert(array $pairs, $path = null)
    {
        $path = $path ?: base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        @copy($path, $path.'.backup.'.date('Ymd_His'));

        foreach ($pairs as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }

            $formatted = self::formatValue($value);
            $line = $key.'='.$formatted;
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }

        return file_put_contents($path, $content) !== false;
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    public static function formatValue($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }

    /**
     * Read a raw env value from the .env file (not cached config).
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return $default;
        }

        if (! preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.*)$/m', $content, $m)) {
            return $default;
        }

        $raw = trim($m[1]);
        if ($raw === '') {
            return '';
        }

        if ((substr($raw, 0, 1) === '"' && substr($raw, -1) === '"')
            || (substr($raw, 0, 1) === "'" && substr($raw, -1) === "'")) {
            return stripcslashes(substr($raw, 1, -1));
        }

        return $raw;
    }
}
