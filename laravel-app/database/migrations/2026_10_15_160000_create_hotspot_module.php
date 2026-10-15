<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateHotspotModule extends Migration
{
    public function up()
    {
        Schema::create('hotspot_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('timezone')->default('Africa/Douala');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hotspot_routers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('location_id')->index();
            $table->string('name');
            $table->string('identity')->nullable();
            $table->string('api_host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('api_user');
            $table->text('api_password')->nullable();
            $table->string('ros_version')->nullable();
            $table->text('radius_secret')->nullable();
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspot_packages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->string('currency', 8)->default('XAF');
            $table->unsignedInteger('validity_seconds');
            $table->unsignedBigInteger('data_bytes')->nullable();
            $table->unsignedInteger('rate_up')->default(1024);
            $table->unsignedInteger('rate_down')->default(2048);
            $table->unsignedSmallInteger('shared_users')->default(1);
            $table->unsignedSmallInteger('simultaneous_sessions')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamp('profile_pushed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspot_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('package_id')->nullable()->index();
            $table->unsignedInteger('router_id')->nullable()->index();
            $table->string('username');
            $table->text('password_hash')->nullable();
            $table->string('state')->default('pending')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('data_cap_bytes')->nullable();
            $table->unsignedBigInteger('data_used_bytes')->default(0);
            $table->unsignedInteger('connected_seconds')->default(0);
            $table->timestamp('receipt_sent_at')->nullable();
            $table->timestamp('expiry_sent_at')->nullable();
            $table->timestamp('cap_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->string('provision_error')->nullable();
            $table->timestamps();
            $table->index(['cloud_tenant_id', 'username']);
        });

        Schema::create('hotspot_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('subscription_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('package_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('XAF');
            $table->string('method');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->unique();
            $table->string('public_token', 64)->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('sale_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspot_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('router_id')->index();
            $table->unsignedInteger('subscription_id')->nullable()->index();
            $table->string('username')->nullable();
            $table->string('mac')->nullable();
            $table->string('framed_ip')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->string('acct_session_id');
            $table->unsignedInteger('uptime_seconds')->default(0);
            $table->timestamps();
            $table->unique(['router_id', 'acct_session_id']);
        });

        Schema::create('hotspot_voucher_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('package_id')->index();
            $table->unsignedInteger('quantity');
            $table->string('note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspot_vouchers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('package_id')->index();
            $table->unsignedInteger('batch_id')->nullable()->index();
            $table->string('code');
            $table->string('state')->default('available')->index();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedInteger('subscription_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'code']);
        });

        Schema::create('hotspot_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('subscription_id')->index();
            $table->string('mac');
            $table->string('label')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspot_message_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('key');
            $table->text('body');
            $table->unsignedSmallInteger('hours_before')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'key']);
        });

        Schema::create('hotspot_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedInteger('router_id')->nullable()->index();
            $table->string('action');
            $table->string('summary', 500);
            $table->timestamps();
        });

        if (! Schema::hasTable('radcheck')) {
            Schema::create('radcheck', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username')->index();
                $table->string('attribute');
                $table->char('op', 2)->default(':=');
                $table->string('value');
            });
        }
        if (! Schema::hasTable('radreply')) {
            Schema::create('radreply', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username')->index();
                $table->string('attribute');
                $table->char('op', 2)->default(':=');
                $table->string('value');
            });
        }
        if (! Schema::hasTable('radusergroup')) {
            Schema::create('radusergroup', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username')->index();
                $table->string('groupname');
                $table->integer('priority')->default(1);
            });
        }

        $names = ['hotspot_module', 'hotspot.view', 'hotspot.manage', 'hotspot.routers'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($names as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('hotspot_audit_logs');
        Schema::dropIfExists('hotspot_message_templates');
        Schema::dropIfExists('hotspot_devices');
        Schema::dropIfExists('hotspot_vouchers');
        Schema::dropIfExists('hotspot_voucher_batches');
        Schema::dropIfExists('hotspot_sessions');
        Schema::dropIfExists('hotspot_payments');
        Schema::dropIfExists('hotspot_subscriptions');
        Schema::dropIfExists('hotspot_packages');
        Schema::dropIfExists('hotspot_routers');
        Schema::dropIfExists('hotspot_locations');
    }
}
