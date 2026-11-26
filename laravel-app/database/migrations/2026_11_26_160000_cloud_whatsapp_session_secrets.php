<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CloudWhatsappSessionSecrets extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return;
        }
        Schema::table('cloud_whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_whatsapp_connections', 'session_secret')) {
                $table->text('session_secret')->nullable();
            }
            if (! Schema::hasColumn('cloud_whatsapp_connections', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            return;
        }
        Schema::table('cloud_whatsapp_connections', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_whatsapp_connections', 'session_secret')) {
                $table->dropColumn('session_secret');
            }
            if (Schema::hasColumn('cloud_whatsapp_connections', 'webhook_secret')) {
                $table->dropColumn('webhook_secret');
            }
        });
    }
}
