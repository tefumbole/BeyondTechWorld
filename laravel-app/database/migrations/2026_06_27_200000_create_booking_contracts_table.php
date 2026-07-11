<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingContractsTable extends Migration
{
    public function up()
    {
        Schema::create('booking_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('booking_id');
            $table->string('signature_token', 64)->unique();
            $table->timestamp('agreement_read_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_image')->nullable();
            $table->string('id_card_path')->nullable();
            $table->unsignedInteger('client_user_id')->nullable();
            $table->string('client_username')->nullable();
            $table->string('generated_password')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_contracts');
    }
}
