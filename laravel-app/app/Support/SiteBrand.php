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
        if ($setting && ! empty($setting->site_logo)) {
            $path = base_path('public/logo/'.$setting->site_logo);
            if (is_file($path)) {
                return url('public/logo/'.$setting->site_logo);
            }
        }

        return url('/branding/beyond-logo.png');
    }

    public static function siteTitle($generalSetting = null)
    {
        $setting = $generalSetting ?: GeneralSetting::latest()->first();

        return ($setting && ! empty($setting->site_title))
            ? $setting->site_title
            : 'Beyond Enterprise';
    }
}
