<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPersonalizedToWaAnnouncements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_announcements') && ! Schema::hasColumn('wa_announcements', 'personalized')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->boolean('personalized')->default(false)->after('send_whatsapp');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('wa_announcements') && Schema::hasColumn('wa_announcements', 'personalized')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->dropColumn('personalized');
            });
        }
    }
}
