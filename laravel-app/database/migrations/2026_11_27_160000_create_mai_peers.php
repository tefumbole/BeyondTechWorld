<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaiPeers extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('mai_peers')) {
            Schema::create('mai_peers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('mai_session_id')->index();
                $table->string('remote_jid', 191);
                $table->string('phone_digits', 32)->nullable();
                $table->string('lid', 64)->nullable();
                $table->string('saved_name', 191)->nullable();
                $table->string('profile_name', 191)->nullable();
                $table->timestamps();
                $table->unique(['mai_session_id', 'remote_jid'], 'mai_peers_session_jid_unique');
            });
        }
        if (Schema::hasTable('mai_sessions') && ! Schema::hasColumn('mai_sessions', 'sync_generation')) {
            Schema::table('mai_sessions', function (Blueprint $table) {
                $table->unsignedInteger('sync_generation')->default(0);
                $table->unsignedInteger('sync_seq')->default(0);
                $table->boolean('history_ready')->default(0);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mai_peers');
        if (Schema::hasTable('mai_sessions') && Schema::hasColumn('mai_sessions', 'sync_generation')) {
            Schema::table('mai_sessions', function (Blueprint $table) {
                $table->dropColumn(['sync_generation', 'sync_seq', 'history_ready']);
            });
        }
    }
}
