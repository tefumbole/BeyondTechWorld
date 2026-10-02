<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform subscription billing records. Separate from ERP sale payments.
 */
class CloudSubscriptionBilling extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cloud_subscription_payments')) {
            Schema::table('cloud_subscription_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('cloud_subscription_payments', 'internal_reference')) {
                    $table->string('internal_reference', 64)->nullable()->unique();
                }
                if (! Schema::hasColumn('cloud_subscription_payments', 'confirmation_source')) {
                    $table->string('confirmation_source', 32)->nullable();
                }
                if (! Schema::hasColumn('cloud_subscription_payments', 'snapshot')) {
                    $table->text('snapshot')->nullable();
                }
            });
        }

        if (! Schema::hasTable('cloud_subscription_payment_items')) {
            Schema::create('cloud_subscription_payment_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_subscription_payment_id')->index();
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_subscription_id')->index();
                $table->unsignedInteger('cloud_plan_id')->nullable()->index();
                $table->string('module_code', 64)->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8);
                $table->timestamps();
                $table->foreign('cloud_subscription_payment_id', 'cloud_pay_item_payment_fk')
                    ->references('id')->on('cloud_subscription_payments')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('cloud_subscription_payment_items');
    }
}
