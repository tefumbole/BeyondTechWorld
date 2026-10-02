<?php

namespace App\Services\Cloud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CloudTenantColumns
{
    /** @var array<string, bool> */
    protected static $cache = [];

    public static function present(Model $model)
    {
        $table = $model->getTable();
        if (array_key_exists($table, static::$cache)) {
            return static::$cache[$table];
        }
        try {
            $exists = Schema::hasTable($table) && Schema::hasColumn($table, 'cloud_tenant_id');
        } catch (\Throwable $e) {
            $exists = false;
        }
        static::$cache[$table] = $exists;

        return $exists;
    }

    public static function forget()
    {
        static::$cache = [];
    }
}
