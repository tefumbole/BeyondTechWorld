<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaiSignins extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mai_signins')) {
            return;
        }
        Schema::create('mai_signins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->timestamp('signed_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        if (Schema::hasTable('mai_operator_actions')) {
            return;
        }
        if (Schema::hasTable('mai_api_keys') && Schema::hasColumn('mai_api_keys', 'mai_session_id')) {
            return;
        }
        Schema::dropIfExists('mai_signins');
    }
}
