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
use App\Services\Assistant\BeyondAssistantSystemPromptBuilder;
use App\Services\BeyondWasenderService;
use App\Services\Cloud\CloudSubscriptionService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudWhatsAppConnectService;
use App\Services\Cloud\CloudWhatsAppConnectionResolver;
use App\User;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudWhatsAppConnectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.whatsapp_self_connect' => false, 'cloud.public_onboarding' => true]);
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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('display_phone')->nullable();
            $table->string('wa_name')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('mode')->nullable();
            $table->string('status')->nullable();
            $table->string('channel')->nullable();
            $table->timestamps();
        });
        \App\Services\Cloud\CloudTenantColumns::forget();
    }

    protected function tearDown(): void
    {
        app(CloudTenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_self_connect_stays_hidden_and_a_customer_cannot_use_the_internal_session()
    {
        $company = $this->customer('wa-hidden', '237600222001');
        $this->trial($company['tenant']);
        $internal = $this->internalConnection();
        $this->actingAs($company['user'])->get('/cloud/messaging')
            ->assertOk()
            ->assertSee('Admin setup required')
            ->assertDontSee('Connect WhatsApp');
        app(CloudTenantContext::class)->set($company['tenant']);
        $sent = app(BeyondWasenderService::class)->sendTextRaw('237600222001', 'hello');
        $this->assertFalse($sent['success']);
        $this->assertSame('WhatsApp is not connected for this company.', $sent['error']);
        $this->assertSame('ACTIVE', $internal->fresh()->status);
        $this->assertSame(1, CloudWhatsAppConnection::count());
    }

    public function test_owner_qr_is_isolated_and_does_not_connect_until_the_provider_says_so()
    {
        config(['cloud.whatsapp_self_connect' => true]);
        $owner = $this->customer('wa-owner', '237600222002');
        $this->trial($owner['tenant']);
        $this->allowPaidConnection($owner['tenant']);
        $other = $this->customer('wa-other', '237600222003');
        $internal = $this->internalConnection();
        $this->actingAs($owner['user'])->post('/cloud/messaging/whatsapp/connect', [
            'cloud_tenant_id' => $other['tenant']->id,
        ])->assertRedirect('/cloud/messaging');
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $owner['tenant']->id)->first();
        $this->assertNotNull($connection);
        $this->assertSame('AWAITING_QR', $connection->status);
        $this->assertSame('stub', $connection->provider);
        $this->assertNotSame($internal->id, $connection->id);
        $again = $this->actingAs($owner['user'])->post('/cloud/messaging/whatsapp/connect');
        $again->assertRedirect('/cloud/messaging');
        $this->assertSame(1, CloudWhatsAppConnection::where('cloud_tenant_id', $owner['tenant']->id)->count());
        $qr = $this->actingAs($owner['user'])->get('/cloud/messaging/whatsapp/'.$connection->id.'/qr');
        $qr->assertOk();
        $this->assertSame('stub-qr-'.$connection->id, $qr->getContent());
        $qr->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($other['user'])->get('/cloud/messaging/whatsapp/'.$connection->id.'/qr')->assertStatus(404);
        $this->actingAs($owner['user'])->post('/cloud/messaging/whatsapp/'.$connection->id.'/refresh');
        $this->assertSame('AWAITING_QR', $connection->fresh()->status);
        config(['services.whatsapp.stub_status' => 'CONNECTED']);
        $this->actingAs($owner['user'])->post('/cloud/messaging/whatsapp/'.$connection->id.'/refresh');
        $this->assertSame('CONNECTED', $connection->fresh()->status);
        app(CloudTenantContext::class)->set($owner['tenant']);
        $sent = app(BeyondWasenderService::class)->sendTextRaw('237600222009', 'paid send');
        $this->assertSame('WhatsApp is not connected for this company.', $sent['error']);
        $this->assertSame('beyond-session', $internal->fresh()->provider_connection_id);
    }

    public function test_staff_and_expired_messaging_cannot_start_a_session()
    {
        config(['cloud.whatsapp_self_connect' => true]);
        $company = $this->customer('wa-staff', '237600222004');
        $subscription = $this->trial($company['tenant']);
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff-wa@demo.test',
            'password' => Hash::make('portal-secret'),
            'phone' => '237600222005',
            'role_id' => 5,
            'is_active' => 1,
        ]);
        CloudTenantMembership::create([
            'cloud_tenant_id' => $company['tenant']->id,
            'user_id' => $staff->id,
            'membership_role' => CloudMembershipRole::STAFF,
            'is_owner' => false,
            'status' => CloudMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        $this->actingAs($staff)->post('/cloud/messaging/whatsapp/connect')->assertRedirect('/cloud/messaging');
        $this->assertSame(0, CloudWhatsAppConnection::where('cloud_tenant_id', $company['tenant']->id)->count());
        $subscription->status = 'EXPIRED';
        $subscription->trial_ends_at = now()->subMinute();
        $subscription->save();
        $this->actingAs($company['user'])->post('/cloud/messaging/whatsapp/connect')->assertRedirect('/cloud/messaging');
        $this->assertSame(0, CloudWhatsAppConnection::where('cloud_tenant_id', $company['tenant']->id)->count());
    }

    public function test_contacts_conversations_and_mai_stay_inside_the_company()
    {
        $a = $this->customer('wa-a', '237600222006');
        $b = $this->customer('wa-b', '237600222007');
        $context = app(CloudTenantContext::class);
        $context->set($a['tenant']);
        $contactA = WhatsAppContact::create(['normalized_phone' => '237699000111', 'display_phone' => '237699000111']);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contactA->id,
            'mode' => WhatsAppConversation::MODE_AI,
            'status' => WhatsAppConversation::STATUS_OPEN,
            'channel' => 'whatsapp',
        ]);
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();
        $context->set($b['tenant']);
        WhatsAppContact::create(['normalized_phone' => '237699000111', 'display_phone' => '237699000111']);
        $this->assertSame(1, WhatsAppContact::count());
        $this->assertSame(0, WhatsAppConversation::count());
        $context->set($a['tenant']);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, WhatsAppConversation::first()->mode);
        config(['services.whatsapp.company_name' => 'Prompt Fixture']);
        $prompt = app(BeyondAssistantSystemPromptBuilder::class)->build();
        $this->assertStringContainsString($a['tenant']->name, $prompt);
        $this->assertStringNotContainsString($b['tenant']->name, $prompt);
        $this->internalConnection();
        CloudWhatsAppConnection::create([
            'cloud_tenant_id' => $b['tenant']->id,
            'provider' => 'stub',
            'provider_connection_id' => 'stub-b',
            'status' => 'CONNECTED',
            'credentials_reference' => 'stub',
        ]);
        $resolved = app(CloudWhatsAppConnectionResolver::class)->tenantForPayload([
            'sessionId' => 'stub-b',
            'data' => ['from' => '237699000111'],
        ]);
        $this->assertSame($b['tenant']->id, $resolved->id);
        $this->assertNull(app(CloudWhatsAppConnectionResolver::class)->tenantForPayload([
            'data' => ['from' => '237699000111', 'cloud_tenant_id' => $a['tenant']->id],
        ]));
    }

    public function test_disconnect_keeps_history_and_a_provider_error_does_not_switch_company()
    {
        config(['cloud.whatsapp_self_connect' => true, 'services.whatsapp.stub_status' => 'CONNECTED']);
        $company = $this->customer('wa-off', '237600222008');
        $this->trial($company['tenant']);
        $this->allowPaidConnection($company['tenant']);
        $internal = $this->internalConnection();
        $context = app(CloudTenantContext::class);
        $context->set($company['tenant']);
        $contact = WhatsAppContact::create(['normalized_phone' => '237699000222', 'display_phone' => '237699000222']);
        WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'mode' => 'AI',
            'status' => 'OPEN',
            'channel' => 'whatsapp',
        ]);
        $connection = app(CloudWhatsAppConnectService::class)->begin($company['tenant'], $company['user']);
        config(['services.whatsapp.stub_status' => 'ERROR']);
        app(CloudWhatsAppConnectService::class)->refresh($company['tenant'], $connection, $company['user']);
        $this->assertSame('ERROR', $connection->fresh()->status);
        $this->assertSame('ACTIVE', $internal->fresh()->status);
        config(['services.whatsapp.stub_status' => 'CONNECTED']);
        app(CloudWhatsAppConnectService::class)->refresh($company['tenant'], $connection->fresh(), $company['user']);
        $this->actingAs($company['user'])->post('/cloud/messaging/whatsapp/'.$connection->id.'/disconnect', [
            'confirm' => 'DISCONNECT',
        ])->assertRedirect('/cloud/messaging');
        $this->assertSame('DISCONNECTED', $connection->fresh()->status);
        app(CloudTenantContext::class)->set($company['tenant']);
        $this->assertSame(1, WhatsAppConversation::count());
        $this->assertSame(1, DB::table('cloud_whatsapp_connection_events')->where('event', 'disconnected')->count());
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

    protected function trial(CloudTenant $tenant)
    {
        $plan = CloudPlan::where('code', 'MESSAGING_MONTHLY')->first();

        return app(CloudSubscriptionService::class)->startTrial($tenant, $plan);
    }

    protected function allowPaidConnection($tenant = null)
    {
        config([
            'cloud.whatsapp.provisioning_enabled' => true,
            'cloud.whatsapp.provisioning_policy' => 'INCLUDED_IN_PLAN',
            'cloud.whatsapp.session_limit' => 10,
            'cloud.whatsapp.reserved_sessions' => 1,
            'cloud.whatsapp.trial_provisioning_allowed' => false,
        ]);
        if ($tenant) {
            CloudSubscription::where('cloud_tenant_id', $tenant->id)->update([
                'status' => CloudSubscriptionStatus::ACTIVE,
                'current_period_end' => now()->addMonth(),
            ]);
        }
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
}
