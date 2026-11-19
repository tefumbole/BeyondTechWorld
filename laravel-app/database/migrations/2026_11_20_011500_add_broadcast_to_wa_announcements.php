<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBroadcastToWaAnnouncements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_announcements') && ! Schema::hasColumn('wa_announcements', 'broadcast')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->boolean('broadcast')->default(false)->after('personalized');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('wa_announcements') && Schema::hasColumn('wa_announcements', 'broadcast')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->dropColumn('broadcast');
            });
        }
    }
}
