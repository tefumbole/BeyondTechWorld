<?php

namespace Tests\Feature;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Http\Middleware\EnforceCloudModule;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Cloud\CloudModuleAccessService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTenantResolver;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudOnboardingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.public_onboarding' => true, 'cloud.payments_live' => false]);
        $signupFile = storage_path('app/cloud-public-signup');
        if (is_file($signupFile)) {
            @unlink($signupFile);
        }
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
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
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_100000_add_cloud_portal_payments.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_200000_cloud_subscription_enforcement.php', '--force' => true]);
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        DB::table('permissions')->insert(['id' => 1, 'name' => 'subscriptions', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_has_permissions')->insert(['permission_id' => 1, 'role_id' => 1]);
    }

    public function test_public_onboarding_creates_a_customer_owner_and_selected_trials()
    {
        $this->openCompany('Alpha Events', 'alpha@demo.test', '237670000001', ['MESSAGING', 'SALES_INVOICES', 'RENTALS']);
        $tenant = CloudTenant::where('name', 'Alpha Events')->first();
        $this->assertSame(CloudTenantType::CUSTOMER, $tenant->type);
        $this->assertSame(CloudTenantStatus::ACTIVE, $tenant->status);
        $this->assertSame('alpha-events', $tenant->slug);
        $this->assertSame('XAF', $tenant->currency);
        $this->assertSame('Africa/Douala', $tenant->timezone);
        $user = User::where('email', 'alpha@demo.test')->first();
        $this->assertNotSame(1, (int) $user->role_id);
        $this->assertSame('cloud-company-'.$tenant->id, DB::table('roles')->where('id', $user->role_id)->value('name'));
        $this->assertSame(1, $tenant->memberships()->where('is_owner', true)->where('membership_role', CloudMembershipRole::OWNER)->count());
        $this->assertSame(3, CloudSubscription::where('cloud_tenant_id', $tenant->id)->count());
        $this->get('/cloud')->assertSee('Alpha Events')->assertSee('remaining');
        $this->get('/c/alpha-events')->assertOk()->assertSee('Alpha Events');
        $this->assertSame($tenant->id, (int) session(CloudTenantResolver::SESSION_KEY));
        $this->assertFalse(Schema::hasTable('products'));
    }

    public function test_module_matrix_and_zero_modules()
    {
        DB::table('permissions')->insert([
            ['id' => 2, 'name' => 'sales-index', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'whatsapp_module', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'booking_module', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $messaging = $this->openCompany('Msg Co', 'msg@demo.test', '237670000011', ['MESSAGING']);
        $owner = User::where('email', 'msg@demo.test')->first();
        $granted = array_map('intval', DB::table('role_has_permissions')->where('role_id', $owner->role_id)->pluck('permission_id')->all());
        $this->assertContains(3, $granted);
        $this->assertNotContains(2, $granted);
        $this->assertNotContains(1, $granted);
        $this->assertSame(200, $this->gate($messaging, 'GET', 'admin/whatsapp')->getStatusCode());
        $this->assertSame(403, $this->gate($messaging, 'GET', 'sales')->getStatusCode());
        $this->assertSame(403, $this->gate($messaging, 'GET', 'bookings')->getStatusCode());

        $sales = $this->openCompany('Sales Co', 'sales@demo.test', '237670000012', ['SALES_INVOICES']);
        $this->assertSame(200, $this->gate($sales, 'GET', 'sales')->getStatusCode());
        $this->assertSame(403, $this->gate($sales, 'GET', 'admin/whatsapp')->getStatusCode());
        $this->assertSame(403, $this->gate($sales, 'GET', 'bookings')->getStatusCode());

        $rentals = $this->openCompany('Rent Co', 'rent@demo.test', '237670000013', ['RENTALS']);
        $this->assertSame(200, $this->gate($rentals, 'GET', 'bookings')->getStatusCode());
        $this->assertSame(200, $this->gate($rentals, 'GET', 'products')->getStatusCode());
        $this->assertSame(403, $this->gate($rentals, 'POST', 'sales')->getStatusCode());
        $this->assertSame(403, $this->gate($rentals, 'GET', 'admin/whatsapp')->getStatusCode());

        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload('None', 'none@demo.test', '237670000014', [], $match[1]))
            ->assertSessionHasErrors('modules');
        $this->assertNull(CloudTenant::where('name', 'None')->first());
    }

    public function test_duplicate_submit_existing_email_reserved_slug_and_internal_type_are_refused()
    {
        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $payload = $this->payload('Acme Events', 'acme@demo.test', '237670000021', ['MESSAGING'], $match[1]);
        $payload['type'] = 'INTERNAL';
        $payload['role_id'] = 1;
        $this->post('/cloud/register', $payload)->assertRedirect('/cloud');
        $this->post('/cloud/register', $payload)->assertRedirect('/cloud');
        $this->assertSame(1, CloudTenant::where('name', 'Acme Events')->count());
        $this->assertSame(1, CloudSubscription::count());
        $this->assertSame(CloudTenantType::CUSTOMER, CloudTenant::first()->type);
        $this->assertNotSame(1, (int) User::where('email', 'acme@demo.test')->value('role_id'));

        $this->post('/cloud/logout');
        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload('Acme Events', 'acme@demo.test', '237670000022', ['RENTALS'], $match[1]))
            ->assertRedirect('/cloud/login');
        $this->assertSame(1, User::where('email', 'acme@demo.test')->count());

        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload('BeyondTechWorld', 'btw@demo.test', '237670000023', ['MESSAGING'], $match[1]))
            ->assertRedirect('/cloud');
        $this->assertNotSame('beyondtechworld', CloudTenant::where('name', 'BeyondTechWorld')->value('slug'));

        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload('Subscriptions', 'sub@demo.test', '237670000024', ['RENTALS'], $match[1]))
            ->assertRedirect('/cloud');
        $this->assertNotSame('subscriptions', CloudTenant::where('name', 'Subscriptions')->value('slug'));

        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload('Hub Only', 'hub@demo.test', '237670000025', ['WHATSAPP_HUB'], $match[1]))
            ->assertRedirect('/cloud/register');
        $this->assertNull(CloudTenant::where('name', 'Hub Only')->first());
    }

    public function test_second_company_switches_safely_and_public_page_does_not_change_company()
    {
        $alpha = $this->openCompany('Alpha', 'owner@demo.test', '237670000031', ['MESSAGING']);
        $page = $this->get('/cloud');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/companies', [
            'company_name' => 'Beta',
            'modules' => ['RENTALS'],
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud');
        $beta = CloudTenant::where('name', 'Beta')->first();
        $ownerId = (int) $alpha->memberships()->value('user_id');
        $this->assertSame(2, \App\Cloud\CloudTenantMembership::where('user_id', $ownerId)->count());
        $this->post('/cloud/company', ['cloud_tenant_id' => $alpha->id])->assertRedirect();
        $this->assertSame($alpha->id, (int) session(CloudTenantResolver::SESSION_KEY));
        $this->get('/c/'.$beta->slug)->assertOk()->assertSee('Beta');
        $this->assertSame($alpha->id, (int) session(CloudTenantResolver::SESSION_KEY));
        $this->post('/cloud/company', ['cloud_tenant_id' => 99999])->assertStatus(403);
        $this->assertSame($alpha->id, (int) session(CloudTenantResolver::SESSION_KEY));
    }

    public function test_trial_expiry_is_read_only_and_suspension_is_separate()
    {
        $tenant = $this->openCompany('Timed', 'timed@demo.test', '237670000041', ['SALES_INVOICES']);
        $access = app(CloudModuleAccessService::class);
        $this->assertTrue($access->canWriteCapability($tenant, 'sales'));
        Carbon::setTestNow(now()->addHours(25));
        $this->assertFalse($access->canWriteCapability($tenant->fresh(), 'sales'));
        $this->assertTrue($access->canReadCapability($tenant->fresh(), 'sales'));
        Carbon::setTestNow();

        $admin = User::create([
            'name' => 'Platform',
            'email' => 'platform@demo.test',
            'password' => Hash::make('secret-pass'),
            'role_id' => 1,
            'is_active' => 1,
        ]);
        $this->actingAs($admin)->post('/admin/subscriptions/companies/'.$tenant->id.'/suspend')->assertRedirect('/admin/subscriptions');
        $this->assertSame(CloudTenantStatus::SUSPENDED, $tenant->fresh()->status);
        $this->assertFalse($access->canWriteCapability($tenant->fresh(), 'sales'));
        $this->assertSame('company_suspended', $access->decision($tenant->fresh(), 'SALES_INVOICES')['reason']);
        $this->actingAs($admin)->post('/admin/subscriptions/companies/'.$tenant->id.'/reactivate')->assertRedirect('/admin/subscriptions');
        $this->assertSame(CloudTenantStatus::ACTIVE, $tenant->fresh()->status);
    }

    public function test_companies_do_not_see_each_others_products_and_mai_stays_inside_the_company()
    {
        foreach (['products', 'customers', 'sales', 'quotations', 'payments', 'bookings'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->increments('id');
                $blueprint->string('name')->nullable();
                $blueprint->string('code')->nullable();
                $blueprint->boolean('is_active')->default(1);
                $blueprint->decimal('rent_price_per_day', 12, 2)->nullable();
                $blueprint->decimal('rent_price_per_hour', 12, 2)->nullable();
                $blueprint->unsignedInteger('cloud_tenant_id')->nullable();
            });
        }
        $alpha = $this->openCompany('Alpha Goods', 'goods@demo.test', '237670000051', ['RENTALS']);
        $beta = $this->openCompany('Beta Goods', 'bgoods@demo.test', '237670000052', ['RENTALS']);
        CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => CloudTenantStatus::ACTIVE,
        ]);
        DB::table('products')->insert([
            ['name' => 'Alpha speaker', 'is_active' => 1, 'cloud_tenant_id' => $alpha->id, 'rent_price_per_day' => 1000],
            ['name' => 'Beta speaker', 'is_active' => 1, 'cloud_tenant_id' => $beta->id, 'rent_price_per_day' => 1000],
            ['name' => 'Beyond speaker', 'is_active' => 1, 'cloud_tenant_id' => CloudTenant::where('slug', 'beyondtechworld')->value('id'), 'rent_price_per_day' => 1000],
        ]);
        $context = app(CloudTenantContext::class);
        $context->set($alpha);
        $this->assertSame(1, \App\Product::query()->count());
        $this->assertSame('Alpha speaker', \App\Product::query()->value('name'));
        $executor = app(AssistantToolExecutor::class);
        $own = $executor->execute('search_rental_products', ['query' => 'speaker'], []);
        $this->assertCount(1, $own['products']);
        $this->assertSame('Alpha speaker', $own['products'][0]['name']);
        $asked = $executor->execute('search_rental_products', ['query' => 'Beyond speaker', 'cloud_tenant_id' => $beta->id], []);
        $this->assertSame([], $asked['products']);
        $context->set($beta);
        $this->assertSame('Beta speaker', \App\Product::query()->value('name'));
        foreach (['customers' => \App\Customer::class, 'sales' => \App\Sale::class, 'quotations' => \App\Quotation::class, 'payments' => \App\Payment::class, 'bookings' => \App\Booking::class] as $table => $model) {
            DB::table($table)->insert([
                ['name' => 'Alpha row', 'cloud_tenant_id' => $alpha->id],
                ['name' => 'Beyond row', 'cloud_tenant_id' => CloudTenant::where('slug', 'beyondtechworld')->value('id')],
            ]);
            $context->set($alpha);
            $this->assertSame(1, $model::query()->count());
            $this->assertSame('Alpha row', $model::query()->value('name'));
        }
        $this->get('/c/'.$alpha->slug)->assertDontSee('Beyond speaker')->assertDontSee('Beta speaker');
        $this->artisan('cloud:audit-ownership')->assertExitCode(0);
    }

    public function test_signup_file_closes_registration_without_a_deploy()
    {
        config(['cloud.public_onboarding' => true]);
        $path = \App\Services\Cloud\CloudPublicSignup::path();
        \App\Services\Cloud\CloudPublicSignup::set(false);
        $this->get('/cloud/register')->assertSee('Company signup is not open yet')->assertDontSee('Start free trial');
        $this->post('/cloud/register', $this->payload('Closed Co', 'closed@demo.test', '237670000081', ['MESSAGING'], 'token'))
            ->assertRedirect('/cloud/register');
        $this->assertNull(CloudTenant::where('name', 'Closed Co')->first());
        \App\Services\Cloud\CloudPublicSignup::set(true);
        $this->get('/cloud/register')->assertSee('Start free trial')->assertSee('Monthly total');
        @unlink($path);
    }

    public function test_validation_gate_no_longer_opens_public_signup()
    {
        config(['cloud.public_onboarding' => false]);
        $this->get('/cloud/register')->assertSee('Company signup is not open yet');
        $gate = app(\App\Services\Cloud\CloudOnboardingGate::class)->issue();
        $page = $this->withHeaders(['X-Cloud-Onboarding-Gate' => $gate])->get('/cloud/register');
        $page->assertSee('Company signup is not open yet');
        $page->assertDontSee('name="validation_token"');
        $this->post('/cloud/register', $this->payload('Gate Co', 'gate@demo.test', '237670000071', ['MESSAGING'], 'not-a-form') + ['validation_token' => $gate])
            ->assertRedirect('/cloud/register');
        $this->assertNull(CloudTenant::where('name', 'Gate Co')->first());
    }

    public function test_customer_company_does_not_read_beyond_whatsapp_groups()
    {
        $tenant = $this->openCompany('Own Line', 'own@demo.test', '237670000091', ['MESSAGING']);
        app(CloudTenantContext::class)->set($tenant);
        $listed = app(\App\Services\BeyondWasenderService::class)->listGroups();
        $this->assertFalse($listed['success']);
        $this->assertSame('WhatsApp is not connected for this company.', $listed['error']);
        $this->assertSame([], $listed['groups']);
        $saved = app(\App\Services\WhatsApp\GroupContactExportService::class)->memberships();
        $this->assertSame([], $saved['groups']);
        app(CloudTenantContext::class)->clear();
    }

    public function test_messaging_page_does_not_connect_whatsapp()
    {
        $this->openCompany('Chat Co', 'chat@demo.test', '237670000061', ['MESSAGING']);
        $this->get('/cloud/messaging')
            ->assertOk()
            ->assertSee('Not connected')
            ->assertSee('Admin setup required')
            ->assertSee('SMS: Not available')
            ->assertDontSee('Infobip');
        $this->assertSame(0, DB::getSchemaBuilder()->hasTable('cloud_whatsapp_connections') ? DB::table('cloud_whatsapp_connections')->count() : 0);
    }

    protected function openCompany($name, $email, $phone, array $modules)
    {
        $this->post('/cloud/logout');
        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', $this->payload($name, $email, $phone, $modules, $match[1]))->assertRedirect('/cloud');

        return CloudTenant::where('name', $name)->first();
    }

    protected function payload($name, $email, $phone, array $modules, $token)
    {
        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => $phone,
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'account_kind' => 'company',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => $phone,
            'code' => $sent->json('testing_code'),
        ]);

        return [
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'email' => $email,
            'phone' => $phone,
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => $name,
            'modules' => $modules,
            'onboard_token' => $token,
        ];
    }

    protected function gate(CloudTenant $tenant, $method, $path)
    {
        app(CloudTenantContext::class)->set($tenant);
        $request = Request::create('/'.$path, $method);

        return app(EnforceCloudModule::class)->handle($request, function () {
            return response('ok', 200);
        });
    }
}
