<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagingHubSms extends Migration
{
    public function up()
    {
        Schema::create('cloud_sms_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('provider', 32);
            $table->string('sender_id', 32)->nullable();
            $table->string('status', 32)->default('INACTIVE');
            $table->string('credentials_reference', 191)->nullable();
            $table->string('country', 8)->nullable();
            $table->string('currency', 3)->default('XAF');
            $table->string('ownership', 16)->default('PLATFORM');
            $table->boolean('sending_enabled')->default(false);
            $table->unsignedInteger('daily_segment_limit')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->index('cloud_tenant_id');
        });

        Schema::create('cloud_sms_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->unique();
            $table->timestamps();
        });

        Schema::create('cloud_messaging_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('correlation_id', 64);
            $table->string('purpose', 32);
            $table->string('preference', 16)->default('AUTO');
            $table->string('recipient', 32);
            $table->string('normalized_phone', 32);
            $table->string('status', 32)->default('QUEUED');
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'correlation_id'], 'cloud_msg_correlation_unique');
            $table->index('cloud_tenant_id');
        });

        Schema::create('cloud_messaging_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('notification_id');
            $table->string('channel', 16);
            $table->string('status', 32)->default('QUEUED');
            $table->string('provider', 32)->nullable();
            $table->string('provider_message_id', 64)->nullable();
            $table->string('failure_class', 32)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();
            $table->index(['cloud_tenant_id', 'notification_id']);
        });

        Schema::create('cloud_sms_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('notification_id')->nullable();
            $table->unsignedInteger('attempt_id')->nullable();
            $table->string('recipient', 32);
            $table->string('normalized_recipient', 32);
            $table->string('provider', 32);
            $table->string('provider_message_id', 64)->nullable();
            $table->string('sender', 32)->nullable();
            $table->text('body');
            $table->string('status', 32)->default('QUEUED');
            $table->string('raw_provider_status', 64)->nullable();
            $table->unsignedSmallInteger('segments')->default(1);
            $table->string('encoding', 8)->nullable();
            $table->decimal('customer_charge', 12, 2)->nullable();
            $table->decimal('provider_cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('XAF');
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_reason', 191)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_message_id'], 'cloud_sms_provider_message_unique');
            $table->index('cloud_tenant_id');
        });

        Schema::create('cloud_sms_ledger', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('kind', 16);
            $table->integer('units');
            $table->string('reason', 64);
            $table->string('reference', 64)->nullable();
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->unsignedInteger('notification_id')->nullable();
            $table->timestamps();
            $table->index('cloud_tenant_id');
        });

        Schema::create('cloud_sms_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country', 8);
            $table->string('network', 32)->nullable();
            $table->string('provider', 32)->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->decimal('markup', 12, 4)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->timestamp('effective_from')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cloud_messaging_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('name', 64);
            $table->string('channel', 16);
            $table->string('purpose', 32);
            $table->string('language', 8)->default('en');
            $table->text('body');
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
            $table->index('cloud_tenant_id');
        });

        Schema::create('cloud_messaging_preferences', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('normalized_phone', 32);
            $table->boolean('sms_allowed')->default(true);
            $table->boolean('whatsapp_allowed')->default(true);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'normalized_phone'], 'cloud_msg_pref_phone_unique');
        });

        Schema::create('cloud_sms_suppressions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('normalized_phone', 32);
            $table->string('reason', 64)->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'normalized_phone'], 'cloud_sms_suppression_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cloud_sms_suppressions');
        Schema::dropIfExists('cloud_messaging_preferences');
        Schema::dropIfExists('cloud_messaging_templates');
        Schema::dropIfExists('cloud_sms_prices');
        Schema::dropIfExists('cloud_sms_ledger');
        Schema::dropIfExists('cloud_sms_messages');
        Schema::dropIfExists('cloud_messaging_attempts');
        Schema::dropIfExists('cloud_messaging_notifications');
        Schema::dropIfExists('cloud_sms_accounts');
        Schema::dropIfExists('cloud_sms_connections');
    }
}
