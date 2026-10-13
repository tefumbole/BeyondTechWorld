<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSendSmsToWaAnnouncements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_announcements') && ! Schema::hasColumn('wa_announcements', 'send_sms')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->boolean('send_sms')->default(false)->after('send_whatsapp');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('wa_announcements') && Schema::hasColumn('wa_announcements', 'send_sms')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->dropColumn('send_sms');
            });
        }
    }
}
