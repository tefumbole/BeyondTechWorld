<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_role', 80)->nullable();
            $table->string('action', 80)->index();
            $table->string('entity', 120)->nullable()->index();
            $table->string('entity_id', 120)->nullable();
            $table->string('summary', 500)->nullable();
            $table->text('metadata')->nullable();
            $table->string('ip_address', 60)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
}
