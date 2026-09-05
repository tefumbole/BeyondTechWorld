<?php

namespace App\Support;

use App\SiteSetting;

class PaNgwayuBiography
{
    const SETTING_KEY = 'pangwayu.biography';

    public static function defaults()
    {
        return require resource_path('memorial/pangwayu-biography.php');
    }

    public static function data()
    {
        $defaults = self::defaults();
        $override = SiteSetting::getValue(self::SETTING_KEY, null);
        if (! is_array($override) || ! $override) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $override);
    }

    public static function photoUrl($path)
    {
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        if (strpos($path, 'public/') === 0) {
            return asset($path);
        }

        return asset('public/'.$path);
    }

    public static function save(array $payload)
    {
        SiteSetting::setJson(self::SETTING_KEY, $payload);
    }
}
