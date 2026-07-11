<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInAssetExpense extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('asset_expenses', function (Blueprint $table) {
            $table->string('repairer_name')->nullable();
            $table->string('repairer_address')->nullable();
            $table->string('repairer_phone')->nullable();
            $table->string('repairer_location')->nullable();
            $table->string('repair_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asset_expenses', function (Blueprint $table) {
            //
        });
    }
}
