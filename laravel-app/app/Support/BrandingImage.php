<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Store and fit branding uploads (site logo, letterhead bands, watermark)
 * so oversized phone photos / design exports do not break the UI or PDF layout.
 */
class BrandingImage
{
    /**
     * Recommended max dimensions for each branding slot.
     * Header/footer are wide letterhead bands; logo and watermark are square-ish.
     */
    const LIMITS = [
        'site_logo' => ['max_w' => 400, 'max_h' => 400],
        'email_header' => ['max_w' => 1400, 'max_h' => 240],
        'email_footer' => ['max_w' => 1400, 'max_h' => 200],
        'email_water_mark' => ['max_w' => 800, 'max_h' => 800],
    ];

    /**
     * Move an uploaded branding image into $destDir, resized to fit the slot.
     * Always writes PNG (preserves transparency) except for GIF/JPEG sources
     * that stay in their original format when no resize is needed.
     *
     * @param  UploadedFile  $file
     * @param  string  $destDir  Absolute directory (e.g. public_path('logo'))
     * @param  string  $slot     One of the LIMITS keys
     * @param  string|null  $filename  Optional basename; generated if null
     * @return string  Stored filename (basename only)
     */
    public static function storeFitted(UploadedFile $file, $destDir, $slot, $filename = null)
    {
        if (! is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $limits = self::LIMITS[$slot] ?? ['max_w' => 1400, 'max_h' => 1400];
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            $ext = 'png';
        }

        $base = $filename ? pathinfo($filename, PATHINFO_FILENAME) : (date('YmdHis').'_'.$slot);
        $filename = $base.'.'.$ext;

        $tmp = $file->getRealPath();
        if (! $tmp || ! is_file($tmp) || ! function_exists('imagecreatetruecolor')) {
            $file->move($destDir, $filename);

            return $filename;
        }

        $size = @getimagesize($tmp);
        if (! $size || empty($size[0]) || empty($size[1])) {
            $file->move($destDir, $filename);

            return $filename;
        }

        $srcW = (int) $size[0];
        $srcH = (int) $size[1];
        $maxW = (int) $limits['max_w'];
        $maxH = (int) $limits['max_h'];

        $needsResize = $srcW > $maxW || $srcH > $maxH;
        if (! $needsResize) {
            $file->move($destDir, $filename);

            return $filename;
        }

        $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        try {
            switch ($size[2]) {
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp); break;
                case IMAGETYPE_GIF:  $src = @imagecreatefromgif($tmp); break;
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
                default: $src = false;
            }
            if (! $src) {
                $file->move($destDir, $filename);

                return $filename;
            }

            // Prefer PNG for logo/watermark so transparency is kept; bands can stay JPEG.
            $outExt = in_array($slot, ['site_logo', 'email_water_mark'], true)
                ? 'png'
                : (in_array($ext, ['jpg', 'jpeg'], true) ? 'jpg' : 'png');
            $filename = $base.'.'.$outExt;
            $dest = rtrim($destDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

            $dst = imagecreatetruecolor($dstW, $dstH);
            if ($outExt === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            } else {
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagealphablending($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            $ok = $outExt === 'jpg'
                ? @imagejpeg($dst, $dest, 88)
                : @imagepng($dst, $dest, 6);

            imagedestroy($src);
            imagedestroy($dst);

            if ($ok && is_file($dest)) {
                @chmod($dest, 0664);

                return $filename;
            }
        } catch (\Throwable $e) {
            // fall through to raw move
        }

        $file->move($destDir, $filename);

        return $filename;
    }
}
