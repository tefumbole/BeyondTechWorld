<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultWarehouseBillerToGeneralSettings extends Migration
{
    public function up()
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('general_settings', 'default_warehouse_id')) {
                $table->unsignedInteger('default_warehouse_id')->nullable();
            }
            if (! Schema::hasColumn('general_settings', 'default_biller_id')) {
                $table->unsignedInteger('default_biller_id')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'default_warehouse_id')) {
                $table->dropColumn('default_warehouse_id');
            }
            if (Schema::hasColumn('general_settings', 'default_biller_id')) {
                $table->dropColumn('default_biller_id');
            }
        });
    }
}
