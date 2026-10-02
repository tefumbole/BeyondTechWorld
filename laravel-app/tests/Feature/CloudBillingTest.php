<?php

namespace Tests\Feature;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Services\Cloud\CloudBillingCheckout;
use App\Services\Cloud\CloudCheckoutService;
use App\Services\Cloud\CloudModuleAccessService;
use App\Services\Cloud\CloudSubscriptionService;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cloud.public_onboarding' => false,
            'cloud.billing_sandbox' => true,
            'cloud.payments_live' => false,
            'cloud.grace_hours' => 0,
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Mail::fake();
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
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_140000_create_cloud_internal_entitlements.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_200000_cloud_subscription_enforcement.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_240000_cloud_subscription_billing.php', '--force' => true]);
        $this->seedPlatformRole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_payment_request_does_not_activate_and_ignores_browser_amount()
    {
        $company = $this->customer('bill-a', '237600111101');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $this->actingAs($company['user'])->post('/cloud/billing/checkout', [
            'subscription_ids' => [$subscription->id],
            'method' => 'MOMO',
            'amount' => 1,
            'currency' => 'USD',
            'cloud_tenant_id' => 999,
        ])->assertRedirect();

        $payment = CloudSubscriptionPayment::first();
        $this->assertEquals(5000, (float) $payment->amount);
        $this->assertSame('XAF', $payment->currency);
        $this->assertSame((int) $company['tenant']->id, (int) $payment->cloud_tenant_id);
        $this->assertSame(CloudSubscriptionPayment::PENDING, $payment->status);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
        $this->get('/cloud/pay/'.$payment->id.'/return?session_id=cs_test_browser')->assertRedirect('/cloud');
        $this->assertSame(CloudSubscriptionPayment::PENDING, $payment->fresh()->status);
    }

    public function test_campay_link_response_does_not_activate_and_uses_the_plan_amount()
    {
        config(['cloud.payments_live' => true, 'services.campay.base_url' => 'https://demo.campay.net/api']);
        $company = $this->customer('bill-link', '237600111116');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $seen = [];
        config(['services.campay.transport' => function ($method, $url, $payload) use (&$seen) {
            $seen = ['method' => $method, 'url' => $url, 'payload' => $payload];

            return [
                'status' => 'SUCCESSFUL',
                'link' => 'https://demo.campay.net/pay/example',
                'reference' => 'campay-link-reference',
            ];
        }]);

        $url = app(CloudCheckoutService::class)->start($company['tenant'], $subscription, 'MOMO');
        $this->assertSame('https://demo.campay.net/pay/example', $url);
        $this->assertSame('POST', $seen['method']);
        $this->assertStringContainsString('https://demo.campay.net/api/get_payment_link/', $seen['url']);
        $this->assertSame('5000', $seen['payload']['amount']);
        $this->assertSame('XAF', $seen['payload']['currency']);
        $this->assertSame('MOMO', $seen['payload']['payment_options']);
        $this->assertArrayHasKey('external_reference', $seen['payload']);
        $this->assertArrayHasKey('redirect_url', $seen['payload']);
        $payment = CloudSubscriptionPayment::first();
        $this->assertSame(CloudSubscriptionPayment::PENDING, $payment->status);
        $this->assertSame('campay-link-reference', $payment->provider_reference);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
        config(['cloud.payments_live' => false]);
    }

    public function test_live_provider_is_not_contacted_while_payments_are_disabled()
    {
        config(['cloud.payments_live' => false, 'cloud.billing_sandbox' => false]);
        $company = $this->customer('bill-off', '237600111102');
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $subscription = app(CloudSubscriptionService::class)->startTrial($company['tenant'], $plan);
        $thrown = false;
        try {
            app(CloudCheckoutService::class)->start($company['tenant'], $subscription, 'MOMO');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
        $this->assertSame(0, CloudSubscriptionPayment::count());
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
    }

    public function test_campay_status_confirmation_activates_once()
    {
        $company = $this->customer('bill-ok', '237600111103');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $payment = $this->campayPayment($company['tenant'], [$subscription]);
        $this->campayTransport($payment, 'SUCCESSFUL', $payment->amount, 'XAF');

        $first = $this->postJson('/cloud/billing/webhook/campay', [
            'external_reference' => $payment->internal_reference,
            'reference' => $payment->provider_reference,
            'status' => 'SUCCESSFUL',
            'amount' => 1,
        ])->assertOk()->json();
        $this->assertTrue($first['applied']);
        $end = $subscription->fresh()->current_period_end->toDateTimeString();
        for ($i = 0; $i < 10; $i++) {
            $again = $this->postJson('/cloud/billing/webhook/campay', [
                'external_reference' => $payment->internal_reference,
                'reference' => $payment->provider_reference,
                'status' => 'SUCCESSFUL',
            ])->json();
            $this->assertSame('duplicate', $again['reason']);
        }
        $this->assertSame($end, $subscription->fresh()->current_period_end->toDateTimeString());
        $this->assertSame(CloudSubscriptionPayment::PROVIDER_CONFIRMED, $payment->fresh()->confirmation_source);
        $this->assertTrue(app(CloudModuleAccessService::class)->canWrite($company['tenant'], CloudModuleCode::MESSAGING));
    }

    public function test_pending_and_failed_campay_results_do_not_activate()
    {
        $company = $this->customer('bill-wait', '237600111104');
        $subscription = $this->trial($company['tenant'], 'SALES_INVOICES_MONTHLY');
        $payment = $this->campayPayment($company['tenant'], [$subscription]);
        $this->campayTransport($payment, 'PENDING', $payment->amount, 'XAF');
        $pending = $this->postJson('/cloud/billing/webhook/campay', [
            'external_reference' => $payment->internal_reference,
            'reference' => $payment->provider_reference,
        ])->json();
        $this->assertSame('pending', $pending['reason']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);

        $this->campayTransport($payment, 'FAILED', $payment->amount, 'XAF');
        $failed = $this->postJson('/cloud/billing/webhook/campay', [
            'external_reference' => $payment->internal_reference,
            'reference' => $payment->provider_reference,
            'status' => 'SUCCESSFUL',
        ])->json();
        $this->assertFalse($failed['applied']);
        $this->assertSame(CloudSubscriptionPayment::FAILED, $payment->fresh()->status);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
    }

    public function test_wrong_amount_currency_reference_and_tenant_do_not_activate()
    {
        $company = $this->customer('bill-bad', '237600111105');
        $other = $this->customer('bill-other', '237600111106');
        $subscription = $this->trial($company['tenant'], 'RENTALS_MONTHLY');
        $payment = $this->campayPayment($company['tenant'], [$subscription]);

        $this->campayTransport($payment, 'SUCCESSFUL', 1, 'XAF');
        $amount = $this->postJson('/cloud/billing/webhook/campay', $this->campayBody($payment))->json();
        $this->assertSame('reconcile', $amount['reason']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);

        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->provider_reference = 'campay-currency-'.$payment->id;
        $payment->save();
        $this->campayTransport($payment, 'SUCCESSFUL', $payment->amount, 'USD');
        $currency = $this->postJson('/cloud/billing/webhook/campay', $this->campayBody($payment->fresh()))->json();
        $this->assertSame('wrong_currency', $currency['reason']);

        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->provider_reference = 'campay-reference-'.$payment->id;
        $payment->save();
        $this->campayTransport($payment, 'SUCCESSFUL', $payment->amount, 'XAF');
        $reference = $this->postJson('/cloud/billing/webhook/campay', [
            'external_reference' => $payment->internal_reference,
            'reference' => 'not-the-stored-reference',
            'tenant_id' => $company['tenant']->id,
        ])->json();
        $this->assertSame('wrong_reference', $reference['reason']);

        $payment->provider_reference = 'campay-tenant-'.$payment->id;
        $payment->save();
        $tenant = $this->postJson('/cloud/billing/webhook/campay', [
            'external_reference' => $payment->internal_reference,
            'reference' => $payment->provider_reference,
            'tenant_id' => $other['tenant']->id,
        ])->json();
        $this->assertSame('wrong_tenant', $tenant['reason']);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);
    }

    public function test_bundle_activates_each_module_and_a_single_module_stays_separate()
    {
        $company = $this->customer('bill-bundle', '237600111107');
        $messaging = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $sales = $this->trial($company['tenant'], 'SALES_INVOICES_MONTHLY');
        $rentals = $this->trial($company['tenant'], 'RENTALS_MONTHLY');
        $bundle = app(CloudBillingCheckout::class)->open($company['tenant'], [$messaging, $sales], 'MOMO');
        $this->assertEquals(10000, (float) $bundle->amount);
        $this->assertCount(2, $bundle->items);
        $bundle->provider = 'sandbox';
        $bundle->save();
        $ok = $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($bundle, $company['tenant']->id, 'bundle-1'))->json();
        $this->assertTrue($ok['applied']);
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $messaging->fresh()->status);
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $sales->fresh()->status);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $rentals->fresh()->status);
        $salesEnd = $sales->fresh()->current_period_end->toDateTimeString();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($bundle, $company['tenant']->id, 'bundle-1'));
        $this->assertSame($salesEnd, $sales->fresh()->current_period_end->toDateTimeString());
    }

    public function test_paying_before_trial_end_keeps_the_remaining_trial()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
        $company = $this->customer('bill-early', '237600111108');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $trialEnd = $subscription->trial_ends_at->copy();
        $payment = $this->campayPayment($company['tenant'], [$subscription]);
        $payment->provider = 'sandbox';
        $payment->save();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'early-1'))->assertOk();
        $fresh = $subscription->fresh();
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $fresh->status);
        $this->assertSame($trialEnd->toDateTimeString(), $fresh->current_period_start->toDateTimeString());
        $this->assertTrue($fresh->current_period_end->greaterThan($trialEnd));
    }

    public function test_paying_after_trial_expiry_starts_now()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
        $company = $this->customer('bill-late', '237600111109');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $subscription->status = CloudSubscriptionStatus::EXPIRED;
        $subscription->trial_ends_at = Carbon::parse('2026-10-01 12:00:00');
        $subscription->current_period_end = Carbon::parse('2026-10-01 12:00:00');
        $subscription->save();
        $payment = $this->campayPayment($company['tenant'], [$subscription]);
        $payment->provider = 'sandbox';
        $payment->save();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'late-1'));
        $fresh = $subscription->fresh();
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $fresh->status);
        $this->assertSame('2026-10-02 12:00:00', $fresh->current_period_start->toDateTimeString());
    }

    public function test_renewal_extends_an_active_period_and_restarts_past_due()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02 12:00:00'));
        $company = $this->customer('bill-renew', '237600111110');
        $active = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $active->status = CloudSubscriptionStatus::ACTIVE;
        $active->current_period_end = Carbon::parse('2026-10-12 12:00:00');
        $active->save();
        $payment = $this->campayPayment($company['tenant'], [$active]);
        $payment->provider = 'sandbox';
        $payment->save();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($payment, $company['tenant']->id, 'renew-1'));
        $this->assertSame('2026-11-12 12:00:00', $active->fresh()->current_period_end->toDateTimeString());

        $past = $this->trial($company['tenant'], 'RENTALS_MONTHLY');
        $past->status = CloudSubscriptionStatus::PAST_DUE;
        $past->current_period_end = Carbon::parse('2026-10-01 12:00:00');
        $past->save();
        $second = $this->campayPayment($company['tenant'], [$past]);
        $second->provider = 'sandbox';
        $second->save();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($second, $company['tenant']->id, 'renew-2'));
        $this->assertSame('2026-10-02 12:00:00', $past->fresh()->current_period_start->toDateTimeString());
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $past->fresh()->status);
    }

    public function test_manual_activation_is_admin_only_and_a_refund_keeps_history()
    {
        $company = $this->customer('bill-manual', '237600111111');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $this->actingAs($company['user'])->post('/cloud/billing/self-activate', [
            'cloud_subscription_id' => $subscription->id,
        ])->assertStatus(403);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $subscription->fresh()->status);

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post('/admin/subscriptions/payments/manual', [
            'cloud_tenant_id' => $company['tenant']->id,
            'cloud_subscription_id' => $subscription->id,
            'method' => 'CASH',
            'reference' => 'offline-1',
            'reason' => 'Paid at the office',
        ])->assertRedirect('/admin/subscriptions/payments');
        $payment = CloudSubscriptionPayment::first();
        $this->assertSame(CloudSubscriptionPayment::ADMIN_CONFIRMED, $payment->confirmation_source);
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $subscription->fresh()->status);
        $end = $subscription->fresh()->current_period_end->toDateTimeString();
        app(CloudSubscriptionService::class)->recordRefund($payment, $admin->id, 'customer asked');
        $this->assertSame(CloudSubscriptionPayment::REFUNDED, $payment->fresh()->status);
        $this->assertSame($end, $subscription->fresh()->current_period_end->toDateTimeString());
        $this->assertNotNull(CloudSubscription::find($subscription->id));
    }

    public function test_tenant_billing_is_isolated_and_receipts_wait_for_success()
    {
        $a = $this->customer('bill-iso-a', '237600111112');
        $b = $this->customer('bill-iso-b', '237600111113');
        $subA = $this->trial($a['tenant'], 'MESSAGING_MONTHLY');
        $subB = $this->trial($b['tenant'], 'MESSAGING_MONTHLY');
        $pending = $this->campayPayment($a['tenant'], [$subA]);
        $paid = $this->campayPayment($b['tenant'], [$subB]);
        $paid->provider = 'sandbox';
        $paid->save();
        $this->postJson('/cloud/billing/webhook/sandbox', $this->proof($paid, $b['tenant']->id, 'iso-b'));

        $this->actingAs($a['user'])->get('/cloud/billing')->assertOk()->assertSee($pending->internal_reference)->assertDontSee($paid->internal_reference);
        $this->actingAs($a['user'])->get('/cloud/billing/'.$pending->id.'/receipt')->assertStatus(404);
        $this->actingAs($a['user'])->get('/cloud/billing/'.$paid->id.'/receipt')->assertStatus(404);
        $this->actingAs($b['user'])->get('/cloud/billing/'.$paid->id.'/receipt')->assertOk()->assertSee('BeyondTechWorld');
        $this->actingAs($admin = $this->platformAdmin())->get('/admin/subscriptions/payments?status=PAID')->assertOk()->assertSee($paid->internal_reference);
        $this->actingAs($admin)->get('/admin/subscriptions/payments?status=PENDING&tenant='.$a['tenant']->id)->assertOk()->assertSee($pending->internal_reference)->assertDontSee($paid->internal_reference);
    }

    public function test_mail_failure_does_not_undo_a_confirmed_payment()
    {
        $company = $this->customer('bill-mail', '237600111114');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $payment = $this->campayPayment($company['tenant'], [$subscription]);
        $payment->provider = 'sandbox';
        $payment->save();
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('smtp down'));
        $result = app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'sandbox',
            'event_id' => 'mail-1',
            'status' => 'paid',
            'amount' => $payment->amount,
            'currency' => 'XAF',
            'tenant_id' => $company['tenant']->id,
            'provider_reference' => $payment->provider_reference,
        ]);
        $this->assertTrue($result['applied']);
        $this->assertSame(CloudSubscriptionPayment::PAID, $payment->fresh()->status);
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $subscription->fresh()->status);
    }

    public function test_scheduler_expires_a_trial_once_and_internal_company_is_not_billed()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $company = $this->customer('bill-due', '237600111115');
        $subscription = $this->trial($company['tenant'], 'MESSAGING_MONTHLY');
        $subscription->trial_ends_at = Carbon::parse('2026-10-04 12:00:00');
        $subscription->save();
        $this->assertSame(1, app(CloudSubscriptionService::class)->processDue(Carbon::parse('2026-10-05 12:00:00')));
        $this->assertSame(0, app(CloudSubscriptionService::class)->processDue(Carbon::parse('2026-10-05 12:00:00')));
        $this->assertSame(CloudSubscriptionStatus::EXPIRED, $subscription->fresh()->status);

        $internal = CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => CloudTenantStatus::ACTIVE,
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);
        $thrown = false;
        try {
            app(CloudBillingCheckout::class)->open($internal, [$subscription], 'MOMO');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
    }

    public function test_production_campay_webhook_stays_closed_when_sandbox_is_off()
    {
        config(['cloud.billing_sandbox' => false]);
        $this->post('/cloud/billing/webhook/campay', ['status' => 'SUCCESSFUL'])->assertStatus(501);
        $this->post('/cloud/billing/webhook/stripe', ['type' => 'checkout.session.completed'])->assertStatus(501);
    }

    protected function trial(CloudTenant $tenant, $planCode)
    {
        $plan = CloudPlan::where('code', $planCode)->first();

        return app(CloudSubscriptionService::class)->startTrial($tenant, $plan);
    }

    protected function campayPayment(CloudTenant $tenant, array $subscriptions)
    {
        $payment = app(CloudBillingCheckout::class)->open($tenant, $subscriptions, 'MOMO');
        $payment->provider_reference = 'campay-ref-'.$payment->id;
        $payment->save();

        return $payment->fresh();
    }

    protected function campayTransport(CloudSubscriptionPayment $payment, $status, $amount, $currency)
    {
        config(['services.campay.transport' => function () use ($status, $amount, $currency) {
            return [
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
            ];
        }]);
    }

    protected function campayBody(CloudSubscriptionPayment $payment, $suffix = 'amount')
    {
        return [
            'external_reference' => $payment->internal_reference,
            'reference' => $payment->provider_reference,
            'event' => $suffix,
        ];
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
            'provider_reference' => (string) $payment->provider_reference,
        ];
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
            'email' => 'platform-'.uniqid().'@beyond.test',
            'password' => Hash::make('secret-pass'),
            'phone' => '237600000001',
            'role_id' => 1,
            'is_active' => 1,
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
