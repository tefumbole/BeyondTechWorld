<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaiCustomerSessions extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('mai_sessions')) {
            Schema::create('mai_sessions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('public_id', 40)->unique();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->string('provider', 16)->default('mai');
                $table->string('worker_session_id', 40)->unique();
                $table->string('phone_number', 32)->nullable();
                $table->string('expected_phone', 32)->nullable();
                $table->string('display_name', 191)->nullable();
                $table->string('status', 32)->default('PENDING')->index();
                $table->string('attempt_id', 40)->nullable();
                $table->string('pairing_code', 32)->nullable();
                $table->timestamp('pairing_expires_at')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('unlinked_at')->nullable();
                $table->boolean('ai_enabled')->default(0);
                $table->string('last_error', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mai_api_keys')) {
            Schema::create('mai_api_keys', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->string('name', 80);
                $table->string('prefix', 16)->index();
                $table->string('key_hash', 64)->unique();
                $table->string('scopes', 191);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mai_webhooks')) {
            Schema::create('mai_webhooks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('mai_session_id')->nullable()->index();
                $table->string('url', 500);
                $table->text('secret');
                $table->boolean('active')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mai_webhook_deliveries')) {
            Schema::create('mai_webhook_deliveries', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('mai_webhook_id')->index();
                $table->string('event_id', 80);
                $table->text('payload');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('last_error', 191)->nullable();
                $table->timestamps();
                $table->unique(['mai_webhook_id', 'event_id'], 'mai_webhook_event_unique');
            });
        }

        if (! Schema::hasTable('mai_outbound_messages')) {
            Schema::create('mai_outbound_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('mai_session_id')->index();
                $table->string('idempotency_key', 80);
                $table->string('to_phone', 32);
                $table->text('body');
                $table->string('status', 16)->default('pending')->index();
                $table->string('provider_message_id', 128)->nullable();
                $table->string('last_error', 191)->nullable();
                $table->timestamps();
                $table->unique(['mai_session_id', 'idempotency_key'], 'mai_outbound_idempotent');
            });
        }

        if (! Schema::hasTable('mai_inbound_events')) {
            Schema::create('mai_inbound_events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('mai_session_id')->index();
                $table->string('event_id', 80);
                $table->string('kind', 32);
                $table->timestamps();
                $table->unique(['mai_session_id', 'event_id'], 'mai_inbound_event_unique');
            });
        }

        if (! Schema::hasTable('mai_email_verifications')) {
            Schema::create('mai_email_verifications', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('whatsapp_conversations') && ! Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                $table->unsignedInteger('mai_session_id')->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp_conversations') && Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                $table->dropColumn('mai_session_id');
            });
        }
        Schema::dropIfExists('mai_email_verifications');
        Schema::dropIfExists('mai_inbound_events');
        Schema::dropIfExists('mai_outbound_messages');
        Schema::dropIfExists('mai_webhook_deliveries');
        Schema::dropIfExists('mai_webhooks');
        Schema::dropIfExists('mai_api_keys');
        Schema::dropIfExists('mai_sessions');
    }
}
