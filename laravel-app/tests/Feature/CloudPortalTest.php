<?php

namespace Tests\Feature;

use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantSetting;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->integer('role_id')->default(5);
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_02_100000_add_cloud_portal_payments.php',
            '--force' => true,
        ]);
        $this->seedPlatformRole();
    }

    protected function seedPlatformRole()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'subscriptions',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_has_permissions')->insert([
            'permission_id' => 1,
            'role_id' => 1,
        ]);
    }

    public function test_platform_admin_can_change_a_price_without_rewriting_an_open_quote()
    {
        $this->registerCompany();
        $plan = CloudPlan::where('code', 'SALES_INVOICES_MONTHLY')->first();
        $this->post('/cloud/subscribe/'.$plan->id.'/trial')->assertRedirect('/cloud');
        $quoted = CloudSubscription::first();
        $this->assertEquals(5000, (float) $quoted->quoted_price);
        $this->post('/cloud/logout');

        $admin = User::create([
            'name' => 'Platform',
            'email' => 'platform@example.com',
            'password' => Hash::make('secret-pass'),
            'phone' => '237677000001',
            'role_id' => 1,
            'is_active' => 1,
        ]);

        $this->actingAs($admin)->post('/admin/subscriptions/plans/'.$plan->id, [
            'price' => 7500,
            'currency' => 'XAF',
            'trial_value' => 24,
            'trial_unit' => 'HOUR',
            'active' => 1,
        ])->assertRedirect('/admin/subscriptions');

        $this->assertEquals(7500, (float) $plan->fresh()->price);
        $this->assertEquals(5000, (float) $quoted->fresh()->quoted_price);
    }

    public function test_company_portal_trial_is_once_per_phone_and_settings_stay_on_that_company()
    {
        $this->get('/cloud/register')->assertOk()->assertSee('Create your company');
        $this->registerCompany();
        $this->assertAuthenticated();
        $this->get('/cloud')->assertSee('Demo Events');
        $this->get('/cloud/subscribe')->assertOk()->assertSee('10,000')->assertSee('5,000');
        $this->get('/cloud/settings')->assertOk()->assertSee('Business rules');

        $plan = CloudPlan::where('code', 'WHATSAPP_HUB_MONTHLY')->first();
        $this->post('/cloud/subscribe/'.$plan->id.'/trial')->assertRedirect('/cloud');
        $subscription = CloudSubscription::first();
        $this->assertSame('TRIALING', $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->greaterThan(now()->addHours(23)));
        $this->assertTrue($subscription->trial_ends_at->lessThan(now()->addHours(25)));

        $this->post('/cloud/settings', [
            'system_name' => 'Demo Events Manager',
            'business_summary' => 'We stage concerts.',
            'services' => 'Sound and screens',
            'business_rules' => 'Deposits are required before delivery.',
        ])->assertRedirect('/cloud/settings');

        $tenant = CloudTenant::where('name', 'Demo Events')->first();
        $this->assertSame('Demo Events Manager', $tenant->fresh()->system_name);
        $this->assertSame(
            'Deposits are required before delivery.',
            CloudTenantSetting::where('cloud_tenant_id', $tenant->id)->where('key', 'business_rules')->value('value')
        );

        $png = $this->heroUpload();
        $this->post('/cloud/settings', [
            'system_name' => 'Demo Events Manager',
            'hero' => $png,
        ])->assertRedirect('/cloud/settings');
        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant->hero_path);
        $this->get('/cloud/hero/'.$tenant->uuid)->assertOk();

        $this->post('/cloud/logout');
        $this->post('/cloud/register', [
            'name' => 'Second Owner',
            'email' => 'second@demo.test',
            'phone' => '+237677000111',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'Other Events',
        ])->assertRedirect('/cloud');
        $this->post('/cloud/subscribe/'.$plan->id.'/trial')->assertRedirect('/cloud/subscribe');
        $this->assertSame(1, CloudSubscription::count());

        $other = CloudTenant::create([
            'name' => 'Other',
            'slug' => 'other-co',
            'phone' => '237699000222',
        ]);
        $this->get('/cloud/hero/'.$other->uuid)->assertNotFound();
    }

    public function test_staff_cannot_open_subscription_admin()
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => Hash::make('secret-pass'),
            'phone' => '237677000009',
            'role_id' => 5,
            'is_active' => 1,
        ]);
        $before = CloudPlan::find(1)->price;
        $this->actingAs($staff)->post('/admin/subscriptions/plans/1', [
            'price' => 1,
            'currency' => 'XAF',
            'trial_value' => 24,
            'trial_unit' => 'HOUR',
        ])->assertRedirect('/admin')->assertSessionHas('not_permitted');
        $this->assertEquals($before, CloudPlan::find(1)->fresh()->price);
    }

    protected function registerCompany()
    {
        $this->post('/cloud/register', [
            'name' => 'Ada Owner',
            'email' => 'ada@demo.test',
            'phone' => '+237677000111',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'Demo Events',
        ])->assertRedirect('/cloud');
    }

    protected function heroUpload()
    {
        $image = imagecreatetruecolor(500, 250);
        imagefilledrectangle($image, 0, 0, 499, 249, imagecolorallocate($image, 11, 63, 144));
        $path = tempnam(sys_get_temp_dir(), 'hero');
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'hero.png', 'image/png', null, true);
    }
}
