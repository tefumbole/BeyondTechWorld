<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBirthdayFlyersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('birthday_flyers')) {
            return;
        }

        Schema::create('birthday_flyers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 50);
            $table->string('display_name', 120);
            $table->string('call_name', 120);
            $table->string('template', 32);
            $table->boolean('has_selfie')->default(false);
            $table->string('output_file', 191);
            $table->timestamps();
            $table->index('phone');
        });
    }

    public function down()
    {
        Schema::dropIfExists('birthday_flyers');
    }
}
