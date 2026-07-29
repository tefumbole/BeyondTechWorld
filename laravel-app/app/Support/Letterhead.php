<?php

namespace App\Support;

use App\GeneralSetting;

/**
 * Resolves Beyond letterhead images (header / footer / watermark)
 * for quotations, letters, and PDFs.
 *
 * Prefer General Settings uploads, then committed Beyond branding assets.
 * Never fall back to Alpha Bridge / apps/api system-assets letterheads.
 */
class Letterhead
{
    const BEYOND_HEADER = 'beyond-letterhead-header.png';
    const BEYOND_FOOTER = 'beyond-letterhead-footer.png';

    /**
     * Variables for quotation browser views (call in the parent Blade).
     */
    public static function viewVars()
    {
        $letterhead = self::ensureSynced();

        return [
            'letterhead' => $letterhead,
            'quotationLetterhead' => ! empty($letterhead['has_header']),
            'quotationLetterFooter' => ! empty($letterhead['has_footer']),
            'quotationWatermark' => $letterhead['watermark_file'] ?? null,
            'quotationHeaderUrl' => $letterhead['header_url'] ?? null,
            'quotationFooterUrl' => $letterhead['footer_url'] ?? null,
            'quotationWatermarkUrl' => $letterhead['watermark_url'] ?? null,
        ];
    }

    /**
     * Height / width of a letterhead band, so PDF page margins can reserve the
     * exact space a full-width header or footer image will occupy.
     *
     * @param  string|null  $path
     * @param  float  $fallback
     * @return float
     */
    public static function ratio($path, $fallback = 0.15)
    {
        if (! $path || ! is_file($path)) {
            return $fallback;
        }

        $size = @getimagesize($path);
        if (! $size || empty($size[0]) || empty($size[1])) {
            return $fallback;
        }

        return $size[1] / $size[0];
    }

