<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicDonations extends Migration
{
    public function up()
    {
        if (Schema::hasTable('public_donations')) {
            return;
        }

        Schema::create('public_donations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 64)->unique();
            $table->string('person_name', 191);
            $table->string('phone', 30);
            $table->unsignedInteger('amount');
            $table->string('note', 191)->nullable();
            $table->string('method', 20)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->string('campay_reference', 64)->nullable();
            $table->text('payment_link')->nullable();
            $table->string('error', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('public_donations');
    }
}
