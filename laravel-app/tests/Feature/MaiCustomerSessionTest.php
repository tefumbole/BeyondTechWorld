<?php

namespace Tests\Feature;

use App\Mai\MaiOutbound;
use App\Mai\MaiSession;
use App\Services\Mai\MaiBusinessLine;
use App\Services\Mai\MaiCustomerAuth;
use App\Services\Mai\MaiInboxStore;
use App\Services\Mai\MaiProviderRouter;
use App\Services\Mai\MaiSessionService;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiCustomerSessionTest extends TestCase
{
    protected $linkCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.mai_customer.enabled' => true]);
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\LogActivity::class,
            \App\Http\Middleware\ResolveCloudTenant::class,
            \App\Http\Middleware\EnforceCloudModule::class,
        ]);
        $this->app->bind(\App\Services\BeyondWasenderService::class, function () {
            return new class {
                public function __call($name, $arguments)
                {
                    throw new \RuntimeException('wasender '.$name);
                }
            };
        });
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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 32)->unique();
            $table->string('display_phone', 48)->nullable();
            $table->string('wa_name', 191)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('mode', 16)->default('HUMAN');
            $table->string('status', 16)->default('OPEN');
            $table->string('channel', 32)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('first_message')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_incoming_at')->nullable();
            $table->timestamp('last_outgoing_at')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('direction', 16);
            $table->string('type', 24)->default('TEXT');
            $table->string('provider_message_id', 128)->nullable()->unique();
            $table->text('body')->nullable();
            $table->text('media_json')->nullable();
            $table->string('status', 16)->default('QUEUED');
            $table->string('sender_type', 16)->default('SYSTEM');
            $table->unsignedInteger('sender_user_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_26_233000_create_mai_customer_sessions.php', '--force' => true]);
        $this->script(function ($method, $path) {
            $this->linkCalls[] = $method.' '.$path;
            if (substr($path, -5) === '/send') {
                return ['success' => true, 'id' => 'MSG1'];
            }

            return ['status' => 'AWAITING_QR', 'qr' => 'data:image/png;base64,abc'];
        });
    }

    public function test_customer_signup_does_not_use_wasender_and_the_business_route_stays()
    {
        config(['cloud.mai_customer.enabled' => false]);
        $this->post('/mai/register', $this->person('ada@example.test'))->assertStatus(404);
        $action = app('router')->getRoutes()->getByName('whatsapp.webhook')->getActionName();
        $this->assertStringContainsString('WaSenderWebhookController', $action);

        config(['cloud.mai_customer.enabled' => true]);
        $this->post('/mai/register', $this->person('ada@example.test'))->assertRedirect('/mai');
        $this->get('/mai/verify/'.MaiCustomerAuth::testingToken())->assertRedirect('/mai');
        $this->get('/mai')->assertOk()->assertSee('Link this number');
        $this->assertCount(0, $this->linkCalls);
    }

    public function test_new_customer_links_sends_and_receives_without_wasender()
    {
        $key = $this->account('ada@example.test');
        $created = $this->json('POST', '/api/mai/v1/sessions', ['phone' => '237670000111'], $this->auth($key));
        $created->assertOk()->assertJsonPath('session.provider', 'mai');
        $id = $created->json('session.id');
        $this->assertNotEmpty($id);
        $this->assertFalse(MaiBusinessLine::is('237670000111'));

        $session = MaiSession::where('public_id', $id)->first();
        $session->status = 'CONNECTED';
        $session->phone_number = '237670000111';
        $session->save();

        $sent = $this->json('POST', '/api/mai/v1/sessions/'.$id.'/messages', [
            'to' => '237671111222',
            'text' => 'Hello',
            'idempotency_key' => 'send1',
        ], $this->auth($key));
        $sent->assertOk()->assertJsonPath('provider_message_id', 'MSG1');
        $sends = $this->sendCount();
        $again = $this->json('POST', '/api/mai/v1/sessions/'.$id.'/messages', [
            'to' => '237671111222',
            'text' => 'Hello',
            'idempotency_key' => 'send1',
        ], $this->auth($key));
        $again->assertOk();
        $this->assertSame($sends, $this->sendCount());

        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'in-1',
            'from_me' => false,
            'remote' => '237671111222',
            'text' => 'Reply',
        ]);
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'in-1',
            'from_me' => false,
            'remote' => '237671111222',
            'text' => 'Reply',
        ]);
        $this->assertSame(1, WhatsAppMessage::where('direction', WhatsAppMessage::DIR_IN)->count());
        $this->assertSame(1, WhatsAppConversation::where('mai_session_id', $session->id)->count());
    }

    public function test_customers_cannot_claim_the_business_line_or_another_session()
    {
        $key = $this->account('ada@example.test');
        $this->json('POST', '/api/mai/v1/sessions', ['phone' => '237675321739'], $this->auth($key))
            ->assertStatus(422);
        $this->json('POST', '/api/mai/v1/sessions', [
            'phone' => '237670000111',
            'provider' => 'wasender',
        ], $this->auth($key))->assertStatus(422);
        $this->assertSame(0, MaiSession::where('provider', 'wasender')->count());

        $created = $this->json('POST', '/api/mai/v1/sessions', ['phone' => '237670000111'], $this->auth($key));
        $id = $created->json('session.id');
        $other = $this->account('bola@example.test');
        $this->json('GET', '/api/mai/v1/sessions/'.$id, [], $this->auth($other))->assertStatus(404);

        $plain = new WhatsAppConversation();
        $this->assertFalse(app(MaiProviderRouter::class)->usesMai($plain));
        $this->assertTrue(MaiBusinessLine::is('+237 675 321 739'));
    }

    public function test_pairing_expiry_wrong_number_timeout_unlink_and_handover()
    {
        $key = $this->account('ada@example.test');
        $created = $this->json('POST', '/api/mai/v1/sessions', ['phone' => '237670000111'], $this->auth($key));
        $session = MaiSession::where('public_id', $created->json('session.id'))->first();
        $service = app(MaiSessionService::class);

        $service->apply($session, ['status' => 'CONNECTED', 'phone' => '237670000111'], 'stale');
        $this->assertNotSame('CONNECTED', $session->fresh()->status);

        $session->pairing_expires_at = now()->subMinute();
        $session->save();
        $service->apply($session->fresh(), ['status' => 'CONNECTED', 'phone' => '237670000111'], $session->attempt_id);
        $this->assertSame('EXPIRED', $session->fresh()->status);

        $session = MaiSession::where('public_id', $created->json('session.id'))->first();
        $session->status = 'AWAITING_QR';
        $session->pairing_expires_at = now()->addMinutes(3);
        $session->save();
        $service->apply($session->fresh(), ['status' => 'CONNECTED', 'phone' => '237699999999'], $session->attempt_id);
        $this->assertSame('WRONG_NUMBER', $session->fresh()->status);

        $session = $session->fresh();
        $session->status = 'CONNECTED';
        $session->phone_number = '237670000111';
        $session->pairing_expires_at = now()->addMinutes(3);
        $session->save();
        $this->script(function ($method, $path) {
            $this->linkCalls[] = $method.' '.$path;
            if (substr($path, -5) === '/send') {
                return ['timeout' => true, 'success' => false];
            }

            return ['status' => 'CONNECTED', 'phone' => '237670000111'];
        });
        $this->json('POST', '/api/mai/v1/sessions/'.$session->public_id.'/messages', [
            'to' => '237671111222',
            'text' => 'Maybe',
            'idempotency_key' => 'slow',
        ], $this->auth($key))->assertStatus(202);
        $before = $this->sendCount();
        $this->json('POST', '/api/mai/v1/sessions/'.$session->public_id.'/messages', [
            'to' => '237671111222',
            'text' => 'Maybe',
            'idempotency_key' => 'slow',
        ], $this->auth($key))->assertStatus(202);
        $this->assertSame($before, $this->sendCount());
        $this->assertSame('uncertain', MaiOutbound::where('idempotency_key', 'slow')->value('status'));

        $this->post('/mai/sessions/'.$session->public_id.'/disconnect')->assertRedirect('/mai');
        $this->assertSame('UNLINKED', $session->fresh()->status);
        $this->assertSame(1, WhatsAppConversation::where('mai_session_id', $session->id)->count());

        $session->status = 'CONNECTED';
        $session->ai_enabled = 1;
        $session->save();
        $conversation = WhatsAppConversation::where('mai_session_id', $session->id)->first();
        $conversation->mode = WhatsAppConversation::MODE_AI;
        $conversation->save();
        config(['cloud.mai_customer.ai_reply' => function ($chat) {
            $chat->mode = WhatsAppConversation::MODE_HUMAN;
            $chat->save();

            return 'should not send';
        }]);
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'ai-1',
            'from_me' => false,
            'remote' => '237671111222',
            'text' => 'Need a person',
        ]);
        $this->assertSame(0, MaiOutbound::where('idempotency_key', 'like', 'ai-%')->count());

        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'echo-1',
            'from_me' => true,
            'remote' => '237671111222',
            'text' => 'Sent from the phone',
        ]);
        $this->assertSame(1, WhatsAppMessage::where('sender_type', 'ECHO')->count());
        $this->assertSame(0, MaiOutbound::where('idempotency_key', 'like', 'ai-%')->count());
    }

    public function test_webhook_rejects_private_hosts_and_signs_a_public_callback()
    {
        $key = $this->account('ada@example.test');
        $this->json('POST', '/api/mai/v1/webhooks', ['url' => 'http://127.0.0.1/hook'], $this->auth($key))
            ->assertStatus(422);
        $seen = [];
        config([
            'cloud.mai_customer.webhook_resolver' => function () {
                return ['8.8.8.8'];
            },
            'cloud.mai_customer.webhook_sender' => function ($url, $body, $headers) use (&$seen) {
                $seen[] = ['url' => $url, 'body' => $body, 'headers' => $headers];

                return 200;
            },
        ]);
        $this->json('POST', '/api/mai/v1/webhooks', ['url' => 'https://hooks.example.test/mai'], $this->auth($key))
            ->assertOk();
        $created = $this->json('POST', '/api/mai/v1/sessions', ['phone' => '237670000111'], $this->auth($key));
        $session = MaiSession::where('public_id', $created->json('session.id'))->first();
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'hook-1',
            'from_me' => false,
            'remote' => '237671111222',
            'text' => 'Ping',
        ]);
        $this->assertCount(1, $seen);
        $secret = decrypt(\App\Mai\MaiWebhook::first()->secret);
        $this->assertSame('sha256='.hash_hmac('sha256', $seen[0]['body'], $secret), $seen[0]['headers']['X-Mai-Signature']);
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'hook-1',
            'from_me' => false,
            'remote' => '237671111222',
            'text' => 'Ping',
        ]);
        $this->assertCount(1, $seen);
    }

    public function test_capabilities_are_text_only_until_each_feature_is_accepted()
    {
        $key = $this->account('ada@example.test');
        $this->json('GET', '/api/mai/v1/capabilities', [], $this->auth($key))
            ->assertOk()
            ->assertJsonPath('capabilities.text.accepted', true)
            ->assertJsonPath('capabilities.media.accepted', false)
            ->assertJsonPath('capabilities.groups.accepted', false)
            ->assertJsonPath('capabilities.voice.accepted', false)
            ->assertJsonPath('capabilities.history_sync.accepted', false)
            ->assertJsonPath('capabilities.read_receipts.accepted', false);
    }

    public function test_signed_in_pairing_rejects_the_business_line_and_a_different_number()
    {
        $user = new \App\User();
        $user->name = 'Ada';
        $user->email = 'ada-link@example.test';
        $user->password = bcrypt('portal-secret');
        $user->phone = '237670000111';
        $user->role_id = 5;
        $user->is_active = 1;
        $user->save();
        $this->actingAs($user);
        $seen = [];
        config(['cloud.whatsapp.link_transport' => function ($method, $path, $body) use (&$seen) {
            $seen[] = ['method' => $method, 'path' => $path, 'body' => $body];
            if (substr($path, -5) === '/pair') {
                return ['status' => 'AWAITING_QR', 'pairing_code' => 'ABCD-EFGH', 'pairing_age_ms' => 0];
            }
            if (isset($body['match']) && $body['match'] === 'wrong') {
                return ['status' => 'CONNECTED', 'phone' => '237699999999'];
            }

            return ['status' => 'AWAITING_QR', 'phone' => null, 'qr' => null];
        }]);

        $this->postJson('/whatsapp-mai/link/pair', ['phone' => '675321739', 'dial' => '237'])
            ->assertOk()
            ->assertJsonPath('status', 'ERROR');
        $this->assertCount(0, $seen);

        $paired = $this->postJson('/whatsapp-mai/link/pair', ['phone' => '670000111', 'dial' => '237', 'fresh' => 1]);
        $paired->assertOk()->assertJsonPath('pairing_code', 'ABCD-EFGH');
        $this->assertSame(1, $seen[0]['body']['fresh']);
        $this->assertSame('237670000111', $seen[0]['body']['phone']);
        $this->assertStringNotContainsString('ABCD-EFGH', json_encode($seen));

        config(['cloud.whatsapp.link_transport' => function () {
            return ['status' => 'CONNECTED', 'phone' => '237699999999'];
        }]);
        $this->getJson('/whatsapp-mai/link/status')
            ->assertOk()
            ->assertJsonPath('status', 'ERROR')
            ->assertJsonPath('redirect', null);

        config(['cloud.whatsapp.link_transport' => function () {
            return ['status' => 'CONNECTED', 'phone' => '237670000111'];
        }]);
        $this->getJson('/whatsapp-mai/link/status')
            ->assertOk()
            ->assertJsonPath('status', 'CONNECTED')
            ->assertJsonPath('redirect', url('/whatsapp-mai/inbox'));
    }

    protected function account($email)
    {
        $this->post('/mai/logout');
        $this->post('/mai/register', $this->person($email));
        $this->get('/mai/verify/'.MaiCustomerAuth::testingToken());
        $this->post('/mai/keys', ['name' => 'Shop'])->assertSessionHas('mai_key');

        return session('mai_key');
    }

    protected function person($email)
    {
        return [
            'name' => 'Ada',
            'email' => $email,
            'password' => 'portal-secret',
        ];
    }

    protected function auth($key)
    {
        return ['Authorization' => 'Bearer '.$key, 'Accept' => 'application/json'];
    }

    protected function script($handler)
    {
        config(['cloud.mai_customer.transport' => $handler]);
    }

    protected function sendCount()
    {
        $count = 0;
        foreach ($this->linkCalls as $call) {
            if (substr($call, -5) === '/send') {
                $count++;
            }
        }

        return $count;
    }
}
