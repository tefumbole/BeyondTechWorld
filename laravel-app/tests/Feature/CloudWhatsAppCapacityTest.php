<?php

namespace Tests\Feature;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudWhatsAppConnection;
use App\Services\BeyondWasenderService;
use App\Services\Cloud\CloudSubscriptionService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudWhatsAppConnectionResolver;
use App\Services\Cloud\CloudWhatsAppConnectService;
use App\Services\Cloud\WhatsAppCapacityService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudWhatsAppCapacityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cloud.whatsapp_self_connect' => false,
            'cloud.public_onboarding' => true,
            'cloud.whatsapp.provisioning_enabled' => false,
            'cloud.whatsapp.provisioning_policy' => 'MANUAL_APPROVAL',
            'cloud.whatsapp.session_limit' => null,
            'cloud.whatsapp.reserved_sessions' => 1,
            'cloud.whatsapp.trial_provisioning_allowed' => false,
            'cloud.whatsapp.customer_send_enabled' => false,
            'services.whatsapp.stub_fail' => false,
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->integer('role_id')->default(5);
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_100000_add_cloud_portal_payments.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_180000_add_cloud_whatsapp_connection_and_tenant_phone.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_200000_cloud_subscription_enforcement.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_250000_cloud_whatsapp_connection_events.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_260000_cloud_whatsapp_capacity.php', '--force' => true]);
        \App\Services\Cloud\CloudTenantColumns::forget();
    }

    protected function tearDown(): void
    {
        app(CloudTenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_capacity_is_available_only_after_beyond_capacity_is_reserved()
    {
        $this->openPaidPolicy(4);
        $internal = $this->internalConnection();
        $company = $this->paidCustomer('cap-open', '237600333001');
        $snapshot = app(WhatsAppCapacityService::class)->snapshot();
        $this->assertSame(4, $snapshot['limit']);
        $this->assertSame(1, $snapshot['beyond']);
        $this->assertSame(3, $snapshot['available']);
        $connection = app(CloudWhatsAppConnectService::class)->begin($company['tenant'], $company['user']);
        $this->assertSame('AWAITING_QR', $connection->status);
        $this->assertNotSame($internal->id, $connection->id);
        $this->assertSame('CONSUMED', DB::table('cloud_whatsapp_slot_reservations')->value('status'));
        $this->assertSame(2, app(WhatsAppCapacityService::class)->availableCustomerSlots());
    }

    public function test_an_unknown_or_exhausted_limit_refuses_another_customer_session()
    {
        $this->openPaidPolicy(null);
        $company = $this->paidCustomer('cap-unknown', '237600333002');
        $this->assertSame(0, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        $this->assertNull(app(WhatsAppCapacityService::class)->snapshot()['limit']);
        $this->refuse($company);
        $this->openPaidPolicy(2);
        $this->internalConnection();
        $first = $this->paidCustomer('cap-full-a', '237600333003');
        app(CloudWhatsAppConnectService::class)->begin($first['tenant'], $first['user']);
        $second = $this->paidCustomer('cap-full-b', '237600333004');
        $this->assertSame(0, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        $this->refuse($second);
        $this->assertSame(0, CloudWhatsAppConnection::where('cloud_tenant_id', $second['tenant']->id)->count());
    }

    public function test_reserved_sessions_cannot_be_given_to_a_customer()
    {
        $this->openPaidPolicy(2);
        config(['cloud.whatsapp.reserved_sessions' => 2]);
        $this->internalConnection();
        $company = $this->paidCustomer('cap-reserve', '237600333005');
        $this->assertSame(0, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        $this->refuse($company);
    }

    public function test_the_final_slot_cannot_be_reserved_twice()
    {
        $this->openPaidPolicy(2);
        $this->internalConnection();
        $first = $this->paidCustomer('cap-race-a', '237600333006');
        $second = $this->paidCustomer('cap-race-b', '237600333007');
        $capacity = app(WhatsAppCapacityService::class);
        DB::transaction(function () use ($capacity, $first, $second) {
            $capacity->lock();
            $capacity->reserve($first['tenant']);
            $failed = false;
            try {
                $capacity->reserve($second['tenant']);
            } catch (\RuntimeException $e) {
                $failed = strpos($e->getMessage(), 'currently unavailable') !== false;
            }
            $this->assertTrue($failed);
        });
        $this->assertSame(1, DB::table('cloud_whatsapp_slot_reservations')->where('status', 'RESERVED')->count());
        $this->refuse($second);
    }

    public function test_a_failed_provider_call_releases_the_reservation()
    {
        $this->openPaidPolicy(2);
        $this->internalConnection();
        $company = $this->paidCustomer('cap-fail', '237600333008');
        config(['services.whatsapp.stub_fail' => true]);
        $failed = false;
        try {
            app(CloudWhatsAppConnectService::class)->begin($company['tenant'], $company['user']);
        } catch (\RuntimeException $e) {
            $failed = true;
        }
        $this->assertTrue($failed);
        $row = CloudWhatsAppConnection::where('cloud_tenant_id', $company['tenant']->id)->first();
        $this->assertSame('ERROR', $row->status);
        $this->assertSame('RELEASED', DB::table('cloud_whatsapp_slot_reservations')->value('status'));
        $this->assertSame(1, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        config(['services.whatsapp.stub_fail' => false]);
        $next = $this->paidCustomer('cap-fail-next', '237600333009');
        $this->assertSame('AWAITING_QR', app(CloudWhatsAppConnectService::class)->begin($next['tenant'], $next['user'])->status);
    }

    public function test_a_messaging_trial_does_not_create_a_wasender_session()
    {
        $this->openPaidPolicy(10);
        $company = $this->customer('cap-trial', '237600333010');
        app(CloudSubscriptionService::class)->startTrial($company['tenant'], CloudPlan::where('code', 'MESSAGING_MONTHLY')->first());
        $this->assertSame(0, CloudWhatsAppConnection::where('cloud_tenant_id', $company['tenant']->id)->count());
        $capacity = app(WhatsAppCapacityService::class);
        $this->assertTrue($capacity->canUseMessaging($company['tenant']));
        $this->assertFalse($capacity->canProvisionWhatsApp($company['tenant']));
        $this->assertFalse($capacity->canSendWhatsApp($company['tenant']));
        $this->refuse($company);
        $this->assertEquals(5000, (float) CloudPlan::where('code', 'MESSAGING_MONTHLY')->value('price'));
    }

    public function test_a_customer_cannot_use_the_beyond_session()
    {
        $this->openPaidPolicy(4);
        $internal = $this->internalConnection();
        $company = $this->paidCustomer('cap-iso', '237600333011');
        $own = app(CloudWhatsAppConnectService::class)->begin($company['tenant'], $company['user']);
        $this->assertNotSame($internal->provider_connection_id, $own->provider_connection_id);
        $this->assertNotSame('services.whatsapp.wasender_api_key', $own->credentials_reference);
        app(CloudTenantContext::class)->set($company['tenant']);
        $sent = app(BeyondWasenderService::class)->sendTextRaw('237600333011', 'customer');
        $this->assertSame('WhatsApp is not connected for this company.', $sent['error']);
        $this->assertSame('ACTIVE', $internal->fresh()->status);
        $this->assertFalse(app(WhatsAppCapacityService::class)->canSendWhatsApp($company['tenant']));
    }

    public function test_disabled_provisioning_blocks_the_qr_page()
    {
        $company = $this->paidCustomer('cap-qr', '237600333012');
        $connection = CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'provider' => 'stub',
            'provider_connection_id' => 'stub-hidden',
            'status' => 'AWAITING_QR',
            'credentials_reference' => 'stub',
        ]);
        config(['cloud.whatsapp_self_connect' => true, 'cloud.whatsapp.provisioning_enabled' => false]);
        $this->actingAs($company['user'])->get('/cloud/messaging')
            ->assertOk()
            ->assertSee('Admin setup required')
            ->assertDontSee('Connect WhatsApp');
        $this->actingAs($company['user'])->get('/cloud/messaging/whatsapp/'.$connection->id.'/qr')->assertStatus(404);
    }

    public function test_connection_entitlement_is_separate_from_the_messaging_module()
    {
        $this->openPaidPolicy(4);
        $company = $this->customer('cap-entitlement', '237600333013');
        $subscription = app(CloudSubscriptionService::class)->startTrial(
            $company['tenant'],
            CloudPlan::where('code', 'MESSAGING_MONTHLY')->first()
        );
        $capacity = app(WhatsAppCapacityService::class);
        $this->assertTrue($capacity->canUseMessaging($company['tenant']));
        $this->assertFalse($capacity->canProvisionWhatsApp($company['tenant']));
        $subscription->status = CloudSubscriptionStatus::ACTIVE;
        $subscription->current_period_end = now()->addMonth();
        $subscription->save();
        $this->assertTrue($capacity->canProvisionWhatsApp($company['tenant']));
        config(['cloud.whatsapp.provisioning_policy' => 'PAID_ADDON_REQUIRED']);
        $this->assertTrue($capacity->canUseMessaging($company['tenant']));
        $this->assertFalse($capacity->canProvisionWhatsApp($company['tenant']));
        config(['cloud.whatsapp.provisioning_policy' => 'CUSTOMER_PROVIDER_ACCOUNT']);
        $this->assertFalse($capacity->canProvisionWhatsApp($company['tenant']));
        config(['cloud.whatsapp.provisioning_policy' => 'MANUAL_APPROVAL']);
        $this->assertFalse($capacity->canProvisionWhatsApp($company['tenant']));
    }

    public function test_provider_capacity_follows_configuration()
    {
        $this->internalConnection();
        config([
            'cloud.whatsapp.provisioning_enabled' => true,
            'cloud.whatsapp.provisioning_policy' => 'INCLUDED_IN_PLAN',
            'cloud.whatsapp.session_limit' => 3,
            'cloud.whatsapp.reserved_sessions' => 1,
        ]);
        $this->assertSame(2, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        config(['cloud.whatsapp.session_limit' => 10]);
        $this->assertSame(9, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        config(['cloud.whatsapp.session_limit' => null]);
        $this->assertSame(0, app(WhatsAppCapacityService::class)->availableCustomerSlots());
        $this->assertNull(app(WhatsAppCapacityService::class)->snapshot()['available']);
    }

    public function test_platform_admin_inventory_hides_secrets_and_stays_off_the_customer_portal()
    {
        $this->seedPlatformRole();
        $internal = $this->internalConnection();
        $internal->phone_number = '237675321739';
        $internal->credentials_reference = 'services.whatsapp.wasender_api_key';
        $internal->save();
        $company = $this->customer('cap-admin', '237655500099');
        CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'provider' => 'stub',
            'provider_connection_id' => 'stub-customer-session',
            'phone_number' => '237655500099',
            'status' => 'CONNECTED',
            'credentials_reference' => 'sk_live_should_not_render',
        ]);
        $admin = User::create([
            'name' => 'Platform',
            'email' => 'platform-wa@demo.test',
            'password' => Hash::make('portal-secret'),
            'role_id' => 1,
            'is_active' => 1,
        ]);
        $page = $this->actingAs($admin)->get('/admin/subscriptions/whatsapp');
        $page->assertOk();
        $page->assertSee('Unknown');
        $page->assertSee('cap-admin');
        $page->assertSee('**99');
        $page->assertSee('stub-customer-session');
        $page->assertDontSee('sk_live_should_not_render');
        $page->assertDontSee('services.whatsapp.wasender_api_key');
        $page->assertDontSee('237655500099');
        $this->actingAs($company['user'])->get('/admin/subscriptions/whatsapp')->assertStatus(403);
    }

    public function test_customer_sessions_do_not_change_the_beyond_queue_tenant()
    {
        $internal = $this->internalConnection();
        $company = $this->customer('cap-queue', '237600333014');
        CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'provider' => 'stub',
            'provider_connection_id' => 'stub-queue',
            'status' => 'CONNECTED',
            'credentials_reference' => 'stub',
        ]);
        $this->assertSame($internal->cloud_tenant_id, app(CloudWhatsAppConnectionResolver::class)->soleTenantId());
    }

    protected function refuse(array $company)
    {
        $failed = false;
        try {
            app(CloudWhatsAppConnectService::class)->begin($company['tenant'], $company['user']);
        } catch (\RuntimeException $e) {
            $failed = strpos($e->getMessage(), 'currently unavailable') !== false;
        }
        $this->assertTrue($failed);
    }

    protected function openPaidPolicy($limit)
    {
        config([
            'cloud.whatsapp_self_connect' => true,
            'cloud.whatsapp.provisioning_enabled' => true,
            'cloud.whatsapp.provisioning_policy' => 'INCLUDED_IN_PLAN',
            'cloud.whatsapp.session_limit' => $limit,
            'cloud.whatsapp.reserved_sessions' => 1,
            'cloud.whatsapp.trial_provisioning_allowed' => false,
        ]);
    }

    protected function paidCustomer($slug, $phone)
    {
        $company = $this->customer($slug, $phone);
        $subscription = app(CloudSubscriptionService::class)->startTrial(
            $company['tenant'],
            CloudPlan::where('code', 'MESSAGING_MONTHLY')->first()
        );
        $subscription->status = CloudSubscriptionStatus::ACTIVE;
        $subscription->current_period_end = now()->addMonth();
        $subscription->save();

        return $company;
    }

    protected function customer($slug, $phone)
    {
        $user = User::create([
            'name' => $slug,
            'email' => $slug.'@demo.test',
            'password' => Hash::make('portal-secret'),
            'phone' => $phone,
            'role_id' => 5,
            'is_active' => 1,
        ]);
        $tenant = CloudTenant::create([
            'name' => $slug,
            'slug' => $slug,
            'type' => CloudTenantType::CUSTOMER,
            'status' => CloudTenantStatus::ACTIVE,
            'phone' => $phone,
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);
        CloudTenantMembership::create([
            'cloud_tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'membership_role' => CloudMembershipRole::OWNER,
            'is_owner' => true,
            'status' => CloudMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        return ['user' => $user, 'tenant' => $tenant];
    }

    protected function internalConnection()
    {
        $tenant = CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => CloudTenantStatus::ACTIVE,
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);

        return CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $tenant->id,
            'provider' => 'wasender',
            'provider_connection_id' => 'beyond-session',
            'status' => 'ACTIVE',
            'credentials_reference' => 'services.whatsapp.wasender_api_key',
        ]);
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
}
