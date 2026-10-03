<?php

namespace Tests\Feature;

use App\Cloud\CloudPlan;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantSetting;
use App\Http\Middleware\EnforceCloudModule;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudSubscribeWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.public_onboarding' => true, 'cloud.payments_live' => false]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('username')->nullable();
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
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_180000_add_cloud_whatsapp_connection_and_tenant_phone.php', '--force' => true]);
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('display_phone')->nullable();
            $table->string('wa_name')->nullable();
            $table->timestamps();
        });
        \App\Services\Cloud\CloudTenantColumns::forget();
    }

    public function test_a_saved_whatsapp_name_is_used_and_a_conflict_asks_for_a_name()
    {
        config(['services.campay.transport' => function ($method, $url) {
            if (strpos($url, 'holder_info') !== false && strpos($url, '237670000201') !== false) {
                return ['full_name' => 'Amina Campay'];
            }

            return null;
        }]);
        DB::table('whatsapp_contacts')->insert([
            'cloud_tenant_id' => 1,
            'normalized_phone' => '237670000201',
            'wa_name' => 'Amina Ndi',
        ]);
        $this->postJson('/cloud/register/identity', [
            'phone' => '237670000201',
            'account_kind' => 'personal',
        ])->assertStatus(422);
        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => '237670000201',
            'account_kind' => 'personal',
        ]);
        $this->postJson('/cloud/register/otp', [
            'phone' => '237670000201',
            'account_kind' => 'personal',
        ])->assertStatus(422);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000201',
            'code' => $sent->json('testing_code'),
        ])->assertOk();
        $found = $this->postJson('/cloud/register/identity', [
            'phone' => '237670000201',
            'account_kind' => 'personal',
        ]);
        $found->assertOk();
        $this->assertSame('Amina Campay', $found->json('name'));
        $this->assertSame('campay', $found->json('source'));
        $this->assertSame('Amina', $found->json('first_name'));
        DB::table('whatsapp_contacts')->insert([
            'cloud_tenant_id' => 4,
            'normalized_phone' => '237670000205',
            'wa_name' => 'Sara Fon',
        ]);
        $wa = $this->postJson('/cloud/register/otp', [
            'phone' => '237670000205',
            'account_kind' => 'personal',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000205',
            'code' => $wa->json('testing_code'),
        ]);
        $saved = $this->postJson('/cloud/register/identity', [
            'phone' => '237670000205',
            'account_kind' => 'personal',
        ]);
        $saved->assertOk();
        $this->assertSame('whatsapp', $saved->json('source'));
        $this->assertSame('Sara Fon', $saved->json('name'));
        DB::table('whatsapp_contacts')->insert([
            ['cloud_tenant_id' => 2, 'normalized_phone' => '237670000202', 'wa_name' => 'One Name'],
            ['cloud_tenant_id' => 3, 'normalized_phone' => '237670000202', 'wa_name' => 'Other Name'],
        ]);
        $code = $this->postJson('/cloud/register/otp', [
            'phone' => '237670000202',
            'account_kind' => 'company',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000202',
            'code' => $code->json('testing_code'),
        ]);
        $blank = $this->postJson('/cloud/register/identity', [
            'phone' => '237670000202',
            'account_kind' => 'company',
        ]);
        $blank->assertOk();
        $this->assertSame('', $blank->json('name'));
        $this->assertSame('', $blank->json('source'));
    }

    public function test_otp_is_required_and_services_can_be_combined()
    {
        $this->get('/subscriptions')->assertRedirect('/cloud/register');
        $catalog = $this->get('/cloud/register');
        $catalog->assertOk()->assertSee('Individual')->assertSee('Company')->assertSee('Phone number')->assertSee('Username')->assertSee('Quotations')->assertSee('Digital Invitations')->assertDontSee('WhatsApp Hub');
        $page = $this->get('/cloud/register?service=QUOTATIONS');
        $page->assertOk()->assertSee('Quotations')->assertDontSee('WhatsApp Hub')->assertDontSee('Digital Invitations')->assertDontSee('Messaging');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $this->post('/cloud/register', [
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'email' => 'skip@demo.test',
            'phone' => '237670000203',
            'username' => 'skip.co',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'Skip Co',
            'modules' => ['QUOTATIONS'],
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud/register');
        $this->assertNull(CloudTenant::where('name', 'Skip Co')->first());

        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => '237670000203',
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'account_kind' => 'company',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000203',
            'code' => '000000',
        ])->assertStatus(422);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000203',
            'code' => $sent->json('testing_code'),
        ])->assertOk();
        $this->post('/cloud/register', [
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'email' => 'bundle@demo.test',
            'phone' => '237670000203',
            'username' => 'bundle.co',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'Bundle Co',
            'account_kind' => 'company',
            'modules' => ['QUOTATIONS', 'DIGITAL_INVITATIONS'],
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud');
        $tenant = CloudTenant::where('name', 'Bundle Co')->first();
        $this->assertSame(2, $tenant->subscriptions()->count());
        $quote = CloudPlan::whereIn('code', ['QUOTATIONS_MONTHLY', 'DIGITAL_INVITATIONS_MONTHLY'])->sum('price');
        $this->assertEquals(10000, (float) $quote);

        $personal = $this->postJson('/cloud/register/otp', [
            'phone' => '237670000204',
            'first_name' => 'Paul',
            'last_name' => 'Biya',
            'account_kind' => 'personal',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670000204',
            'code' => $personal->json('testing_code'),
        ]);
        $again = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $again->getContent(), $token);
        $this->post('/cloud/register', [
            'first_name' => 'Paul',
            'last_name' => 'Biya',
            'email' => 'paul@demo.test',
            'phone' => '237670000204',
            'username' => 'paul.biya',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'account_kind' => 'personal',
            'modules' => ['MESSAGING'],
            'onboard_token' => $token[1],
        ])->assertRedirect('/cloud');
        $person = CloudTenant::where('email', 'paul@demo.test')->first();
        $this->assertSame('Paul Biya', $person->name);
        $this->assertSame('paul.biya', \App\User::where('email', 'paul@demo.test')->value('username'));
        $this->assertSame('personal', CloudTenantSetting::where('cloud_tenant_id', $person->id)->where('key', 'account_kind')->value('value'));

        app(CloudTenantContext::class)->set($tenant);
        $request = Request::create('/online-invitation/invitations', 'GET');
        $response = app(EnforceCloudModule::class)->handle($request, function () {
            return response('ok', 200);
        });
        $this->assertSame(200, $response->getStatusCode());
        $blocked = Request::create('/sales', 'GET');
        $denied = app(EnforceCloudModule::class)->handle($blocked, function () {
            return response('ok', 200);
        });
        $this->assertSame(403, $denied->getStatusCode());
    }
}
