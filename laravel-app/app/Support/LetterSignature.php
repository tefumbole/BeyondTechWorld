<?php

namespace App\Support;

class LetterSignature
{
    public static function storeFromDataUrl(string $dataUrl, string $prefix = 'letter_sig'): ?string
    {
        if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            return null;
        }

        $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        imagesavealpha($src, true);
        imagealphablending($src, false);
        $src = self::trimImage($src);
        $src = self::stampDate($src, date('M d, Y'));

        $dir = public_path('letter/signatures');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $prefix . '_' . date('YmdHis') . '_' . uniqid() . '.png';
        imagepng($src, $dir . '/' . $filename);
        imagedestroy($src);

        return $filename;
    }

    public static function url(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        return url('public/letter/signatures/' . $filename);
    }

    public static function path(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        $path = public_path('letter/signatures/' . $filename);

        return is_file($path) ? $path : null;
    }

    public static function resolveEditSrc($letter, $user = null): ?string
    {
        if (!empty($letter->edit_signature)) {
            return self::url($letter->edit_signature);
        }

        if ($user && !empty($user->stemp)) {
            return url('public/images/user/' . $user->stemp);
        }

        return null;
    }

    public static function resolveApproveSrc($letter, $user = null): ?string
    {
        if (!empty($letter->approve_signature)) {
            return self::url($letter->approve_signature);
        }

        if ($user && !empty($user->approve)) {
            return url('public/images/user/' . $user->approve);
        }

        return null;
    }

    public static function resolveSignSrc($letter, $user = null): ?string
    {
        if (!empty($letter->sign_signature)) {
            return self::url($letter->sign_signature);
        }

        if ($user && !empty($user->sign)) {
            return url('public/images/user/' . $user->sign);
        }

        return null;
    }

    private static function trimImage($img)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $top = $height;
        $left = $width;
        $bottom = 0;
        $right = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 120) {
                    if ($x < $left) {
                        $left = $x;
                    }
                    if ($x > $right) {
                        $right = $x;
                    }
                    if ($y < $top) {
                        $top = $y;
                    }
                    if ($y > $bottom) {
                        $bottom = $y;
                    }
                }
            }
        }

        if ($right <= $left || $bottom <= $top) {
            return $img;
        }

        $newWidth = $right - $left + 1;
        $newHeight = $bottom - $top + 1;
        $trimmed = imagecreatetruecolor($newWidth, $newHeight);
        imagesavealpha($trimmed, true);
        $transparent = imagecolorallocatealpha($trimmed, 0, 0, 0, 127);
        imagefill($trimmed, 0, 0, $transparent);
        imagecopy($trimmed, $img, 0, 0, $left, $top, $newWidth, $newHeight);
        imagedestroy($img);

        return $trimmed;
    }

    private static function stampDate($img, string $date)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $font = 2;
        $textHeight = imagefontheight($font);
        $newHeight = $height + $textHeight + 2;
        $new = imagecreatetruecolor($width, $newHeight);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopy($new, $img, 0, 0, 0, 0, $width, $height);
        $textColor = imagecolorallocate($new, 40, 40, 40);
        $textWidth = imagefontwidth($font) * strlen($date);
        $x = max(0, (int)(($width - $textWidth) / 2));
        imagestring($new, $font, $x, $height + 1, $date, $textColor);
        imagedestroy($img);

        return $new;
    }
}
