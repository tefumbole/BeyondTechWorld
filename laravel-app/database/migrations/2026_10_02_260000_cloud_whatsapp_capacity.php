<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CloudWhatsappCapacity extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cloud_whatsapp_capacity_guard')) {
            Schema::create('cloud_whatsapp_capacity_guard', function (Blueprint $table) {
                $table->increments('id');
            });
            DB::table('cloud_whatsapp_capacity_guard')->insert(['id' => 1]);
        }
        if (! Schema::hasTable('cloud_whatsapp_slot_reservations')) {
            Schema::create('cloud_whatsapp_slot_reservations', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->string('status', 32);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cloud_whatsapp_slot_reservations');
        Schema::dropIfExists('cloud_whatsapp_capacity_guard');
    }
}
