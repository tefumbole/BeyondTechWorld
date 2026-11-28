<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MaiDashboardTokens extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mai_api_keys') && ! Schema::hasColumn('mai_api_keys', 'mai_session_id')) {
            Schema::table('mai_api_keys', function (Blueprint $table) {
                $table->unsignedInteger('mai_session_id')->nullable()->index();
            });
        }
        if (! Schema::hasTable('mai_signins')) {
            Schema::create('mai_signins', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->timestamp('signed_in_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('mai_operator_actions')) {
            Schema::create('mai_operator_actions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('operator_user_id')->index();
                $table->string('action', 40);
                $table->string('target_type', 40);
                $table->unsignedInteger('target_id')->nullable();
                $table->string('note', 191)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mai_operator_actions');
        Schema::dropIfExists('mai_signins');
        if (Schema::hasTable('mai_api_keys') && Schema::hasColumn('mai_api_keys', 'mai_session_id')) {
            Schema::table('mai_api_keys', function (Blueprint $table) {
                $table->dropColumn('mai_session_id');
            });
        }
    }
}
