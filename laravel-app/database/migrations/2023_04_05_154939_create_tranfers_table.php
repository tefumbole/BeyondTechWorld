<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTranfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('asset_id');
            $table->integer('parent_id');
            $table->integer('from');
            $table->integer('to');
            $table->integer('price');
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
        Schema::dropIfExists('asset_transfers');
    }
}
