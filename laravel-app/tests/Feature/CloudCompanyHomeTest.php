<?php

namespace Tests\Feature;

use App\Biller;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantType;
use App\Customer;
use App\Sale;
use App\Services\Cloud\CloudCompanyDashboard;
use App\Services\Cloud\CloudCompanyDefaults;
use App\Services\Cloud\CloudTenantContext;
use App\Unit;
use App\Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudCompanyHomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.isolate_queries' => true]);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->unsignedInteger('biller_id')->nullable();
            $table->unsignedInteger('warehouse_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php', '--force' => true]);
        foreach ([
            'warehouses' => ['name'],
            'units' => ['unit_code', 'unit_name'],
            'brands' => ['title'],
            'categories' => ['name'],
            'customers' => ['name'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->increments('id');
                $blueprint->unsignedInteger('cloud_tenant_id')->nullable();
                foreach ($columns as $column) {
                    $blueprint->string($column)->nullable();
                }
                $blueprint->string('phone')->nullable();
                $blueprint->string('email')->nullable();
                $blueprint->string('phone_number')->nullable();
                $blueprint->string('address')->nullable();
                $blueprint->string('city')->nullable();
                $blueprint->string('company_name')->nullable();
                $blueprint->unsignedInteger('customer_group_id')->nullable();
                $blueprint->string('operator')->nullable();
                $blueprint->double('operation_value')->nullable();
                $blueprint->unsignedInteger('base_unit')->nullable();
                $blueprint->unsignedInteger('parent_id')->nullable();
                $blueprint->double('deposit')->nullable();
                $blueprint->double('expense')->nullable();
                $blueprint->double('points')->nullable();
                $blueprint->boolean('is_active')->default(1);
                $blueprint->timestamps();
            });
        }
        Schema::create('billers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->double('percentage')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->double('grand_total')->default(0);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('billers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('units');
        Schema::dropIfExists('warehouses');
        parent::tearDown();
    }

    public function test_a_new_company_gets_walk_in_and_named_sales_defaults()
    {
        $tenant = $this->company('Alpha Bridge', 'Alpha Bridge', 'company');
        app(CloudCompanyDefaults::class)->ensure($tenant);
        app(CloudCompanyDefaults::class)->ensure($tenant);

        app(CloudTenantContext::class)->set($tenant);
        $this->assertSame(1, Customer::where('name', 'Walk-in')->count());
        $this->assertSame('Alpha Bridge', Warehouse::first()->name);
        $this->assertSame('Alpha Bridge', Unit::first()->unit_name);
        $this->assertSame(1, Biller::where('company_name', 'Alpha Bridge')->count());
        $user = DB::table('users')->where('email', 'alpha.bridge@example.com')->first();
        $this->assertSame(Warehouse::first()->id, (int) $user->warehouse_id);
        $this->assertSame(Biller::first()->id, (int) $user->biller_id);
    }

    public function test_a_personal_signup_uses_the_person_name()
    {
        $tenant = $this->company('Ada Lovelace', 'Ada Systems', 'personal');
        app(CloudCompanyDefaults::class)->ensure($tenant);
        app(CloudTenantContext::class)->set($tenant);
        $this->assertSame('Ada Lovelace', Warehouse::first()->name);
        $this->assertSame('Walk-in', Customer::first()->name);
    }

    public function test_dashboard_counts_only_this_company()
    {
        $alpha = $this->company('Alpha Bridge', 'Alpha Bridge', 'company');
        $other = CloudTenant::create([
            'name' => 'Other',
            'system_name' => 'Other',
            'slug' => 'other-company',
            'type' => CloudTenantType::CUSTOMER,
            'status' => 'ACTIVE',
        ]);
        DB::table('sales')->insert([
            ['cloud_tenant_id' => $alpha->id, 'reference_no' => 'A-1', 'grand_total' => 1500, 'created_at' => now(), 'updated_at' => now()],
            ['cloud_tenant_id' => $other->id, 'reference_no' => 'B-1', 'grand_total' => 9000, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('products')->insert([
            ['cloud_tenant_id' => $alpha->id, 'name' => 'Cable', 'created_at' => now(), 'updated_at' => now()],
            ['cloud_tenant_id' => $other->id, 'name' => 'Secret', 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(CloudTenantContext::class)->set($alpha);
        $snapshot = app(CloudCompanyDashboard::class)->snapshot();
        $this->assertSame(1500.0, $snapshot['monthSales']);
        $this->assertSame(1500.0, array_sum($snapshot['salesSeries']));
        $this->assertSame(1, $snapshot['productCount']);
        $this->assertSame('A-1', $snapshot['recentSales'][0]['reference']);
        $this->assertSame(0, Sale::where('reference_no', 'B-1')->count());
    }

    protected function company($name, $systemName, $kind)
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => 'secret',
            'role_id' => 5,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenant = CloudTenant::create([
            'name' => $name,
            'system_name' => $systemName,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'type' => CloudTenantType::CUSTOMER,
            'status' => 'ACTIVE',
            'email' => 'office@example.com',
            'phone' => '237670000000',
            'city' => 'Bamenda',
        ]);
        CloudTenantMembership::create([
            'cloud_tenant_id' => $tenant->id,
            'user_id' => $userId,
            'membership_role' => 'OWNER',
            'is_owner' => true,
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
        DB::table('cloud_tenant_settings')->insert([
            'cloud_tenant_id' => $tenant->id,
            'key' => 'account_kind',
            'value' => $kind,
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tenant;
    }
}
