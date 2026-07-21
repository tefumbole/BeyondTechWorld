<?php

namespace App\Support;

use App\GeneralSetting;

/**
 * Resolves system letterhead images (header / footer / watermark)
 * for quotations, letters, and PDFs — same General Settings assets.
 */
class Letterhead
{
    /**
     * @param  object|null  $settings  GeneralSetting row or shared view object
     * @return array{has_header:bool,has_footer:bool,has_watermark:bool,header_file:?string,footer_file:?string,watermark_file:?string,header_path:?string,footer_path:?string,watermark_path:?string,header_url:?string,footer_url:?string,watermark_url:?string}
     */
    public static function resolve($settings = null)
    {
        $settings = $settings ?: GeneralSetting::query()->orderByDesc('id')->first();

        $headerPath = self::locate($settings->email_header ?? null)
            ?: self::locateNewestSystemAsset('pdf-letterhead_pdf-header');
        $footerPath = self::locate($settings->email_footer ?? null)
            ?: self::locateNewestSystemAsset('pdf-letterhead_pdf-footer');
        $watermarkPath = self::locate($settings->email_water_mark ?? null)
            ?: self::locate($settings->site_logo ?? null);

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
     * Ensure configured header/footer exist under public/logo.
     * Copies from apps/api system-assets when Laravel logo files are missing,
     * and updates general_settings filenames when needed.
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

        $headerSrc = self::locate($settings->email_header)
            ?: self::locateNewestSystemAsset('pdf-letterhead_pdf-header');
        if ($headerSrc && ! self::locate($settings->email_header)) {
            $name = 'letterhead-header-'.basename($headerSrc);
            $dest = $logoDir.DIRECTORY_SEPARATOR.$name;
            if (@copy($headerSrc, $dest)) {
                @chmod($dest, 0664);
                $settings->email_header = $name;
                $changed = true;
            }
        }

        $footerSrc = self::locate($settings->email_footer)
            ?: self::locateNewestSystemAsset('pdf-letterhead_pdf-footer');
        if ($footerSrc && ! self::locate($settings->email_footer)) {
            $name = 'letterhead-footer-'.basename($footerSrc);
            $dest = $logoDir.DIRECTORY_SEPARATOR.$name;
            if (@copy($footerSrc, $dest)) {
                @chmod($dest, 0664);
                $settings->email_footer = $name;
                $changed = true;
            }
        }

        if ($changed) {
            $settings->save();
        }

        return self::resolve($settings);
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
            // legacy docroot layouts
            base_path('../public/logo/'.$filename),
        ];

        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected static function locateNewestSystemAsset($prefix)
    {
        $dirs = [
            base_path('../apps/api/uploads/system-assets'),
            dirname(base_path()).'/apps/api/uploads/system-assets',
            '/var/www/beyondtechworld/apps/api/uploads/system-assets',
        ];

        $matches = [];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob(rtrim($dir, '/').'/'.$prefix.'*.png') ?: [] as $file) {
                if (is_file($file) && strpos(basename($file), '._') !== 0) {
                    $matches[$file] = @filemtime($file) ?: 0;
                }
            }
        }

        if (empty($matches)) {
            return null;
        }

        arsort($matches);
        reset($matches);

        return key($matches);
    }

    protected static function publicUrl($absolutePath)
    {
        $logoDir = realpath(public_path('logo'));
        $real = realpath($absolutePath);
        if ($logoDir && $real && strpos($real, $logoDir) === 0) {
            return url('public/logo/'.basename($real));
        }

        // File lives outside public/logo (e.g. system-assets) — serve via logo copy when possible
        return url('public/logo/'.basename($absolutePath));
    }
}
