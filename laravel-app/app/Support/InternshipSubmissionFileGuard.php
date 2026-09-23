<?php

namespace App\Support;

class InternshipSubmissionFileGuard
{
    public static function maxBytes()
    {
        $bytes = (int) config('services.whatsapp.internship_max_bytes', 20 * 1024 * 1024);

        return $bytes > 0 ? $bytes : (20 * 1024 * 1024);
    }

    /**
     * @return array{ok:bool,error:?string,name:string,extension:string}
     */
    public static function check($originalName, $mime, $size, $forVoice = false)
    {
        $name = self::safeName($originalName);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (self::unsafe($originalName) || self::unsafe($name)) {
            return ['ok' => false, 'error' => 'unsafe_type', 'name' => $name, 'extension' => $extension];
        }
        if ($size > self::maxBytes()) {
            return ['ok' => false, 'error' => 'too_large', 'name' => $name, 'extension' => $extension];
        }
        $allowedExt = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'mp3', 'ogg', 'wav', 'm4a', 'aac'];
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png', 'image/jpeg', 'image/gif', 'image/webp',
            'text/plain',
            'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/aac', 'audio/webm',
        ];
        if (! in_array($extension, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'unsafe_type', 'name' => $name, 'extension' => $extension];
        }
        $mime = strtolower(trim((string) $mime));
        if ($mime !== '' && ! in_array($mime, $allowedMime, true)) {
            return ['ok' => false, 'error' => 'unsafe_type', 'name' => $name, 'extension' => $extension];
        }
        if (! $forVoice && in_array($extension, ['mp3', 'ogg', 'wav', 'm4a', 'aac'], true)) {
            return ['ok' => false, 'error' => 'voice_not_allowed', 'name' => $name, 'extension' => $extension];
        }

        return ['ok' => true, 'error' => null, 'name' => $name, 'extension' => $extension];
    }

    public static function safeName($originalName)
    {
        $base = basename(str_replace('\\', '/', (string) $originalName));
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $base = trim((string) $base, '._');
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'upload.bin';
        }

        return substr($base, 0, 120);
    }

    public static function unsafe($name)
    {
        $name = strtolower((string) $name);
        if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            return true;
        }

        return (bool) preg_match('/\.(php|phtml|phar|exe|sh|bat|cmd|js|jar|html|htm|svg|htaccess)(\.|$)/', $name);
    }

    public static function isGithubUrl($url)
    {
        $url = trim((string) $url);
        if (strpos($url, '..') !== false) {
            return false;
        }
        if (! preg_match('#^https://github\.com/([A-Za-z0-9][A-Za-z0-9_.-]*)/([A-Za-z0-9][A-Za-z0-9_.-]*)(?:/(?:commit|pull|tree|blob)/[A-Za-z0-9_./-]+)?/?$#', $url)) {
            return false;
        }

        return true;
    }
}
