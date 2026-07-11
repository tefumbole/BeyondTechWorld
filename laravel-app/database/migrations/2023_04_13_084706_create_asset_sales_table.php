<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_number')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_id')->nullable();
            $table->date('buyer_id_date')->nullable();
            $table->integer('buyer_total_amount')->nullable();
            $table->text('buyer_remark')->nullable();
            $table->date('date');
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
        Schema::dropIfExists('asset_sales');
    }
}
