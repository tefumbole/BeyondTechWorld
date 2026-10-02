<?php

use App\Services\Cloud\CloudTenantColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Group B. Sale, quotation, and payment headers only.
 * Line tables keep ownership through the parent and do not get a second column.
 */
class AddNullableCloudTenantToSales extends Migration
{
    public function up()
    {
        foreach (['sales', 'quotations', 'payments'] as $table) {
            CloudTenantColumn::add($table);
        }
    }

    public function down()
    {
        foreach (['payments', 'quotations', 'sales'] as $table) {
            CloudTenantColumn::remove($table);
        }
    }
}
