<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaiSessionToWaAnnouncements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_announcements') && ! Schema::hasColumn('wa_announcements', 'mai_session_id')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->unsignedInteger('mai_session_id')->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('wa_announcements') && Schema::hasColumn('wa_announcements', 'mai_session_id')) {
            Schema::table('wa_announcements', function (Blueprint $table) {
                $table->dropColumn('mai_session_id');
            });
        }
    }
}
