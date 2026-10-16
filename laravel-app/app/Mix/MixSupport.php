<?php

namespace App\Mix;

class MixSupport
{
    public static function slug($value, $fallback = 'item')
    {
        $slug = strtolower(trim((string) $value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }

    public static function uniqueSlug($modelClass, $value, $ignoreId = null)
    {
        $base = self::slug($value);
        $slug = $base;
        $n = 2;
        while ($modelClass::where('slug', $slug)->when($ignoreId, function ($q) use ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        })->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    public static function num($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $value + 0;
    }

    public static function text($value)
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
