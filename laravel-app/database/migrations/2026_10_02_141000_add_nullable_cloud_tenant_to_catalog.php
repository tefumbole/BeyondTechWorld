<?php

use App\Services\Cloud\CloudTenantColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Group A. Nullable ownership for catalog identity.
 * Do not apply this to the live beyondtechworld_laravel database until approved.
 * Does not enable a global tenant scope.
 */
class AddNullableCloudTenantToCatalog extends Migration
{
    public function up()
    {
        foreach (['products', 'categories', 'brands', 'units', 'warehouses', 'customers', 'suppliers'] as $table) {
            CloudTenantColumn::add($table);
        }
    }

    public function down()
    {
        foreach (['suppliers', 'customers', 'warehouses', 'units', 'brands', 'categories', 'products'] as $table) {
            CloudTenantColumn::remove($table);
        }
    }
}
