<?php

namespace App\Services\Voice;

/**
 * MAI Voice on/off switch.
 * A file in storage overrides config so the line can be closed without a deploy.
 * Missing file: config('voice.enabled'), which defaults to off.
 */
class MaiVoiceSwitch
{
    public static function path()
    {
        return storage_path('app/mai-voice');
    }

    public static function open()
    {
        $path = self::path();
        if (is_file($path)) {
            return trim((string) file_get_contents($path)) === '1';
        }

        return (bool) config('voice.enabled');
    }

    public static function set($open)
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('MAI Voice could not be updated.');
        }
        if (file_put_contents($path, $open ? '1' : '0') === false) {
            throw new \RuntimeException('MAI Voice could not be updated.');
        }
        @chmod($path, 0640);
    }
}
