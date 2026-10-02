<?php

namespace Tests\Feature;

use App\Cloud\CloudSubscription;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantType;
use App\Property\Tenancy;
use App\Services\Cloud\CloudInternalEntitlementPolicy;
use App\Services\Cloud\CloudPortalService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTenantContextRunner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudInternalTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareTables();
        foreach ([
            'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php',
            'database/migrations/2026_10_02_140000_create_cloud_internal_entitlements.php',
            'database/migrations/2026_10_02_141000_add_nullable_cloud_tenant_to_catalog.php',
        ] as $path) {
            $this->artisan('migrate', ['--path' => $path, '--force' => true]);
        }
    }

    public function test_internal_tenant_is_idempotent_and_does_not_use_a_subscription()
    {
        $this->artisan('cloud:create-internal-tenant');
        $this->artisan('cloud:create-internal-tenant');

        $this->assertSame(1, CloudTenant::where('type', CloudTenantType::INTERNAL)->count());
        $tenant = CloudTenant::where('slug', 'beyondtechworld')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('BeyondTechWorld', $tenant->name);
        $this->assertSame('Beyond Enterprise', $tenant->legal_name);
        $this->assertSame('Beyond Tech World', $tenant->system_name);
        $this->assertSame('info@beyondcompanyltd.com', $tenant->email);
        $this->assertSame('XAF', $tenant->currency);
        $this->assertSame('Africa/Douala', $tenant->timezone);
        $this->assertSame('ACTIVE', $tenant->status);

        $this->assertSame(2, CloudTenantMembership::count());
        $this->assertSame(1, CloudTenantMembership::where('membership_role', 'OWNER')->where('user_id', 2)->count());
        $this->assertSame(1, CloudTenantMembership::where('membership_role', 'STAFF')->where('user_id', 4)->count());
        $this->assertSame(0, CloudTenantMembership::where('user_id', 1)->count());
        $this->assertSame(0, CloudTenantMembership::where('user_id', 3)->count());
        $this->assertSame(0, CloudTenantMembership::where('user_id', 5)->count());
        $this->assertSame(1, (int) DB::table('users')->where('id', 1)->value('role_id'));
        $this->assertSame(2, (int) DB::table('users')->where('id', 2)->value('role_id'));

        $this->assertSame(0, CloudSubscription::where('cloud_tenant_id', $tenant->id)->count());
        $policy = app(CloudInternalEntitlementPolicy::class);
        $this->assertTrue($policy->grants($tenant, 'WHATSAPP_HUB'));
        $this->assertTrue($policy->grants($tenant, 'SALES_INVOICES'));
        $this->assertTrue($policy->grants($tenant, 'RENTALS'));
        $this->assertFalse($policy->grants($tenant, 'MESSAGING'));

        $plan = \App\Cloud\CloudPlan::first();
        try {
            app(CloudPortalService::class)->startTrial($tenant, $plan);
            $this->fail('Internal tenant started a trial.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('trial for this module', $e->getMessage());
        }
        $this->assertSame(0, CloudSubscription::count());
    }

    public function test_dry_run_does_not_write_and_execute_skips_other_companies()
    {
        $this->artisan('cloud:create-internal-tenant');
        $tenant = CloudTenant::where('slug', 'beyondtechworld')->first();

        $this->artisan('cloud:rehearse-beyond-migration');
        $this->assertNull(DB::table('products')->where('id', 1)->value('cloud_tenant_id'));

        $other = CloudTenant::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
            'type' => CloudTenantType::CUSTOMER,
            'status' => 'ACTIVE',
        ]);
        DB::table('products')->insert([
            'id' => 2,
            'name' => 'Foreign Speaker',
            'cloud_tenant_id' => $other->id,
        ]);

        $this->artisan('cloud:rehearse-beyond-migration', ['--execute' => true]);

        $this->assertSame($tenant->id, (int) DB::table('products')->where('id', 1)->value('cloud_tenant_id'));
        $this->assertSame($other->id, (int) DB::table('products')->where('id', 2)->value('cloud_tenant_id'));
        $this->assertSame(2, DB::table('products')->count());
        $this->assertSame('Speaker', DB::table('products')->where('id', 1)->value('name'));
    }

    public function test_context_runner_clears_after_the_job()
    {
        $this->artisan('cloud:create-internal-tenant');
        $beyond = CloudTenant::where('slug', 'beyondtechworld')->first();
        $other = CloudTenant::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
            'type' => CloudTenantType::CUSTOMER,
            'status' => 'ACTIVE',
        ]);
        $runner = app(CloudTenantContextRunner::class);
        $context = app(CloudTenantContext::class);

        $seen = $runner->run($beyond->id, function () use ($context) {
            return $context->id();
        });
        $this->assertSame($beyond->id, $seen);
        $this->assertNull($context->id());

        $runner->run($other->id, function () use ($context, $other) {
            $this->assertSame($other->id, $context->id());
        });
        $this->assertNull($context->id());
        $this->assertNotSame(Tenancy::class, CloudTenant::class);
        $this->assertSame('tenancies', (new Tenancy())->getTable());
        $this->assertSame('cloud_tenants', (new CloudTenant())->getTable());
    }

    protected function prepareTables()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('role_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Platform', 'email' => 'platform@example.test', 'password' => 'x', 'role_id' => 1, 'is_active' => 1],
            ['id' => 2, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'x', 'role_id' => 2, 'is_active' => 1],
            ['id' => 3, 'name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'x', 'role_id' => 5, 'is_active' => 1],
            ['id' => 4, 'name' => 'Supervisor', 'email' => 'supervisor@example.test', 'password' => 'x', 'role_id' => 15, 'is_active' => 1],
            ['id' => 5, 'name' => 'Old Owner', 'email' => 'old-owner@example.test', 'password' => 'x', 'role_id' => 2, 'is_active' => 0],
        ]);
        Schema::create('general_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_title')->nullable();
            $table->integer('currency')->nullable();
            $table->integer('default_biller_id')->nullable();
        });
        DB::table('general_settings')->insert([
            'site_title' => 'Beyond Tech World',
            'currency' => 3,
            'default_biller_id' => 1,
        ]);
        Schema::create('billers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
        });
        DB::table('billers')->insert([
            'name' => 'Beyond Enterprise',
            'company_name' => 'Beyond Enterprise',
            'email' => 'info@beyondcompanyltd.com',
            'phone_number' => '237675321739',
            'address' => 'Mile Six, Nkwen',
            'city' => 'Bamenda',
        ]);
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('products')->insert(['id' => 1, 'name' => 'Speaker']);
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('customers')->insert(['id' => 1, 'name' => 'Ada']);
        foreach (['categories', 'brands', 'units', 'warehouses', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->increments('id');
                $blueprint->string('name')->nullable();
            });
        }
    }
}
