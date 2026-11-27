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
        $catalog->assertOk()->assertSee('Individual')->assertSee('Company')->assertSee('Phone number')->assertDontSee('Confirm password')->assertSee('Quotations')->assertSee('Digital Invitations')->assertDontSee('WhatsApp Hub');
        $catalog->assertSee('Whats MAI')->assertSee('branding/mbole-ai.png')->assertSee('https://mai.beyondtechworld.com');
        $this->get('http://beyondtechworld.com/whatsapp-mai')->assertRedirect('https://mai.beyondtechworld.com');
        $this->get('http://mai.beyondtechworld.com/')->assertOk()->assertSee('Start free trial')->assertSee('Auto Translate')->assertDontSee('Create an account');
        $this->get('http://mai.beyondtechworld.com/mai')->assertOk()->assertSee('Start free trial')->assertDontSee('Create an account');
        $this->get('/whatsapp-mai')->assertOk()->assertSee('Whats MAI')->assertSee('Auto Translate')->assertSee('Transcribe')->assertSee('AI Replies')->assertSee('Auto Summaries')->assertSee('Scheduled Posts')->assertSee('Group Messaging')->assertSee('Start free trial')->assertSee('Install on Android')->assertSee('Subscribe')->assertSee('3-day trial')->assertSee('MTN MoMo')->assertSee('1,500')->assertDontSee('That code does not link WhatsApp.')->assertDontSee('Already registered?')->assertDontSee('Signature Required')->assertDontSee('Digital Invitations');
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

    public function test_a_client_signs_up_and_returns_with_the_phone_code_only()
    {
        $this->get('/whatsapp-mai/open')->assertOk()->assertSee('Sign in to Whats MAI');
        $page = $this->get('/whatsapp-mai');
        $page->assertOk()->assertDontSee('Confirm password');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => '670000301',
            'dial' => '237',
            'account_kind' => 'personal',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '670000301',
            'dial' => '237',
            'code' => $sent->json('testing_code'),
        ])->assertOk();
        $this->post('/cloud/register', [
            'first_name' => 'Amina',
            'last_name' => 'Ndi',
            'phone' => '+237670000301',
            'company_name' => 'Amina Ndi',
            'account_kind' => 'personal',
            'modules' => ['MESSAGING'],
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud');
        $this->assertNotNull(\App\User::where('phone', '237670000301')->first());
        auth()->logout();
        $return = $this->postJson('/cloud/register/otp', [
            'phone' => '670000301',
            'dial' => '237',
            'account_kind' => 'personal',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '670000301',
            'dial' => '237',
            'code' => $return->json('testing_code'),
            'mai' => '1',
        ])->assertOk();
        $this->post('/cloud/register', [
            'first_name' => 'Amina',
            'last_name' => 'Ndi',
            'phone' => '+237670000301',
            'company_name' => 'Amina Ndi',
            'account_kind' => 'personal',
            'modules' => ['MESSAGING'],
            'mai' => '1',
            'onboard_token' => $match[1],
        ])->assertRedirect('/whatsapp-mai/plan');
        auth()->logout();
        $this->assertGuest();
        $login = $this->postJson('/cloud/login/otp', [
            'phone' => '670000301',
            'dial' => '237',
        ]);
        $login->assertOk();
        $this->postJson('/cloud/login/otp/verify', [
            'phone' => '670000301',
            'dial' => '237',
            'code' => $login->json('testing_code'),
            'next' => 'messaging',
        ])->assertOk()->assertJson(['verified' => true]);
        $this->assertAuthenticated();
    }

    public function test_a_signed_in_phone_gets_its_own_whatsapp_session()
    {
        $seen = [];
        config(['cloud.whatsapp.link_transport' => function ($method, $path, $body) use (&$seen) {
            $seen[] = [$method, $path, $body];

            return [
                'status' => 'AWAITING_QR',
                'phone' => null,
                'qr' => 'data:image/png;base64,abc',
                'pairing_code' => 'ABCD1234',
            ];
        }]);
        $user = \App\User::create([
            'name' => 'Mai Person',
            'username' => 'mai250',
            'email' => 'mai250@clients.mbole.invalid',
            'password' => bcrypt('secret'),
            'phone' => '250794006160',
            'role_id' => 5,
            'is_active' => 1,
        ]);
        $this->actingAs($user);
        $this->get('/whatsapp-mai/link')
            ->assertOk()
            ->assertSee('Link WhatsApp')
            ->assertSee('QR code')
            ->assertSee('Link code')
            ->assertSee('250794006160');
        $this->assertSame('POST', $seen[0][0]);
        $this->assertSame('/sessions', $seen[0][1]);
        $this->assertSame('250794006160', $seen[0][2]['phone']);
        $this->post('/whatsapp-mai/link/send', [
            'to' => '237670000000',
            'text' => 'Hello',
        ])->assertRedirect('/whatsapp-mai/link');
        $paths = [];
        foreach ($seen as $row) {
            $paths[] = $row[1];
        }
        $this->assertContains('/sessions/p250794006160?qr=1', $paths);
        $this->assertNotContains('/sessions/p250794006160/send', $paths);
    }

    public function test_whats_mai_stays_on_its_page_for_a_company_member()
    {
        $user = \App\User::create([
            'name' => 'Bridge Owner',
            'username' => 'bridgeowner',
            'email' => 'bridge-owner@demo.test',
            'password' => bcrypt('secret'),
            'phone' => '237670000031',
            'role_id' => 5,
            'is_active' => 1,
        ]);
        $tenantId = DB::table('cloud_tenants')->insertGetId([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Alpha Bridge',
            'slug' => 'alpha-bridge',
            'system_name' => 'Alpha Bridge',
            'type' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cloud_tenant_memberships')->insert([
            'cloud_tenant_id' => $tenantId,
            'user_id' => $user->id,
            'membership_role' => 'OWNER',
            'is_owner' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($user)->get('/whatsapp-mai')
            ->assertOk()
            ->assertSee('Whats MAI')
            ->assertSee('Start free trial')
            ->assertDontSee('Admin setup required');
    }

    public function test_a_three_day_trial_is_free_and_the_phone_link_opens_whats_mai()
    {
        $page = $this->get('/whatsapp-mai');
        $page->assertOk()
            ->assertSee('What should we call you?')
            ->assertSee('Try Whats MAI free for 3 days.')
            ->assertSee('Plan summary')
            ->assertSee('No payment required.')
            ->assertSee('Free')
            ->assertDontSee('The app link was sent')
            ->assertDontSee('Selected modules')
            ->assertDontSee('24 HOUR');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => '670000309',
            'dial' => '237',
            'account_kind' => 'personal',
        ]);
        $verified = $this->postJson('/cloud/register/otp/verify', [
            'phone' => '670000309',
            'dial' => '237',
            'code' => $sent->json('testing_code'),
            'mai' => '1',
        ]);
        $verified->assertOk();
        $this->assertArrayNotHasKey('app_url', $verified->json());
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '670000309',
            'dial' => '237',
            'code' => '000000',
            'mai' => '1',
        ])->assertOk();
        $this->postJson('/cloud/register/otp', [
            'phone' => '670000309',
            'dial' => '237',
            'account_kind' => 'personal',
        ])->assertOk();
        $this->post('/cloud/register', [
            'first_name' => 'Amina',
            'last_name' => 'Ndi',
            'phone' => '+237670000309',
            'company_name' => 'Amina Ndi',
            'account_kind' => 'personal',
            'modules' => ['MESSAGING'],
            'mai' => '1',
            'mai_days' => 3,
            'start_mode' => 'trial',
            'onboard_token' => $match[1],
        ])->assertRedirect('/whatsapp-mai/home');
        $user = \App\User::where('phone', '237670000309')->first();
        $this->assertNotNull($user);
        $subscription = \App\Cloud\CloudSubscription::orderBy('id', 'desc')->first();
        $this->assertSame('TRIALING', $subscription->status);
        $this->assertTrue($subscription->trial_ends_at->gt(now()->addDays(2)));
        $this->assertSame(0, \App\Cloud\CloudSubscriptionPayment::count());
        auth()->logout();
        $this->get('/whatsapp-mai/enter/'.$user->id)->assertRedirect('/whatsapp-mai');
        $url = \URL::temporarySignedRoute('whatsapp.mai.enter', now()->addDay(), ['user' => $user->id]);
        $parts = parse_url($url);
        $this->get($parts['path'].'?'.$parts['query'])->assertRedirect('/whatsapp-mai/home');
        $this->get('/whatsapp-mai/home')
            ->assertOk()
            ->assertSee('Whats MAI')
            ->assertSee('Amina Ndi')
            ->assertDontSee('Enter your phone number');
    }
}
