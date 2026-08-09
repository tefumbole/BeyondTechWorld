<?php

namespace App\Support;

use App\GeneralSetting;

class SiteBrand
{
    /**
     * Public URL for the logo uploaded in Settings → General.
     * Falls back to the static branding asset when none is set.
     */
    public static function logoUrl($generalSetting = null)
    {
        $setting = $generalSetting ?: GeneralSetting::latest()->first();
        $fallback = url('public/branding/beyond-logo.png');

        if ($setting && ! empty($setting->site_logo)) {
            $filename = basename((string) $setting->site_logo);
            $path = base_path('public/logo/'.$filename);
            // Skip missing or absurdly large uploads (e.g. watermark used as logo → broken on Safari).
            if (is_file($path) && filesize($path) > 0 && filesize($path) <= 800000) {
                return url('public/logo/'.$filename);
            }
        }

        $brandPath = base_path('public/branding/beyond-logo.png');
        if (is_file($brandPath)) {
            return $fallback;
        }

        $logoCopy = base_path('public/logo/beyond-logo.png');
        if (is_file($logoCopy)) {
            return url('public/logo/beyond-logo.png');
        }

        return $fallback;
    }

    public static function siteTitle($generalSetting = null)
    {
        $setting = $generalSetting ?: GeneralSetting::latest()->first();

        return ($setting && ! empty($setting->site_title))
            ? $setting->site_title
            : 'Beyond Enterprise';
    }
}
