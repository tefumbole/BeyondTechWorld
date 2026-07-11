<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInAssetSales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('asset_sales', function (Blueprint $table) {
            $table->string('buyer_title')->nullable();
            $table->string('buyer_address')->nullable();
            $table->string('buyer_to')->nullable();
            $table->string('saller_title')->nullable();
            $table->string('saller_name')->nullable();
            $table->string('saller_number')->nullable();
            $table->string('saller_email')->nullable();
            $table->string('saller_id')->nullable();
            $table->date('saller_id_date')->nullable();
            $table->string('saller_address')->nullable();
            $table->string('saller_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asset_sales', function (Blueprint $table) {
            //
        });
    }
}
