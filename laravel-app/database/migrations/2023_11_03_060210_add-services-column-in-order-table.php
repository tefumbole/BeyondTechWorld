<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServicesColumnInOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
//            $table->string('service_type')->nullable();
//            $table->string('academic_year')->nullable();
//            $table->integer('variant_id')->nullable();
//            $table->integer('number_of_pages')->nullable();
//            $table->integer('word_count')->nullable();
//            $table->string('spacing')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
}
