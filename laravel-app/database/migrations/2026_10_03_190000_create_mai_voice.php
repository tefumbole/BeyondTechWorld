<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaiVoice extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('voice_connections')) {
            Schema::create('voice_connections', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->string('tenant_type', 32)->default('INTERNAL');
                $table->string('called_number', 32)->index();
                $table->string('provider', 32)->default('stub');
                $table->string('status', 32)->default('INACTIVE');
                $table->boolean('ai_enabled')->default(false);
                $table->string('company_name', 191)->nullable();
                $table->string('assistant_name', 80)->default('MAI');
                $table->string('language', 16)->default('en');
                $table->string('greeting', 500)->nullable();
                $table->string('transfer_number', 32)->nullable();
                $table->text('business_hours')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voice_calls')) {
            Schema::create('voice_calls', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->unsignedInteger('voice_connection_id')->nullable()->index();
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->string('provider', 32)->default('stub');
                $table->string('provider_call_id', 128)->nullable()->index();
                $table->string('caller_phone', 32)->nullable()->index();
                $table->string('called_number', 32)->nullable();
                $table->string('status', 32)->default('REJECTED')->index();
                $table->string('status_reason', 64)->nullable();
                $table->string('handling', 16)->default('MAI');
                $table->string('transfer_status', 16)->default('NONE');
                $table->boolean('recording')->default(false);
                $table->boolean('identity_verified')->default(false);
                $table->string('caller_name', 191)->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->unsignedInteger('last_latency_ms')->nullable();
                $table->unsignedInteger('silent_turns')->default(0);
                $table->text('state')->nullable();
                $table->text('transcript')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voice_callbacks')) {
            Schema::create('voice_callbacks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->unsignedInteger('voice_call_id')->nullable()->index();
                $table->string('caller_phone', 32)->nullable();
                $table->string('caller_name', 191)->nullable();
                $table->string('requested_for', 191)->nullable();
                $table->string('reason', 191)->nullable();
                $table->text('message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->string('status', 32)->default('OPEN');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('voice_callbacks');
        Schema::dropIfExists('voice_calls');
        Schema::dropIfExists('voice_connections');
    }
}
