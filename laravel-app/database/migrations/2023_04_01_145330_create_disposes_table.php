<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDisposesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('disposes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('asset_id');
            $table->string('method');
            $table->string('other')->nullable();
            $table->integer('price')->default(0);
            $table->text('remarks')->nullable();
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
        Schema::dropIfExists('disposes');
    }
}
