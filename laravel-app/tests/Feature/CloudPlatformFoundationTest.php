<?php

namespace Tests\Feature;

use App\Booking;
use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModule;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPaymentMethodCode;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantSetting;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudTrialUnit;
use App\Customer;
use App\Product;
use App\Property\Tenancy;
use App\Quotation;
use App\Sale;
use App\Services\Cloud\CloudSlug;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTrialEligibility;
use App\User;
use App\WhatsApp\WhatsAppContact;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudPlatformFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareExistingTables();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php',
            '--force' => true,
        ]);
    }

    public function test_catalog_prices_and_trial_come_from_the_database()
    {
        $this->assertSame(4, CloudModule::count());
        $this->assertSame(
            ['MESSAGING', 'RENTALS', 'SALES_INVOICES', 'WHATSAPP_HUB'],
            CloudModule::orderBy('code')->pluck('code')->all()
        );

        $whatsapp = CloudPlan::where('code', 'WHATSAPP_HUB_MONTHLY')->first();
        $sales = CloudPlan::where('code', 'SALES_INVOICES_MONTHLY')->first();
        $rentals = CloudPlan::where('code', 'RENTALS_MONTHLY')->first();

        $this->assertNotNull($whatsapp);
        $this->assertSame(CloudModuleCode::WHATSAPP_HUB, $whatsapp->module->code);
        $this->assertEquals(10000, (float) $whatsapp->price);
        $this->assertSame('XAF', $whatsapp->currency);
        $this->assertSame(24, (int) $whatsapp->trial_value);
        $this->assertSame(CloudTrialUnit::HOUR, $whatsapp->trial_unit);

        $this->assertEquals(5000, (float) $sales->price);
        $this->assertEquals(5000, (float) $rentals->price);
        $messaging = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();
        $this->assertNotNull($messaging);
        $this->assertSame(CloudModuleCode::MESSAGING, $messaging->module->code);
        $this->assertEquals(5000, (float) $messaging->price);
        $this->assertSame('XAF', $messaging->currency);
        $this->assertSame(24, (int) $messaging->trial_value);
        $this->assertSame(24, (int) $sales->trial_value);
        $this->assertSame(CloudTrialUnit::HOUR, $rentals->trial_unit);
        $this->assertSame(0, CloudTenant::count());

        $momo = CloudPaymentMethod::where('code', CloudPaymentMethodCode::MOMO)->first();
        $visa = CloudPaymentMethod::where('code', CloudPaymentMethodCode::VISA)->first();
        $this->assertNotNull($momo);
        $this->assertNotNull($visa);
        $this->assertSame('campay', $momo->provider);
        $this->assertSame('stripe', $visa->provider);
        $this->assertTrue($momo->active);
        $this->assertTrue($visa->active);
    }

    public function test_one_phone_can_take_each_module_trial_only_once()
    {
        $gate = new CloudTrialEligibility();
        $whatsapp = CloudModule::where('code', CloudModuleCode::WHATSAPP_HUB)->first();
        $sales = CloudModule::where('code', CloudModuleCode::SALES_INVOICES)->first();

        $gate->claim('+237 677 000 111', $whatsapp);
        $this->assertTrue($gate->alreadyUsed('237677000111', $whatsapp));

        $gate->claim('677000111', $sales);
        $this->assertSame(2, \App\Cloud\CloudTrialClaim::count());

        try {
            $gate->claim('237677000111', $whatsapp);
            $this->fail('The same phone was allowed a second WhatsApp Hub trial.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already used', $e->getMessage());
        }
    }

    public function test_tenant_uuid_and_slug_are_unique()
    {
        $first = CloudTenant::create([
            'name' => 'Alpha Bridge Technologies',
            'slug' => CloudSlug::fromName('Alpha Bridge Technologies'),
            'type' => CloudTenantType::CUSTOMER,
            'status' => CloudTenantStatus::ACTIVE,
        ]);
        $this->assertSame('alpha-bridge-technologies', $first->slug);
        $this->assertNotSame('', (string) $first->uuid);
        $this->assertSame('XAF', $first->currency);
        $this->assertSame('Africa/Douala', $first->timezone);

        $second = CloudTenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
        ]);
        $this->assertNotSame($first->uuid, $second->uuid);

        $this->expectException(QueryException::class);
        CloudTenant::create([
            'name' => 'Copy',
            'slug' => 'alpha-bridge-technologies',
        ]);
    }

    public function test_one_user_can_belong_to_two_companies_and_removal_keeps_the_user()
    {
        $user = User::create([
            'name' => 'Ada Owner',
            'email' => 'ada@example.com',
            'password' => bcrypt('secret'),
        ]);
        $alpha = $this->company('Alpha', 'alpha');
        $beta = $this->company('Beta', 'beta');

        $owner = CloudTenantMembership::create([
            'cloud_tenant_id' => $alpha->id,
            'user_id' => $user->id,
            'membership_role' => CloudMembershipRole::OWNER,
            'status' => CloudMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        CloudTenantMembership::create([
            'cloud_tenant_id' => $beta->id,
            'user_id' => $user->id,
            'membership_role' => CloudMembershipRole::ADMIN,
            'status' => CloudMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);

        $this->assertTrue($owner->fresh()->is_owner);
        $this->assertSame(2, CloudTenantMembership::where('user_id', $user->id)->count());
        $this->assertSame($alpha->id, $owner->cloudTenant->id);
        $this->assertSame($user->id, $owner->user->id);

        $owner->remove();
        $this->assertSame(CloudMembershipStatus::REMOVED, $owner->fresh()->status);
        $this->assertNotNull(User::find($user->id));

        $betaMembership = CloudTenantMembership::where('cloud_tenant_id', $beta->id)->first();
        $betaMembership->delete();
        $this->assertNotNull(User::find($user->id));
        $this->assertSame(0, CloudTenantMembership::where('cloud_tenant_id', $beta->id)->count());
    }

    public function test_settings_are_unique_per_company_and_subscriptions_keep_their_own_status()
    {
        $alpha = $this->company('Alpha', 'alpha-settings');
        $beta = $this->company('Beta', 'beta-settings');
        CloudTenantSetting::create([
            'cloud_tenant_id' => $alpha->id,
            'key' => 'system_name',
            'value' => 'Alpha Business Manager',
        ]);
        CloudTenantSetting::create([
            'cloud_tenant_id' => $beta->id,
            'key' => 'system_name',
            'value' => 'Beta Desk',
        ]);
        $this->assertSame(2, CloudTenantSetting::where('key', 'system_name')->count());

        $plan = CloudPlan::where('code', 'WHATSAPP_HUB_MONTHLY')->first();
        $trial = CloudSubscription::create([
            'cloud_tenant_id' => $alpha->id,
            'cloud_plan_id' => $plan->id,
            'status' => CloudSubscriptionStatus::TRIALING,
        ]);
        $active = CloudSubscription::create([
            'cloud_tenant_id' => $beta->id,
            'cloud_plan_id' => $plan->id,
            'status' => CloudSubscriptionStatus::ACTIVE,
        ]);
        $this->assertSame(CloudSubscriptionStatus::TRIALING, $trial->fresh()->status);
        $this->assertSame(CloudSubscriptionStatus::ACTIVE, $active->fresh()->status);
        $this->assertSame($plan->id, $trial->plan->id);
        $this->assertSame($alpha->id, $trial->cloudTenant->id);
        $this->assertTrue($alpha->status === CloudTenantStatus::ACTIVE || $alpha->status === CloudTenantStatus::PENDING);

        try {
            CloudTenantSetting::create([
                'cloud_tenant_id' => $alpha->id,
                'key' => 'system_name',
                'value' => 'Again',
            ]);
            $this->fail('Duplicate setting key was stored.');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_context_is_explicit_and_cloud_is_not_property_tenancy()
    {
        $this->assertFalse(class_exists('App\\Tenant'));
        $this->assertTrue(class_exists(CloudTenant::class));
        $this->assertTrue(class_exists(Tenancy::class));
        $this->assertNotSame(Tenancy::class, CloudTenant::class);

        foreach (glob(app_path('Cloud/*.php')) as $file) {
            $this->assertStringNotContainsString('use App\\Tenant;', file_get_contents($file));
        }

        $context = $this->app->make(CloudTenantContext::class);
        $this->assertNull($context->get());
        try {
            $context->requireTenant();
            $this->fail('Missing CloudTenant was treated as allowed.');
        } catch (\RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $company = $this->company('Context Co', 'context-co');
        $context->set($company);
        $this->assertSame($company->id, $context->requireTenant()->id);
        $context->clear();
        $this->assertNull($context->id());

        foreach ([Product::class, Sale::class, Customer::class, Quotation::class, Booking::class, WhatsAppContact::class] as $class) {
            $scopes = (new $class)->getGlobalScopes();
            foreach ($scopes as $scope) {
                $this->assertStringNotContainsString('cloud', strtolower(is_object($scope) ? get_class($scope) : (string) $scope));
            }
        }
    }

    public function test_migration_does_not_change_existing_business_tables_and_rolls_back()
    {
        $this->assertSame(['id', 'name'], Schema::getColumnListing('products'));
        $this->assertSame('Speaker', DB::table('products')->where('id', 1)->value('name'));
        $this->assertFalse(Schema::hasColumn('products', 'cloud_tenant_id'));
        $this->assertFalse(Schema::hasColumn('whatsapp_contacts', 'cloud_tenant_id'));
        $this->assertSame('237600000001', DB::table('whatsapp_contacts')->where('id', 1)->value('normalized_phone'));

        try {
            DB::table('whatsapp_contacts')->insert(['normalized_phone' => '237600000001']);
            $this->fail('WhatsApp phone uniqueness was removed.');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->artisan('migrate:rollback', [
            '--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php',
            '--force' => true,
        ]);
        $this->assertFalse(Schema::hasTable('cloud_tenants'));
        $this->assertFalse(Schema::hasTable('cloud_modules'));
        $this->assertFalse(Schema::hasTable('cloud_plans'));
        $this->assertFalse(Schema::hasTable('cloud_subscriptions'));
        $this->assertFalse(Schema::hasTable('cloud_tenant_memberships'));
        $this->assertFalse(Schema::hasTable('cloud_tenant_settings'));
        $this->assertFalse(Schema::hasTable('cloud_payment_methods'));
        $this->assertFalse(Schema::hasTable('cloud_trial_claims'));
        $this->assertSame('Speaker', DB::table('products')->where('id', 1)->value('name'));
        $this->assertSame(1, DB::table('whatsapp_contacts')->count());
    }

    protected function company($name, $slug)
    {
        return CloudTenant::create([
            'name' => $name,
            'slug' => $slug,
            'type' => CloudTenantType::CUSTOMER,
            'status' => CloudTenantStatus::ACTIVE,
        ]);
    }

    protected function prepareExistingTables()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('products')->insert(['id' => 1, 'name' => 'Speaker']);
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 32)->unique();
        });
        DB::table('whatsapp_contacts')->insert([
            'id' => 1,
            'normalized_phone' => '237600000001',
        ]);
    }
}
