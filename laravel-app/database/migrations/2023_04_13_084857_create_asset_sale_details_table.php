<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetSaleDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_sale_details', function (Blueprint $table) {
            $table->bigIncrements('id');$table->string('buyer_name')->nullable();
            $table->integer('asset_sale_id');
            $table->integer('asset_id');
            $table->string('asset_name');
            $table->integer('price');
            $table->integer('life_span');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_sale_details');
    }
}
