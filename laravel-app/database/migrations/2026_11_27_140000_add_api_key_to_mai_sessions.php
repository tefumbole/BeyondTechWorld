<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApiKeyToMaiSessions extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mai_sessions') && ! Schema::hasColumn('mai_sessions', 'api_key')) {
            Schema::table('mai_sessions', function (Blueprint $table) {
                $table->string('api_key', 64)->nullable()->unique();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('mai_sessions') && Schema::hasColumn('mai_sessions', 'api_key')) {
            Schema::table('mai_sessions', function (Blueprint $table) {
                $table->dropUnique(['api_key']);
                $table->dropColumn('api_key');
            });
        }
    }
}
