<?php

namespace Tests\Feature;

use App\Cloud\CloudTenant;
use App\Services\Messaging\InfobipSmsProvider;
use App\Services\Messaging\MessagingHub;
use App\Services\Messaging\SmsCreditLedger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InfobipSmsProviderTest extends TestCase
{
    protected $history = [];

    protected $responses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cloud.public_onboarding' => true,
            'messaging.sms.driver' => 'infobip',
            'messaging.sms.validation_segment_ceiling' => 20,
            'messaging.sms.infobip.base_url' => 'https://example.test',
            'messaging.sms.infobip.api_key' => 'test-key',
            'messaging.sms.infobip.sender' => '',
            'messaging.sms.infobip.delivery_url' => '',
        ]);
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
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_220000_create_messaging_hub_sms.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_02_230000_add_sms_provider_reconciliation.php', '--force' => true]);
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_adapter_stores_provider_id_without_marking_delivered_and_duplicate_does_not_resend()
    {
        $this->bindClient([
            json_encode([
                'messages' => [[
                    'messageId' => 'ib-100',
                    'messageCount' => 2,
                    'status' => ['groupName' => 'PENDING', 'name' => 'PENDING_ACCEPTED'],
                ]],
            ]),
        ]);
        $tenant = $this->company();
        $this->connect($tenant, 'BEYOND');
        app(SmsCreditLedger::class)->grantTest($tenant->id, 5, null, 'test');
        $hub = app(MessagingHub::class);
        $hub->notify($tenant, $this->notice('once', 'Hello'));
        $hub->notify($tenant, $this->notice('once', 'Hello'));

        $this->assertCount(1, $this->history);
        $row = DB::table('cloud_sms_messages')->first();
        $this->assertSame('SENT', $row->status);
        $this->assertSame('ib-100', $row->provider_message_id);
        $this->assertSame(1, (int) $row->segments);
        $this->assertSame(2, (int) $row->provider_units);
        $this->assertNull($row->provider_cost);
        $this->assertSame('ACTIVE', DB::table('cloud_sms_connections')->value('health_status'));
        $request = json_encode($this->history[0]['payload']);
        $this->assertStringNotContainsString('test-key', $request);

        $payload = json_encode([
            'results' => [[
                'messageId' => 'ib-100',
                'smsCount' => 2,
                'price' => ['pricePerMessage' => 0.02, 'currency' => 'EUR'],
                'status' => ['groupName' => 'DELIVERED', 'name' => 'DELIVERED_TO_HANDSET'],
                'error' => ['permanent' => false],
            ]],
        ]);
        $this->call('POST', '/cloud/messaging/sms/webhook/infobip', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
        $this->call('POST', '/cloud/messaging/sms/webhook/infobip', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
        $this->assertSame('DELIVERED', DB::table('cloud_sms_messages')->value('status'));
        $this->assertEquals(0.02, (float) DB::table('cloud_sms_messages')->value('provider_cost'));
        $this->assertSame(1, DB::table('cloud_sms_ledger')->where('kind', 'CONSUMED')->count());

        $unknown = json_encode([
            'results' => [[
                'messageId' => 'ib-100',
                'status' => ['groupName' => 'WEIRD', 'name' => 'WEIRD_STATUS'],
            ]],
        ]);
        $this->call('POST', '/cloud/messaging/sms/webhook/infobip', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $unknown)->assertOk();
        $this->assertSame('DELIVERED', DB::table('cloud_sms_messages')->value('status'));
    }

    public function test_missing_sender_and_ceiling_do_not_call_the_provider()
    {
        $this->bindClient([]);
        $tenant = $this->company();
        $this->connect($tenant, '');
        app(SmsCreditLedger::class)->grantTest($tenant->id, 5, null, 'test');
        app(MessagingHub::class)->notify($tenant, $this->notice('nosender', 'Hello'));
        $this->assertCount(0, $this->history);
        $this->assertSame('sender_not_configured', DB::table('cloud_sms_messages')->value('failure_code'));

        config(['messaging.sms.validation_segment_ceiling' => 0]);
        $this->connect($tenant, 'BEYOND');
        app(MessagingHub::class)->notify($tenant, $this->notice('capped', 'Hello'));
        $this->assertCount(0, $this->history);
    }

    public function test_default_driver_does_not_select_infobip()
    {
        config(['messaging.sms.driver' => 'disabled']);
        $result = app(\App\Services\Messaging\SmsProviderRegistry::class)->resolve('infobip')->send([
            'to' => '+237670000000',
            'from' => 'BEYOND',
            'body' => 'Hello',
            'reference' => 'nope',
        ]);
        $this->assertFalse($result['accepted']);
        $this->assertSame('provider_not_activated', $result['failure_code']);
    }

    protected function bindClient(array $responses)
    {
        $this->history = [];
        $this->responses = $responses;
        $test = $this;
        $this->app->instance(InfobipSmsProvider::class, new InfobipSmsProvider(function ($url, $headers, $payload) use ($test) {
            $test->history[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload];
            if ($test->responses === []) {
                throw new \RuntimeException('unexpected request');
            }

            return array_shift($test->responses);
        }));
    }

    protected function company()
    {
        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
        $sent = $this->postJson('/cloud/register/otp', [
            'phone' => '237670009001',
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'account_kind' => 'company',
        ]);
        $this->postJson('/cloud/register/otp/verify', [
            'phone' => '237670009001',
            'code' => $sent->json('testing_code'),
        ]);
        $this->post('/cloud/register', [
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'email' => 'sms@demo.test',
            'phone' => '237670009001',
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => 'SMS Co',
            'modules' => ['MESSAGING'],
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud');

        return CloudTenant::where('name', 'SMS Co')->first();
    }

    protected function connect($tenant, $sender)
    {
        DB::table('cloud_sms_connections')->insert([
            'cloud_tenant_id' => $tenant->id,
            'provider' => 'infobip',
            'sender_id' => $sender !== '' ? $sender : null,
            'status' => 'ACTIVE',
            'credentials_reference' => 'env:INFOBIP_API_KEY',
            'country' => 'CM',
            'currency' => 'XAF',
            'ownership' => 'PLATFORM',
            'sending_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function notice($id, $body)
    {
        return [
            'phone' => '237699000444',
            'body' => $body,
            'purpose' => 'GENERAL_NOTIFICATION',
            'preference' => 'SMS',
            'correlation_id' => $id,
        ];
    }
}
