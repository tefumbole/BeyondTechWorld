<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('booking_products', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id');
            $table->integer('product_id');
            $table->integer('booking_method');
            $table->double('qty');
            $table->integer('sale_unit_id');
            $table->double('net_unit_price');
            $table->double('discount');
            $table->double('tax_rate');
            $table->double('tax');
            $table->double('total');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->integer('category_id');
            $table->integer('warehouse_id');
            $table->integer('product_batch_id')->nullable();
            $table->integer('multi_product_batch_id')->nullable();
            $table->integer('multi_product_batch_qty')->nullable();
            $table->integer('variant_id')->nullable();
            $table->integer('is_return')->default(0);
            $table->integer('is_notified')->default(0);
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
        Schema::dropIfExists('booking_products');
    }
}
