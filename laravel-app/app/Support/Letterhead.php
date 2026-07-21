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
