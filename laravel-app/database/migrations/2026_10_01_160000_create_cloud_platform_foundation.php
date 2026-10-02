<?php

use App\Services\Cloud\CloudCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beyond Cloud platform tables only.
 *
 * Does not add cloud_tenant_id to products, sales, bookings, WhatsApp, or any
 * other existing table. Does not create a BeyondTechWorld company row.
 * Does not change whatsapp_contacts.normalized_phone uniqueness.
 */
class CreateCloudPlatformFoundation extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cloud_tenants')) {
            Schema::create('cloud_tenants', function (Blueprint $table) {
                $table->increments('id');
                $table->char('uuid', 36)->unique();
                $table->string('name', 191);
                $table->string('legal_name', 191)->nullable();
                $table->string('slug', 191)->unique();
                $table->string('system_name', 191)->nullable();
                $table->string('type', 32)->default('CUSTOMER')->index();
                $table->string('status', 32)->default('PENDING')->index();
                $table->string('email', 191)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('country', 8)->nullable();
                $table->string('city', 191)->nullable();
                $table->string('address', 191)->nullable();
                $table->string('currency', 8)->default('XAF');
                $table->string('timezone', 64)->default('Africa/Douala');
                $table->string('logo_path', 191)->nullable();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('cloud_tenant_memberships')) {
            Schema::create('cloud_tenant_memberships', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('role_id')->nullable()->index();
                $table->string('membership_role', 32)->default('STAFF')->index();
                $table->boolean('is_owner')->default(false);
                $table->string('status', 32)->default('INVITED')->index();
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->unique(['cloud_tenant_id', 'user_id'], 'cloud_membership_tenant_user_unique');
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('cloud_modules')) {
            Schema::create('cloud_modules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 64)->unique();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cloud_plans')) {
            Schema::create('cloud_plans', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_module_id')->index();
                $table->string('code', 64)->unique();
                $table->string('name', 191);
                $table->string('billing_interval', 16)->default('MONTH');
                $table->decimal('price', 12, 2);
                $table->string('currency', 8)->default('XAF');
                $table->unsignedSmallInteger('trial_value')->default(24);
                $table->string('trial_unit', 16)->default('HOUR');
                $table->boolean('active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('cloud_module_id')->references('id')->on('cloud_modules')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('cloud_subscriptions')) {
            Schema::create('cloud_subscriptions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->index();
                $table->unsignedInteger('cloud_plan_id')->index();
                $table->string('status', 32)->default('TRIALING')->index();
                $table->timestamp('trial_started_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamps();
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
                $table->foreign('cloud_plan_id')->references('id')->on('cloud_plans')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('cloud_payment_methods')) {
            Schema::create('cloud_payment_methods', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 32)->unique();
                $table->string('name', 191);
                $table->string('provider', 32);
                $table->boolean('active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cloud_trial_claims')) {
            Schema::create('cloud_trial_claims', function (Blueprint $table) {
                $table->increments('id');
                $table->string('normalized_phone', 32);
                $table->unsignedInteger('cloud_module_id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();
                $table->unique(['normalized_phone', 'cloud_module_id'], 'cloud_trial_phone_module_unique');
                $table->foreign('cloud_module_id')->references('id')->on('cloud_modules')->onDelete('restrict');
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('cloud_tenant_settings')) {
            Schema::create('cloud_tenant_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->string('key', 191);
                $table->longText('value')->nullable();
                $table->string('type', 32)->nullable();
                $table->timestamps();
                $table->unique(['cloud_tenant_id', 'key'], 'cloud_tenant_settings_tenant_key_unique');
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
            });
        }

        CloudCatalog::install();
    }

    public function down()
    {
        Schema::dropIfExists('cloud_trial_claims');
        Schema::dropIfExists('cloud_payment_methods');
        Schema::dropIfExists('cloud_tenant_settings');
        Schema::dropIfExists('cloud_subscriptions');
        Schema::dropIfExists('cloud_plans');
        Schema::dropIfExists('cloud_modules');
        Schema::dropIfExists('cloud_tenant_memberships');
        Schema::dropIfExists('cloud_tenants');
    }
}
