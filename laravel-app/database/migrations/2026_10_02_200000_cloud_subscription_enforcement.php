<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription lifecycle support. Does not change plan prices.
 * Does not make operational cloud_tenant_id columns NOT NULL.
 */
class CloudSubscriptionEnforcement extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cloud_subscriptions') && ! Schema::hasColumn('cloud_subscriptions', 'cancel_at_period_end')) {
            Schema::table('cloud_subscriptions', function (Blueprint $table) {
                $table->boolean('cancel_at_period_end')->default(false);
            });
        }

        if (! Schema::hasTable('cloud_module_trials')) {
            Schema::create('cloud_module_trials', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_module_id')->index();
                $table->string('source', 32)->default('INTRODUCTORY')->index();
                $table->string('normalized_phone', 32)->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->timestamps();
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
                $table->foreign('cloud_module_id')->references('id')->on('cloud_modules')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cloud_subscription_events')) {
            Schema::create('cloud_subscription_events', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_subscription_id')->nullable()->index();
                $table->string('event', 64)->index();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->text('payload')->nullable();
                $table->timestamps();
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('cloud_billing_events')) {
            Schema::create('cloud_billing_events', function (Blueprint $table) {
                $table->increments('id');
                $table->string('provider', 32);
                $table->string('event_id', 191);
                $table->unsignedInteger('cloud_subscription_payment_id')->nullable()->index();
                $table->string('outcome', 32);
                $table->timestamps();
                $table->unique(['provider', 'event_id'], 'cloud_billing_event_unique');
            });
        }

        if (! Schema::hasTable('cloud_subscription_notices')) {
            Schema::create('cloud_subscription_notices', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_subscription_id')->nullable()->index();
                $table->string('kind', 64);
                $table->timestamp('created_at')->nullable();
                $table->unique(['cloud_subscription_id', 'kind'], 'cloud_subscription_notice_unique');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cloud_subscription_notices');
        Schema::dropIfExists('cloud_billing_events');
        Schema::dropIfExists('cloud_subscription_events');
        Schema::dropIfExists('cloud_module_trials');
        if (Schema::hasTable('cloud_subscriptions') && Schema::hasColumn('cloud_subscriptions', 'cancel_at_period_end')) {
            Schema::table('cloud_subscriptions', function (Blueprint $table) {
                $table->dropColumn('cancel_at_period_end');
            });
        }
    }
}
