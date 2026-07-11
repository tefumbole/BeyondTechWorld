<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('serial_no')->nullable();
            $table->integer('department_id')->nullable();
            $table->integer('region_id')->nullable();
            $table->integer('station_id')->nullable();
            $table->integer('donor_id')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->integer('price')->nullable();
            $table->integer('number_of_Seats')->nullable();
            $table->string('serial')->nullable();
            $table->string('city')->nullable();
            $table->string('physical_location')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('service_date')->nullable();
            $table->integer('life_span')->nullable();
            $table->string('asset_type')->nullable();
            $table->string('Assign_to')->nullable();
            $table->string('manager')->nullable();
            $table->string('set_type')->nullable();
            $table->string('depreciation_type')->nullable();
            $table->string('remark')->nullable();
            $table->string('driver')->nullable();
            $table->string('milage_at_purchase')->nullable();
            $table->string('chassi_number')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('horse_power')->nullable();
            $table->string('matricule')->nullable();
            $table->string('ram')->nullable();
            $table->string('hard_drive')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('processor')->nullable();
            $table->string('processor_speed')->nullable();
            $table->string('tv_size')->nullable();
            $table->string('house_in_land')->nullable();
            $table->string('furnished')->nullable();
            $table->string('dimentions_of_the_plot')->nullable();
            $table->string('number_of_Room')->nullable();
            $table->string('source_code_owner')->nullable();
            $table->boolean('is_active')->nullable();
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
        Schema::dropIfExists('assets');
    }
}
