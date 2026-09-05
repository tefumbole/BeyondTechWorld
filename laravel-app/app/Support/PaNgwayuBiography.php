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

        return self::preferDefaultMedia($defaults, array_replace_recursive($defaults, $override));
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
        $relative = strpos($path, 'public/') === 0 ? substr($path, 7) : $path;
        $url = asset(strpos($path, 'public/') === 0 ? $path : 'public/'.$path);
        $file = public_path($relative);
        if (is_file($file)) {
            $url .= (strpos($url, '?') === false ? '?' : '&').'v='.filemtime($file);
        }

        return $url;
    }

    protected static function preferDefaultMedia(array $defaults, array $merged)
    {
        $merged['meta']['og_image'] = $defaults['meta']['og_image'];
        $merged['hero']['portrait'] = $defaults['hero']['portrait'];
        $merged['hero']['companion'] = $defaults['hero']['companion'];
        $merged['intro']['image'] = $defaults['intro']['image'];
        $merged['values']['image'] = $defaults['values']['image'];

        foreach ($defaults['sections'] as $i => $section) {
            if (isset($section['image'])) {
                $merged['sections'][$i]['image'] = $section['image'];
            }
        }

        $defaultGallery = $defaults['gallery']['items'];
        foreach ($defaultGallery as $i => $item) {
            if (! isset($merged['gallery']['items'][$i])) {
                $merged['gallery']['items'][$i] = $item;
                continue;
            }
            $merged['gallery']['items'][$i]['src'] = $item['src'];
        }
        $merged['gallery']['items'] = array_slice($merged['gallery']['items'], 0, count($defaultGallery));

        return $merged;
    }

    public static function save(array $payload)
    {
        SiteSetting::setJson(self::SETTING_KEY, $payload);
    }
}
