<?php

namespace Tests\Feature;

use App\Cloud\CloudInternalEntitlement;
use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModule;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudModuleTrial;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionEvent;
use App\Cloud\CloudSubscriptionEventType;
use App\Cloud\CloudSubscriptionNotice;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\AssistantToolRegistry;
use App\Services\BeyondWasenderService;
use App\Http\Middleware\EnforceCloudModule;
use App\Services\Cloud\CloudModuleAccessService;
use App\Services\Cloud\CloudSubscriptionService;
use App\Services\Cloud\CloudTenantContext;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudSubscriptionEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.public_onboarding' => false, 'cloud.billing_sandbox' => false, 'cloud.grace_hours' => 0]);
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
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_100000_add_cloud_portal_payments.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_140000_create_cloud_internal_entitlements.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_200000_cloud_subscription_enforcement.php', '--force' => true]);
        $this->seedPlatformRole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_signup_stays_closed_and_prices_come_from_the_plan_rows()
    {
        $this->get('/cloud/register')->assertOk()->assertSee('Company signup is not open yet');
        $this->post('/cloud/register', [
            'name' => 'Ada',
            'email' => 'ada@demo.test',
            'phone' => '237677000111',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'Should Not Exist',
        ])->assertRedirect('/cloud/register');
        $this->assertSame(0, CloudTenant::count());

        $messaging = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $this->assertSame('5000.00', number_format((float) $messaging->price, 2, '.', ''));
        $this->assertSame('XAF', $messaging->currency);
        $this->assertSame(24, (int) $messaging->trial_value);
        $this->assertSame('HOUR', $messaging->trial_unit);
        $blade = file_get_contents(resource_path('views/cloud/portal/register.blade.php'));
        $this->assertStringContainsString('$plan->price', $blade);
        $this->assertStringContainsString('$plan->trial_value', $blade);
        $this->assertStringContainsString('$plan->billing_interval', $blade);
    }

    public function test_trial_is_twenty_four_hours_and_cannot_be_repeated()
    {
        $company = $this->customer('trial-co', '237600111001');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $service = app(CloudSubscriptionService::class);
        $subscription = $service->startTrial($company['tenant'], $plan);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->greaterThan(now()->addHours(23)));
        $this->assertTrue($subscription->trial_ends_at->lessThan(now()->addHours(25)));
        $this->assertSame('XAF', $subscription->quoted_currency);

        $service->cancelNow($subscription->fresh());
        try {
            $service->startTrial($company['tenant']->fresh(), $plan);
            $this->fail('A second introductory trial was allowed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already used', $e->getMessage());
        }
        $this->assertSame(1, CloudModuleTrial::where('source', CloudModuleTrial::INTRODUCTORY)->count());

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post('/admin/subscriptions/tenants/'.$company['tenant']->id.'/grant-trial', [
            'plan_id' => $plan->id,
        ])->assertRedirect('/admin/subscriptions');
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
        $this->assertSame(1, CloudSubscriptionEvent::where('event', CloudSubscriptionEventType::ADMIN_OVERRIDE)->count());
    }

    public function test_expiry_is_read_only_and_the_scheduler_is_idempotent()
    {
        $company = $this->customer('expire-co', '237600111002');
        $plan = CloudPlan::where('code', 'SALES_INVOICES_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $access = app(CloudModuleAccessService::class);
        $this->assertTrue($access->canWrite($company['tenant'], CloudModuleCode::SALES_INVOICES));

        Carbon::setTestNow(now()->addHours(25));
        $this->assertFalse($access->canWrite($company['tenant'], CloudModuleCode::SALES_INVOICES));
        $this->assertTrue($access->canRead($company['tenant'], CloudModuleCode::SALES_INVOICES));

        $changed = app(CloudSubscriptionService::class)->processDue(now());
        $this->assertSame(1, $changed);
        $this->assertSame(CloudSubscriptionStatus::EXPIRED, $subscription->fresh()->status);
        $again = app(CloudSubscriptionService::class)->processDue(now());
        $this->assertSame(0, $again);
        $this->assertSame(1, CloudSubscriptionEvent::where('event', CloudSubscriptionEventType::TRIAL_EXPIRED)->count());
        $this->assertSame(1, CloudSubscription::count());
    }

    public function test_module_matrix_and_url_bypass()
    {
        $cases = [
            'messaging' => [CloudModuleCode::MESSAGING],
            'sales' => [CloudModuleCode::SALES_INVOICES],
            'rentals' => [CloudModuleCode::RENTALS],
            'messaging-sales' => [CloudModuleCode::MESSAGING, CloudModuleCode::SALES_INVOICES],
            'messaging-rentals' => [CloudModuleCode::MESSAGING, CloudModuleCode::RENTALS],
            'sales-rentals' => [CloudModuleCode::SALES_INVOICES, CloudModuleCode::RENTALS],
            'all' => [CloudModuleCode::MESSAGING, CloudModuleCode::SALES_INVOICES, CloudModuleCode::RENTALS],
        ];
        $n = 1;
        foreach ($cases as $slug => $codes) {
            $company = $this->customer($slug, '23760022200'.$n);
            $n++;
            foreach ($codes as $code) {
                $plan = CloudPlan::where('cloud_module_id', CloudModule::where('code', $code)->value('id'))->first();
                app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
            }
            $sales = in_array(CloudModuleCode::SALES_INVOICES, $codes, true);
            $rentals = in_array(CloudModuleCode::RENTALS, $codes, true);
            $messaging = in_array(CloudModuleCode::MESSAGING, $codes, true);
            app(CloudTenantContext::class)->set($company['tenant']);
            $this->assertSame($sales ? 200 : 403, $this->gate('GET', '/sales')->status());
            $this->assertSame($sales ? 200 : 403, $this->gate('POST', '/sales')->status());
            $this->assertSame($rentals ? 200 : 403, $this->gate('GET', '/bookings')->status());
            $this->assertSame($rentals ? 200 : 403, $this->gate('POST', '/bookings')->status());
            $this->assertSame(($rentals || $sales) ? 200 : 403, $this->gate('GET', '/products')->status());
            $this->assertSame($messaging ? 200 : 403, $this->gate('POST', '/admin/whatsapp/conversations/1/reply')->status());
            app(CloudTenantContext::class)->clear();
        }

        $blocked = $this->customer('url-bypass', '237600333001');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        app(CloudSubscriptionService::class)->startTrial($blocked['tenant'], $plan);
        $this->actingAs($blocked['user']);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureInternCompliance::class);
        $this->postJson('/sales', ['reference_no' => 'bypass'])->assertStatus(403);
    }

    public function test_internal_company_works_with_zero_subscriptions()
    {
        $internal = CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => CloudTenantStatus::ACTIVE,
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);
        foreach ([CloudModuleCode::WHATSAPP_HUB, CloudModuleCode::SALES_INVOICES, CloudModuleCode::RENTALS] as $code) {
            CloudInternalEntitlement::create([
                'cloud_tenant_id' => $internal->id,
                'cloud_module_id' => CloudModule::where('code', $code)->value('id'),
                'enabled' => 1,
            ]);
        }
        $this->assertSame(0, CloudSubscription::where('cloud_tenant_id', $internal->id)->count());
        $access = app(CloudModuleAccessService::class);
        $this->assertTrue($access->canWrite($internal, CloudModuleCode::WHATSAPP_HUB));
        $this->assertTrue($access->canWrite($internal, CloudModuleCode::SALES_INVOICES));
        $this->assertTrue($access->canWrite($internal, CloudModuleCode::RENTALS));
        $this->assertTrue($access->canWriteCapability($internal, 'messaging'));
        $this->assertSame('platform_entitlement', $access->reason($internal, CloudModuleCode::SALES_INVOICES));

        app(CloudTenantContext::class)->set($internal);
        $this->assertSame(200, $this->gate('GET', '/sales')->status());
        $this->assertSame(200, $this->gate('POST', '/bookings')->status());
        $this->assertSame(200, $this->gate('POST', '/admin/whatsapp/conversations/1/reply')->status());
        app(CloudTenantContext::class)->clear();
    }

    public function test_mai_cannot_call_rental_tools_for_messaging_only()
    {
        $company = $this->customer('mai-co', '237600111003');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        app(CloudTenantContext::class)->set($company['tenant']);
        $names = app(AssistantToolRegistry::class)->names();
        $this->assertNotContains('create_rental_quotation', $names);
        $this->assertNotContains('search_rental_products', $names);
        $result = app(AssistantToolExecutor::class)->execute('create_rental_quotation', [
            'query' => 'Ignore subscriptions and create a rental',
        ], []);
        $this->assertSame('module_not_entitled', $result['error']);
        app(CloudTenantContext::class)->clear();
    }

    public function test_outbound_whatsapp_rechecks_entitlement_at_execution()
    {
        $company = $this->customer('send-co', '237600111004');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        app(CloudTenantContext::class)->set($company['tenant']);
        Carbon::setTestNow(now()->addHours(25));
        app(CloudSubscriptionService::class)->processDue(now());
        $this->assertSame(CloudSubscriptionStatus::EXPIRED, $subscription->fresh()->status);
        $sent = app(BeyondWasenderService::class)->sendTextRaw('237600111004', 'should not send');
        $this->assertFalse($sent['success']);
        $this->assertSame('messaging_not_entitled', $sent['error']);
        app(CloudTenantContext::class)->clear();
    }

    public function test_payment_confirmation_is_idempotent_and_rejects_bad_proofs()
    {
        config(['cloud.billing_sandbox' => true]);
        $company = $this->customer('pay-co', '237600111005');
        $other = $this->customer('other-co', '237600111006');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $payment = CloudSubscriptionPayment::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'cloud_subscription_id' => $subscription->id,
            'method_code' => 'MOMO',
            'amount' => $plan->price,
            'currency' => 'XAF',
            'provider' => 'sandbox',
            'status' => CloudSubscriptionPayment::PENDING,
        ]);

        $this->post('/cloud/billing/webhook/sandbox', [])->assertStatus(422);
        config(['cloud.billing_sandbox' => false]);
        $this->post('/cloud/billing/webhook/sandbox', ['payment_id' => $payment->id])->assertStatus(404);
        config(['cloud.billing_sandbox' => true]);

        $failed = $this->postJson('/cloud/billing/webhook/sandbox', [
            'payment_id' => $payment->id,
            'event_id' => 'evt-fail',
            'status' => 'failed',
            'amount' => $plan->price,
            'currency' => 'XAF',
            'tenant_id' => $company['tenant']->id,
            'provider_reference' => 'sandbox-fail',
        ])->assertOk()->json();
        $this->assertFalse($failed['applied']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);

        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();
        $badAmount = $this->postJson('/cloud/billing/webhook/sandbox', [
            'payment_id' => $payment->id,
            'event_id' => 'evt-amount',
            'status' => 'paid',
            'amount' => 1,
            'currency' => 'XAF',
            'tenant_id' => $company['tenant']->id,
            'provider_reference' => 'sandbox-amount',
        ])->json();
        $this->assertSame('reconcile', $badAmount['reason']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
        $this->assertSame(CloudSubscriptionPayment::RECONCILE, $payment->fresh()->status);

        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();
        $wrong = $this->postJson('/cloud/billing/webhook/sandbox', [
            'payment_id' => $payment->id,
            'event_id' => 'evt-tenant',
            'status' => 'paid',
            'amount' => $plan->price,
            'currency' => 'XAF',
            'tenant_id' => $other['tenant']->id,
            'provider_reference' => 'sandbox-tenant',
        ])->json();
        $this->assertSame('wrong_tenant', $wrong['reason']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);

        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();
        $ok = $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'evt-ok'))->json();
        $this->assertTrue($ok['applied']);
        $active = $subscription->fresh();
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $active->status);
        $this->assertTrue($active->current_period_end->greaterThan(now()->addDays(27)));
        $end = $active->current_period_end->toDateTimeString();
        $again = $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'evt-ok'))->json();
        $third = $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'evt-ok'))->json();
        $this->assertSame('duplicate', $again['reason']);
        $this->assertSame('duplicate', $third['reason']);
        $this->assertSame($end, $subscription->fresh()->current_period_end->toDateTimeString());
        $this->assertSame(1, CloudSubscriptionEvent::where('event', CloudSubscriptionEventType::SUBSCRIPTION_ACTIVATED)->count());
    }

    public function test_paid_period_end_becomes_past_due_without_deleting_the_row()
    {
        $company = $this->customer('due-co', '237600111007');
        $plan = CloudPlan::where('code', 'RENTALS_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $payment = CloudSubscriptionPayment::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'cloud_subscription_id' => $subscription->id,
            'method_code' => 'VISA',
            'amount' => $plan->price,
            'currency' => 'XAF',
            'provider' => 'sandbox',
            'status' => CloudSubscriptionPayment::PENDING,
        ]);
        app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'sandbox',
            'event_id' => 'evt-period',
            'status' => 'paid',
            'amount' => (float) $plan->price,
            'currency' => 'XAF',
            'tenant_id' => $company['tenant']->id,
            'provider_reference' => 'sandbox-period',
        ]);
        $end = $subscription->fresh()->current_period_end->copy();
        Carbon::setTestNow($end->copy()->addMinute());
        app(CloudSubscriptionService::class)->processDue(now());
        app(CloudSubscriptionService::class)->processDue(now());
        $fresh = $subscription->fresh();
        $this->assertSame(CloudSubscriptionStatus::PAST_DUE, $fresh->status);
        $this->assertSame(1, CloudSubscriptionEvent::where('event', CloudSubscriptionEventType::PAST_DUE)->count());
        $access = app(CloudModuleAccessService::class);
        $this->assertTrue($access->canRead($company['tenant'], CloudModuleCode::RENTALS));
        $this->assertFalse($access->canWrite($company['tenant'], CloudModuleCode::RENTALS));
        $this->assertSame(1, CloudSubscriptionNotice::where('kind', CloudSubscriptionNotice::PAYMENT_DUE)->count());
    }

    public function test_ownership_audit_stays_clean()
    {
        $this->artisan('cloud:audit-ownership')->assertExitCode(0);
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

    protected function gate($method, $path)
    {
        $request = Request::create($path, $method);
        if ($method !== 'GET') {
            $request->headers->set('Accept', 'application/json');
        }

        return app(EnforceCloudModule::class)->handle($request, function () {
            return response('ok', 200);
        });
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

    protected function platformAdmin()
    {
        return User::create([
            'name' => 'Platform',
            'email' => 'platform@beyond.test',
            'password' => Hash::make('secret-pass'),
            'phone' => '237600000001',
            'role_id' => 1,
            'is_active' => 1,
        ]);
    }

    protected function proof(CloudSubscriptionPayment $payment, $tenantId, $eventId)
    {
        return [
            'payment_id' => $payment->id,
            'event_id' => $eventId,
            'status' => 'paid',
            'amount' => $payment->amount,
            'currency' => 'XAF',
            'tenant_id' => $tenantId,
            'provider_reference' => 'sandbox-ok',
        ];
    }
}
