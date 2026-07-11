<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
//        Schema::create('payment_requests', function (Blueprint $table) {
//            $table->bigIncrements('id');
//            $table->integer('vendor_id');
//            $table->integer('order_id');
//            $table->integer('amount');
//            $table->integer('status')->default(0);
//            $table->integer('payed_by')->nullable();
//            $table->timestamps();
//        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_requests');
    }
}
