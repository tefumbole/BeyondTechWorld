<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventAutoPayments extends Migration
{
    public function up()
    {
        if (Schema::hasTable('event_auto_payments')) {
            return;
        }
        Schema::create('event_auto_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 36)->index();
            $table->dateTime('pay_at');
            $table->string('status', 32)->default('pending_approval')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_auto_payments');
    }
}
