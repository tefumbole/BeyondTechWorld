<?php

namespace App\Services\Cloud;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable ownership column. Not a global scope, and not NOT NULL.
 * Deleting a CloudTenant must not delete the business row.
 */
class CloudTenantColumn
{
    public static function add($tableName)
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'cloud_tenant_id')) {
            return;
        }
        if (! Schema::hasTable('cloud_tenants')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('restrict');
        });
    }

    public static function remove($tableName)
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'cloud_tenant_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['cloud_tenant_id']);
            $table->dropColumn('cloud_tenant_id');
        });
    }
}
