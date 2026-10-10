<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampayPayoutsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('campay_payouts')) {
            return;
        }
        Schema::create('campay_payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->string('person_name');
            $table->string('phone', 32);
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('XAF');
            $table->string('note', 191)->nullable();
            $table->string('external_reference', 64)->unique();
            $table->string('campay_reference', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('operator', 32)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('campay_payouts');
    }
}
