<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CloudWhatsappConnectionEvents extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cloud_whatsapp_connection_events')) {
            return;
        }
        Schema::create('cloud_whatsapp_connection_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->index();
            $table->unsignedInteger('cloud_whatsapp_connection_id')->nullable()->index('wa_conn_event_conn_idx');
            $table->string('event', 64);
            $table->unsignedInteger('actor_user_id')->nullable()->index('wa_conn_event_actor_idx');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cloud_whatsapp_connection_events');
    }
}
