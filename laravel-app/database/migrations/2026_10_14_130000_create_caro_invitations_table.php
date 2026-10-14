<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCaroInvitationsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('caro_invitations')) {
            return;
        }

        Schema::create('caro_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 50);
            $table->string('phone_digits', 32);
            $table->string('name', 120);
            $table->string('role', 32);
            $table->string('seat_code', 8)->unique();
            $table->string('image_file', 191);
            $table->timestamps();
            $table->index('phone_digits');
        });
    }

    public function down()
    {
        Schema::dropIfExists('caro_invitations');
    }
}