    /**
     * A print-resolution copy of a letterhead image for embedding in PDFs.
     *
     * Uploaded branding is often far larger than a PDF needs (the watermark has
     * been seen at 6501px wide), and dompdf embeds images at full resolution, so
     * a single invoice ballooned past 1.8 MB — painful for WhatsApp recipients.
     * Downscaled copies are cached next to the original and reused.
     *
     * Set $opaque for full-width bands with nothing behind them: they are written
     * as JPEG, which dompdf embeds as-is instead of expanding a palette image to
     * 24-bit RGB (517 KB of header/footer becomes 122 KB). Keep it off for the
     * watermark, which needs its alpha channel.
     *
     * Returns the original path if no work is needed, or if anything goes wrong,
     * so callers can use the result unconditionally.
     *
     * @param  string|null  $path
     * @param  int  $maxWidth
     * @param  bool  $opaque
     * @return string|null
     */
    public static function pdfImage($path, $maxWidth = 1400, $opaque = false)
    {
        if (! $path || ! is_file($path) || ! function_exists('imagecreatetruecolor')) {
            return $path;
        }

        $size = @getimagesize($path);
        if (! $size || empty($size[0]) || empty($size[1])) {
            return $path;
        }

        $needsResize = $size[0] > $maxWidth;
        $needsReencode = $opaque && $size[2] !== IMAGETYPE_JPEG;
        if (! $needsResize && ! $needsReencode) {
            return $path;
        }

        $cacheDir = storage_path('app/letterhead-cache');
        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0775, true) && ! is_dir($cacheDir)) {
            return $path;
        }

        $extension = $opaque ? 'jpg' : 'png';
        $cached = $cacheDir.'/'.sha1($path.'|'.filemtime($path).'|'.filesize($path).'|'.$maxWidth.'|'.$extension).'.'.$extension;
        if (is_file($cached)) {
            return $cached;
        }

        // GD decodes the whole bitmap, so a 6501px image needs ~170 MB while
        // php-fpm allows 128 MB. Raise the ceiling just for this one-off resize
        // (the result is cached) and bail out rather than risk a fatal OOM.
        $needed = (int) (($size[0] * $size[1] * 4) + ($maxWidth * $maxWidth * 4) + (16 * 1024 * 1024));
        $originalLimit = ini_get('memory_limit');
        $limitBytes = self::bytesFromIni($originalLimit);
        $restoreLimit = false;

        if ($limitBytes > 0 && $limitBytes < $needed) {
            if ($needed > 640 * 1024 * 1024) {
                return $path;
            }
            if (@ini_set('memory_limit', (int) ceil($needed / (1024 * 1024)).'M') === false) {
                return $path;
            }
            $restoreLimit = true;
        }

        try {
            switch ($size[2]) {
                case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path); break;
                case IMAGETYPE_GIF:  $src = @imagecreatefromgif($path); break;
                case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
                default: return $path;
            }
            if (! $src) {
                return $path;
            }

            $width = min($size[0], $maxWidth);
            $height = max(1, (int) round($size[1] * ($width / $size[0])));

            $dst = imagecreatetruecolor($width, $height);
            if ($opaque) {
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                // So transparent source pixels blend into the white page.
                imagealphablending($dst, true);
            } else {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);

            $ok = $opaque ? @imagejpeg($dst, $cached, 85) : @imagepng($dst, $cached, 8);
            imagedestroy($src);
            imagedestroy($dst);

            if ($ok && is_file($cached)) {
                @chmod($cached, 0664);

                return $cached;
            }
        } catch (\Throwable $e) {
            // fall through to the original
        } finally {
            if ($restoreLimit) {
                @ini_set('memory_limit', $originalLimit);
            }
        }

        return $path;
    }

    /**
     * @param  string  $value
     * @return int  Bytes, or -1 when unlimited.
     */
    protected static function bytesFromIni($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }

    /**
     * @param  object|null  $settings
     * @return array
     */
    public static function resolve($settings = null)
    {
        $settings = $settings ?: GeneralSetting::query()->orderByDesc('id')->first();

        $headerPath = self::locateBeyondOrConfigured($settings->email_header ?? null, 'header');
        $footerPath = self::locateBeyondOrConfigured($settings->email_footer ?? null, 'footer');
        $watermarkPath = self::locate($settings->email_water_mark ?? null)
            ?: self::locate($settings->site_logo ?? null)
            ?: self::locateBranding('beyond-logo.png');

        return [
            'has_header' => (bool) $headerPath,
            'has_footer' => (bool) $footerPath,
            'has_watermark' => (bool) $watermarkPath,
            'header_file' => $headerPath ? basename($headerPath) : null,
            'footer_file' => $footerPath ? basename($footerPath) : null,
            'watermark_file' => $watermarkPath ? basename($watermarkPath) : null,
            'header_path' => $headerPath,
            'footer_path' => $footerPath,
            'watermark_path' => $watermarkPath,
            'header_url' => $headerPath ? self::publicUrl($headerPath) : null,
            'footer_url' => $footerPath ? self::publicUrl($footerPath) : null,
            'watermark_url' => $watermarkPath ? self::publicUrl($watermarkPath) : null,
        ];
    }

    /**
     * Install Beyond letterheads into public/logo and point general_settings at them
     * when missing or still pointing at Alpha Bridge assets.
     */
    public static function ensureSynced()
    {
        $settings = GeneralSetting::query()->orderByDesc('id')->first();
        if (! $settings) {
            return self::resolve(null);
        }

        $logoDir = public_path('logo');
        if (! is_dir($logoDir)) {
            @mkdir($logoDir, 0775, true);
        }

        $changed = false;

        foreach (['header' => self::BEYOND_HEADER, 'footer' => self::BEYOND_FOOTER] as $kind => $beyondName) {
            $field = $kind === 'header' ? 'email_header' : 'email_footer';
            $current = (string) ($settings->{$field} ?? '');
            $needsBeyond = $current === ''
                || self::isForeignLetterhead($current)
                || ! self::locate($current);

            $installed = self::installBeyondAsset($beyondName);
            if (! $installed) {
                continue;
            }

            if ($needsBeyond || $current !== $beyondName) {
                // Keep a custom Beyond upload if it exists and is not foreign branding
                if ($current !== '' && ! self::isForeignLetterhead($current) && self::locate($current)) {
                    continue;
                }
                $settings->{$field} = $beyondName;
                $changed = true;
            }
        }

        // Watermark: prefer Beyond site logo when configured mark file is missing
        if (! self::locate($settings->email_water_mark ?? null) && ! empty($settings->site_logo) && self::locate($settings->site_logo)) {
            $settings->email_water_mark = $settings->site_logo;
            $changed = true;
        }

        if ($changed) {
            $settings->save();
        }

        return self::resolve($settings->fresh());
    }

    /**
     * Alpha Bridge / legacy API letterheads must not be used on Beyond.
     */
    protected static function isForeignLetterhead($filename)
    {
        $name = strtolower((string) $filename);

        return strpos($name, 'pdf-letterhead') !== false
            || strpos($name, 'letterhead-header-pdf-letterhead') !== false
            || strpos($name, 'letterhead-footer-pdf-letterhead') !== false
            || strpos($name, 'alpha') !== false
            || strpos($name, 'alphabridge') !== false;
    }

    protected static function locateBeyondOrConfigured($configured, $kind)
    {
        $configured = trim((string) $configured);
        if ($configured !== '' && ! self::isForeignLetterhead($configured)) {
            $path = self::locate($configured);
            if ($path) {
                return $path;
            }
        }

        $beyondName = $kind === 'header' ? self::BEYOND_HEADER : self::BEYOND_FOOTER;
        $installed = self::installBeyondAsset($beyondName);

        return $installed ?: self::locate($beyondName) ?: self::locateBranding($beyondName);
    }

    /**
     * Copy branding letterhead into public/logo (writable web path).
     */
    protected static function installBeyondAsset($filename)
    {
        $dest = public_path('logo/'.$filename);
        if (is_file($dest)) {
            return $dest;
        }

        $src = self::locateBranding($filename);
        if (! $src) {
            return null;
        }

        $logoDir = public_path('logo');
        if (! is_dir($logoDir)) {
            @mkdir($logoDir, 0775, true);
        }

        if (@copy($src, $dest)) {
            @chmod($dest, 0664);

            return $dest;
        }

        return is_file($src) ? $src : null;
    }

    protected static function locate($filename)
    {
        $filename = trim((string) $filename);
        if ($filename === '') {
            return null;
        }

        $candidates = [
            public_path('logo/'.$filename),
            base_path('public/logo/'.$filename),
            public_path('branding/'.$filename),
            base_path('public/branding/'.$filename),
        ];

        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function locateBranding($filename)
    {
        $candidates = [
            public_path('branding/'.$filename),
            base_path('public/branding/'.$filename),
        ];
        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function publicUrl($absolutePath)
    {
        $real = realpath($absolutePath) ?: $absolutePath;
        $logoDir = realpath(public_path('logo'));
        $brandDir = realpath(public_path('branding'));

        if ($logoDir && strpos($real, $logoDir) === 0) {
            return url('public/logo/'.basename($real));
        }
        if ($brandDir && strpos($real, $brandDir) === 0) {
            return url('public/branding/'.basename($real));
        }

        // Prefer logo URL after installBeyondAsset
        return url('public/logo/'.basename($real));
    }
}
