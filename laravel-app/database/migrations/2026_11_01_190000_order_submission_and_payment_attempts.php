<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OrderSubmissionAndPaymentAttempts extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('order_submissions')) {
            Schema::create('order_submissions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->default(1);
                $table->string('idempotency_key', 80);
                $table->char('payload_hash', 64);
                $table->unsignedInteger('sale_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamps();
                $table->unique(['cloud_tenant_id', 'idempotency_key'], 'order_submissions_tenant_key_unique');
            });
        }

        if (! Schema::hasTable('payment_attempts')) {
            Schema::create('payment_attempts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->default(1);
                $table->string('checkout_key', 80);
                $table->unsignedInteger('sale_id')->nullable();
                $table->string('provider', 32)->default('campay');
                $table->string('provider_reference', 191)->nullable();
                $table->string('external_reference', 64);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('status', 20)->default('pending');
                $table->char('payload_hash', 64)->nullable();
                $table->text('request_payload')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['cloud_tenant_id', 'checkout_key'], 'payment_attempts_tenant_key_unique');
                $table->unique(['provider', 'provider_reference'], 'payment_attempts_provider_ref_unique');
                $table->unique('external_reference', 'payment_attempts_external_unique');
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'provider')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider', 32)->nullable();
                $table->string('provider_reference', 191)->nullable();
            });
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->unique(['provider', 'provider_reference'], 'payments_provider_reference_unique');
                });
            } catch (\Throwable $e) {
                // Existing rows can already share an empty provider reference. The attempt table still enforces one charge per checkout.
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'provider')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropUnique('payments_provider_reference_unique');
                });
            } catch (\Throwable $e) {
            }
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['provider', 'provider_reference']);
            });
        }
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('order_submissions');
    }
}
