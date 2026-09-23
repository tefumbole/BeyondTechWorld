<?php

namespace App\Services\Property;

class PropertyMediaGuard
{
    public function check($name, $mime, $size, $voice = false)
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        if ($name === '' || $name === '.' || strpos($name, '..') !== false) {
            return ['ok' => false, 'error' => 'bad_name'];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = strtolower(trim((string) $mime));
        $size = (int) $size;
        if ($size < 1 || $size > 8 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'bad_size'];
        }
        $images = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $audio = ['mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac'];
        $allowed = $voice ? $audio : $images;
        if (! isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            return ['ok' => false, 'error' => 'bad_type'];
        }

        return ['ok' => true, 'name' => $name, 'ext' => $ext, 'mime' => $mime, 'kind' => $voice ? 'audio' : 'image'];
    }
}
