<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Consistent "active only" filtering for dropdowns (is_active = 1).
 */
class ActiveRecords
{
    public static function scope(Builder $query)
    {
        return $query->whereRaw('COALESCE(is_active, 0) = 1');
    }

    public static function of($modelClass)
    {
        $query = self::scope($modelClass::query());
        try {
            return $query->orderBy('name')->get();
        } catch (\Throwable $e) {
            return self::scope($modelClass::query())->get();
        }
    }
}
