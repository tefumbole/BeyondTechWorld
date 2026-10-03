<?php

namespace Tests\Feature;

use App\Cloud\CloudTenant;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Messaging\FakeSmsProvider;
use App\Services\Messaging\MessagingHub;
use App\Services\Messaging\SmsCreditLedger;
use App\Services\Messaging\SmsProviderRegistry;
use App\Services\Messaging\SmsSegmentEstimator;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagingHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cloud.public_onboarding' => true,
            'messaging.sms.driver' => 'fake',
            'messaging.sms.webhook_secret' => 'test-secret',
            'messaging.sms.allow_test_credits' => true,
        ]);
        $this->app->singleton(FakeSmsProvider::class, function () {
            return new FakeSmsProvider();
        });
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
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_segments_distinguish_gsm_and_unicode()
    {
        $estimator = new SmsSegmentEstimator();
        $this->assertSame(['encoding' => 'GSM', 'characters' => 5, 'segments' => 1], $estimator->estimate('Hello'));
        $long = $estimator->estimate(str_repeat('A', 161));
        $this->assertSame('GSM', $long['encoding']);
        $this->assertSame(2, $long['segments']);
        $shortUnicode = $estimator->estimate(str_repeat('你', 70));
        $this->assertSame('UCS2', $shortUnicode['encoding']);
        $this->assertSame(1, $shortUnicode['segments']);
        $longUnicode = $estimator->estimate(str_repeat('你', 71));
        $this->assertSame(2, $longUnicode['segments']);
        $this->assertNotSame(strlen(str_repeat('你', 71)), $longUnicode['characters']);
    }

    public function test_credit_reservation_stops_overspend_and_releases_a_refused_send()
    {
        $tenant = $this->company('Credit Co', 'credit@demo.test', '237670001001', ['MESSAGING']);
        $this->connect($tenant, 'ALPHA');
        app(SmsCreditLedger::class)->grantTest($tenant->id, 10, null, 'test');
        $hub = app(MessagingHub::class);
        $hub->notify($tenant, $this->sms($tenant, 'one', str_repeat('A', 1072)));
        $this->assertSame(1, count(app(FakeSmsProvider::class)->sent));
        $this->assertSame(2, app(SmsCreditLedger::class)->available($tenant->id));
        $hub->notify($tenant, $this->sms($tenant, 'two', str_repeat('A', 1072)));
        $this->assertSame(1, count(app(FakeSmsProvider::class)->sent));
        $this->assertSame('insufficient_credit', DB::table('cloud_sms_messages')->orderByDesc('id')->value('failure_code'));

        app(FakeSmsProvider::class)->mode = 'temporary';
        $hub->notify($tenant, $this->sms($tenant, 'three', 'Hi'));
        $this->assertSame(2, app(SmsCreditLedger::class)->available($tenant->id));
        $this->assertSame(1, DB::table('cloud_sms_ledger')->where('kind', 'RELEASE')->count());
    }

    public function test_companies_cannot_use_each_others_sms_and_the_same_phone_stays_separate()
    {
        $alpha = $this->company('Alpha Msg', 'alpha-msg@demo.test', '237670001011', ['MESSAGING']);
        $beta = $this->company('Beta Msg', 'beta-msg@demo.test', '237670001012', ['MESSAGING']);
        $this->connect($alpha, 'ALPHA');
        $this->connect($beta, 'BETA');
        app(SmsCreditLedger::class)->grantTest($alpha->id, 5, null, 'test');
        app(SmsCreditLedger::class)->grantTest($beta->id, 5, null, 'test');
        $templateId = DB::table('cloud_messaging_templates')->insertGetId([
            'cloud_tenant_id' => $alpha->id,
            'name' => 'Quote',
            'channel' => 'SMS',
            'purpose' => 'QUOTATION',
            'body' => 'Hello {{company_name}} {{system}}',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hub = app(MessagingHub::class);
        $hub->notify($alpha, [
            'phone' => '237699000111',
            'template_id' => $templateId,
            'variables' => ['company_name' => 'Alpha Msg'],
            'purpose' => 'QUOTATION',
            'preference' => 'SMS',
            'correlation_id' => 'alpha-quote',
        ]);
        $this->assertSame('Hello Alpha Msg ', DB::table('cloud_sms_messages')->where('cloud_tenant_id', $alpha->id)->value('body'));
        try {
            $hub->notify($beta, [
                'phone' => '237699000111',
                'template_id' => $templateId,
                'purpose' => 'QUOTATION',
                'preference' => 'SMS',
                'correlation_id' => 'beta-steal',
            ]);
            $this->fail('Beta used Alpha template');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not available', $e->getMessage());
        }
        $hub->notify($beta, $this->sms($beta, 'beta-own', 'Beta note'));
        $this->assertSame(1, $hub->usage($alpha->id)->count());
        $this->assertSame(1, $hub->usage($beta->id)->count());
        $this->assertSame('ALPHA', DB::table('cloud_sms_messages')->where('cloud_tenant_id', $alpha->id)->value('sender'));
        $this->assertSame(4, app(SmsCreditLedger::class)->available($alpha->id));
        $this->assertSame(4, app(SmsCreditLedger::class)->available($beta->id));
    }

    public function test_fallback_idempotency_delivery_and_entitlement()
    {
        $tenant = $this->company('Route Co', 'route@demo.test', '237670001021', ['MESSAGING']);
        $this->connect($tenant, 'ROUTE');
        app(SmsCreditLedger::class)->grantTest($tenant->id, 5, null, 'test');
        $hub = app(MessagingHub::class);
        $hub->notify($tenant, $this->auto('fall-ok', 'Ready'), [
            'success' => false,
            'failure_class' => 'permanent_recipient',
        ]);
        $this->assertSame(1, DB::table('cloud_sms_messages')->count());
        $hub->notify($tenant, $this->auto('fall-ok', 'Ready'), [
            'success' => false,
            'failure_class' => 'permanent_recipient',
        ]);
        $this->assertSame(1, DB::table('cloud_sms_messages')->count());
        $this->assertSame(1, DB::table('cloud_messaging_attempts')->where('channel', 'SMS')->count());

        $hub->notify($tenant, $this->auto('fall-temp', 'Wait'), [
            'success' => false,
            'failure_class' => 'temporary',
        ]);
        $this->assertSame(1, DB::table('cloud_sms_messages')->count());

        $id = DB::table('cloud_sms_messages')->value('provider_message_id');
        $this->assertSame('SENT', DB::table('cloud_sms_messages')->value('status'));
        $this->post('/cloud/messaging/sms/webhook/fake', [
            'provider_message_id' => $id,
            'status' => 'DELIVERED',
        ], ['X-Messaging-Signature' => 'test-secret'])->assertOk();
        $this->assertSame('DELIVERED', DB::table('cloud_sms_messages')->value('status'));
        $this->post('/cloud/messaging/sms/webhook/fake', [
            'provider_message_id' => $id,
            'status' => 'DELIVERED',
        ], ['X-Messaging-Signature' => 'test-secret'])->assertOk();
        $this->assertSame(1, DB::table('cloud_sms_messages')->where('status', 'DELIVERED')->count());

        $hub->notify($tenant, $this->sms($tenant, 'later-fail', 'Ping'));
        $second = DB::table('cloud_sms_messages')->orderByDesc('id')->value('provider_message_id');
        $hub->applyDelivery('fake', $second, 'FAILED');
        $this->assertSame('FAILED', DB::table('cloud_sms_messages')->where('provider_message_id', $second)->value('status'));
        $this->assertSame(0, DB::table('cloud_sms_ledger')->where('kind', 'REFUND')->count());

        $this->post('/cloud/messaging/sms/webhook/fake', [
            'provider_message_id' => $id,
            'status' => 'DELIVERED',
        ])->assertStatus(401);

        $plain = $this->company('Plain Co', 'plain@demo.test', '237670001022', ['SALES_INVOICES']);
        try {
            $hub->notify($plain, $this->sms($plain, 'nope', 'Hi'));
            $this->fail('SMS sent without Messaging');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not active', $e->getMessage());
        }
        app(CloudTenantContext::class)->set($plain);
        $denied = app(AssistantToolExecutor::class)->execute('send_notification', ['count' => 10000], []);
        $this->assertSame('module_not_entitled', $denied['error']);

        $queued = $this->company('Queue Co', 'queue@demo.test', '237670001023', ['MESSAGING']);
        $this->connect($queued, 'QUEUE');
        app(SmsCreditLedger::class)->grantTest($queued->id, 3, null, 'test');
        $before = count(app(FakeSmsProvider::class)->sent);
        $hub->notify($queued, array_merge($this->sms($queued, 'deferred', 'Later'), ['defer' => true]));
        Carbon::setTestNow(now()->addHours(25));
        $attemptId = DB::table('cloud_messaging_attempts')->where('cloud_tenant_id', $queued->id)->where('channel', 'SMS')->value('id');
        $hub->executeSmsAttempt($attemptId);
        Carbon::setTestNow();
        $this->assertSame($before, count(app(FakeSmsProvider::class)->sent));
        $this->assertSame('entitlement', DB::table('cloud_sms_messages')->where('cloud_tenant_id', $queued->id)->value('failure_code'));

        config(['messaging.sms.driver' => 'disabled']);
        $orange = app(SmsProviderRegistry::class)->resolve('orange_cm');
        $this->assertFalse($orange->send(['to' => '+237670001021', 'from' => 'X', 'body' => 'Hi', 'reference' => 'n'])['accepted']);
        $this->assertFalse($orange->verifyWebhook([], ''));
        $this->assertSame(
            'Beyond: Your quotation QT-1 is ready. View securely: https://example.test/q/abc?token=1',
            $hub->quotationText('Beyond', 'QT-1', 'https://example.test/q/abc?token=1')
        );
        try {
            $hub->quotationText('Beyond', 'QT-1', 'https://example.test/invoice/123');
            $this->fail('Bare invoice link was accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('secure token', $e->getMessage());
        }

        $this->withoutMockingConsoleOutput();
        $code = \Illuminate\Support\Facades\Artisan::call('cloud:audit-ownership');
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('unowned_total=0', $output);
        $this->assertStringContainsString('invalid_tenant_total=0', $output);
        $this->assertStringContainsString('cross_tenant_links=0', $output);
    }

    protected function company($name, $email, $phone, array $modules)
    {
        $this->post('/cloud/logout');
        $page = $this->get('/cloud/register');
        preg_match('/name="onboard_token" value="([^"]+)"/', $page->getContent(), $match);
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
        $this->post('/cloud/register', [
            'first_name' => 'Ada',
            'last_name' => 'Owner',
            'email' => $email,
            'phone' => $phone,
            'password' => 'portal-secret',
            'password_confirmation' => 'portal-secret',
            'company_name' => $name,
            'modules' => $modules,
            'onboard_token' => $match[1],
        ])->assertRedirect('/cloud');

        return CloudTenant::where('name', $name)->first();
    }

    protected function connect($tenant, $sender)
    {
        DB::table('cloud_sms_connections')->insert([
            'cloud_tenant_id' => $tenant->id,
            'provider' => 'fake',
            'sender_id' => $sender,
            'status' => 'ACTIVE',
            'credentials_reference' => 'config:messaging.sms',
            'country' => 'CM',
            'currency' => 'XAF',
            'ownership' => 'PLATFORM',
            'sending_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function sms($tenant, $correlation, $body)
    {
        return [
            'phone' => '237699000222',
            'body' => $body,
            'purpose' => 'GENERAL_NOTIFICATION',
            'preference' => 'SMS',
            'correlation_id' => $correlation,
        ];
    }

    protected function auto($correlation, $body)
    {
        return [
            'phone' => '237699000333',
            'body' => $body,
            'purpose' => 'GENERAL_NOTIFICATION',
            'preference' => 'AUTO',
            'correlation_id' => $correlation,
        ];
    }
}
