<?php

namespace Tests\Feature;

use App\Mai\MaiSession;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiCustomerEntryTest extends TestCase
{
    protected $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.mai_customer.enabled' => true, 'cloud.mai_customer.api_sending' => false]);
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\LogActivity::class,
            \App\Http\Middleware\ResolveCloudTenant::class,
        ]);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
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
            $table->timestamps();
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_id')->index();
            $table->unsignedInteger('mai_session_id')->nullable()->index();
            $table->string('mode', 16)->default('HUMAN');
            $table->string('status', 16)->default('OPEN');
            $table->string('channel', 32)->nullable();
            $table->string('peer_label', 191)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('last_message')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('direction', 16);
            $table->string('type', 24)->default('TEXT');
            $table->text('body')->nullable();
            $table->string('status', 16)->default('SENT');
            $table->timestamps();
        });
        Schema::create('general_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_title')->nullable();
            $table->string('site_logo')->nullable();
            $table->string('currency')->nullable();
            $table->string('currency_position')->nullable();
            $table->string('staff_access')->nullable();
            $table->string('date_format')->nullable();
            $table->timestamps();
        });
        \DB::table('general_settings')->insert(['id' => 1, 'site_title' => 'Beyond']);
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_26_233000_create_mai_customer_sessions.php', '--force' => true]);
        $this->script(['status' => 'NOT_CONNECTED']);
    }

    public function test_a_connected_customer_opens_the_shared_inbox_from_every_entry()
    {
        $user = $this->person('Ada', '237670000501');
        $this->script(['status' => 'CONNECTED', 'phone' => '237670000501']);
        $this->actingAs($user);

        $this->get('/whatsapp-mai')->assertRedirect('/whatsapp-mai/inbox');
        $this->get('/whatsapp-mai/open')->assertRedirect('/whatsapp-mai/inbox');
        $this->get('/whatsapp-mai/home')->assertRedirect('/whatsapp-mai/inbox');
        $posts = $this->posted();
        $this->assertSame([], $posts);

        $inbox = $this->get('/whatsapp-mai/inbox');
        $inbox->assertOk();
        $inbox->assertSee('Announcements');
        $inbox->assertSee('Dashboard');
        $inbox->assertSee('Translation');
        $inbox->assertSee('AI Brief');
        $inbox->assertSee('Settings');
        $inbox->assertSee('Sign out');
        $inbox->assertSee('/whatsapp-mai/dashboard');
        $inbox->assertSee('/whatsapp-mai/inbox?pane=speech');
        $inbox->assertSee('/whatsapp-mai/inbox?pane=briefing');
        $inbox->assertSee('/whatsapp-mai/inbox?pane=announcements');
        $inbox->assertDontSee('/admin/whatsapp"');
        $inbox->assertDontSee('Clients dashboard');
        $this->assertFalse((bool) config('cloud.mai_customer.api_sending'));
        $this->get('/whatsapp-mai/inbox?pane=speech')->assertOk()->assertSee('Translation');
        $this->get('/whatsapp-mai/inbox?pane=briefing')->assertOk()->assertSee('AI Brief');
        $this->get('/whatsapp-mai/inbox?pane=announcements')->assertOk()->assertSee('Announcements');
        $this->get('/whatsapp-mai/dashboard')->assertOk()->assertSee('Your dashboard')->assertSee('Ada')->assertDontSee('Operator dashboard');
    }

    public function test_refresh_resumes_one_pairing_attempt_and_linking_does_not_ask_for_an_otp()
    {
        $user = $this->person('Bea', '237670000502');
        $this->script(['status' => 'AWAITING_QR', 'qr' => 'data:image/png;base64,abc', 'pairing_code' => 'WXYZ-1234']);
        $this->actingAs($user);

        $this->get('/whatsapp-mai/open')->assertRedirect('/whatsapp-mai/link');
        $this->get('/whatsapp-mai/link')->assertOk()->assertSee('Link by code')->assertSee('Scan QR')->assertDontSee('link/otp');
        $this->post('/whatsapp-mai/link/otp')->assertRedirect('/whatsapp-mai/link');
        $this->post('/whatsapp-mai/link/otp/verify', ['code' => '123456'])->assertRedirect('/whatsapp-mai/link');
        $this->assertNotContains('POST /sessions/p237670000502/pair', $this->posted());
        $this->assertNotContains('POST /sessions', $this->posted());

        $paired = $this->postJson('/whatsapp-mai/link/pair', ['phone' => '670000502', 'dial' => '237']);
        $paired->assertOk()->assertJsonPath('pairing_code', 'WXYZ-1234');
        $pairCalls = 0;
        foreach ($this->posted() as $call) {
            if (strpos($call, '/pair') !== false) {
                $pairCalls++;
            }
        }
        $this->assertSame(1, $pairCalls);
        $this->get('/whatsapp-mai/link')->assertOk();
        $again = 0;
        foreach ($this->posted() as $call) {
            if (strpos($call, '/pair') !== false) {
                $again++;
            }
        }
        $this->assertSame(1, $again);
    }

    public function test_a_reconnecting_customer_keeps_the_inbox_and_a_stranger_chat_stays_closed()
    {
        $owner = $this->person('Cara', '237670000503');
        $other = $this->person('Dara', '237670000504');
        $session = new MaiSession();
        $session->public_id = 'c503';
        $session->user_id = $owner->id;
        $session->provider = 'mai';
        $session->worker_session_id = 'p237670000503';
        $session->phone_number = '237670000503';
        $session->expected_phone = '237670000503';
        $session->status = 'CONNECTED';
        $session->save();
        $foreign = new MaiSession();
        $foreign->public_id = 'c504';
        $foreign->user_id = $other->id;
        $foreign->provider = 'mai';
        $foreign->worker_session_id = 'p237670000504';
        $foreign->phone_number = '237670000504';
        $foreign->status = 'CONNECTED';
        $foreign->save();
        $contact = new WhatsAppContact();
        $contact->normalized_phone = '237699000777';
        $contact->display_phone = '+237699000777';
        $contact->wa_name = 'Secret';
        $contact->save();
        $chat = new WhatsAppConversation();
        $chat->contact_id = $contact->id;
        $chat->mai_session_id = $foreign->id;
        $chat->mode = 'HUMAN';
        $chat->status = 'OPEN';
        $chat->peer_label = 'Secret';
        $chat->save();
        $this->script(['status' => 'CONNECTING', 'phone' => '237670000503']);
        $this->actingAs($owner);

        $this->get('/whatsapp-mai')->assertRedirect('/whatsapp-mai/inbox');
        $page = $this->get('/whatsapp-mai/inbox');
        $page->assertOk()->assertSee('WhatsApp is reconnecting');
        $this->get('/whatsapp-mai/inbox?open=1&chat='.$chat->id)->assertJson(['ok' => false]);
        $this->assertNotContains('POST /sessions', $this->posted());
    }

    public function test_a_confirmed_link_sends_the_open_link_to_that_phone_and_creates_its_session()
    {
        $user = $this->person('Ema', '237670000505');
        $this->script(['status' => 'CONNECTED', 'phone' => '237670000505']);
        $this->actingAs($user);

        $this->getJson('/whatsapp-mai/link/status')
            ->assertOk()
            ->assertJsonPath('status', 'CONNECTED');
        $sent = (string) session('mai_phone_link_sent');
        $this->assertNotSame('', $sent);
        $this->assertStringContainsString('670000505', $sent);
        $this->assertStringNotContainsString('675321739', $sent);
        $session = MaiSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertSame('237670000505', $session->phone_number);
        $this->assertSame('p237670000505', $session->worker_session_id);
    }

    public function test_a_signed_out_visitor_is_sent_to_mai_sign_in_once()
    {
        $this->get('/whatsapp-mai/inbox')->assertRedirect('/whatsapp-mai/open');
        $this->get('/whatsapp-mai/link')->assertRedirect('/whatsapp-mai/open');
        $this->get('/whatsapp-mai/open')->assertOk()->assertSee('Sign in to Whats MAI');
    }

    public function test_the_business_line_stays_on_the_staff_inbox()
    {
        $user = $this->person('Desk', '237675321739');
        $this->script(['status' => 'CONNECTED', 'phone' => '237675321739']);
        $this->actingAs($user);
        $this->get('/whatsapp-mai')->assertRedirect('/admin/whatsapp/chats');
        $this->assertSame([], $this->posted());
    }

    protected function person($name, $phone)
    {
        $user = new \App\User();
        $user->name = $name;
        $user->email = strtolower($name).'@example.test';
        $user->password = bcrypt('portal-secret');
        $user->phone = $phone;
        $user->role_id = 5;
        $user->is_active = 1;
        $user->save();

        return $user;
    }

    protected function script(array $state)
    {
        $this->calls = [];
        config(['cloud.whatsapp.link_transport' => function ($method, $path) use ($state) {
            $this->calls[] = $method.' '.$path;

            return $state;
        }]);
    }

    protected function posted()
    {
        $posts = [];
        foreach ($this->calls as $call) {
            if (strpos($call, 'POST ') === 0) {
                $posts[] = $call;
            }
        }

        return $posts;
    }
}
