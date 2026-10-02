<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal fields for Beyond Cloud. Does not add cloud_tenant_id to ERP tables.
 */
class AddCloudPortalPayments extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cloud_tenants') && ! Schema::hasColumn('cloud_tenants', 'hero_path')) {
            Schema::table('cloud_tenants', function (Blueprint $table) {
                $table->string('hero_path', 191)->nullable();
            });
        }

        if (Schema::hasTable('cloud_subscriptions') && ! Schema::hasColumn('cloud_subscriptions', 'quoted_price')) {
            Schema::table('cloud_subscriptions', function (Blueprint $table) {
                $table->decimal('quoted_price', 12, 2)->nullable();
                $table->string('quoted_currency', 8)->nullable();
                $table->string('payment_method_code', 32)->nullable();
            });
        }

        if (! Schema::hasTable('cloud_subscription_payments')) {
            Schema::create('cloud_subscription_payments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_subscription_id')->nullable()->index();
                $table->string('method_code', 32)->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('XAF');
                $table->string('provider', 32);
                $table->string('provider_reference', 191)->nullable()->index();
                $table->string('status', 32)->default('PENDING')->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
                $table->foreign('cloud_subscription_id')->references('id')->on('cloud_subscriptions')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cloud_subscription_payments');
        if (Schema::hasTable('cloud_subscriptions') && Schema::hasColumn('cloud_subscriptions', 'quoted_price')) {
            Schema::table('cloud_subscriptions', function (Blueprint $table) {
                $table->dropColumn(['quoted_price', 'quoted_currency', 'payment_method_code']);
            });
        }
        if (Schema::hasTable('cloud_tenants') && Schema::hasColumn('cloud_tenants', 'hero_path')) {
            Schema::table('cloud_tenants', function (Blueprint $table) {
                $table->dropColumn('hero_path');
            });
        }
    }
}
