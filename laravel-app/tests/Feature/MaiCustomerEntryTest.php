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
        $inbox->assertSee('data-mai-install');
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
            ->assertJsonPath('status', 'CONNECTED')
            ->assertJsonPath('redirect', url('/whatsapp-mai/inbox'));
        $sent = (string) session('mai_phone_link_sent');
        $this->assertNotSame('', $sent);
        $this->assertStringContainsString('670000505', $sent);
        $this->assertStringNotContainsString('675321739', $sent);
        $session = MaiSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertSame('237670000505', $session->phone_number);
        $this->assertSame('p237670000505', $session->worker_session_id);
    }

    public function test_an_android_phone_opens_the_sent_link_on_the_download_button_and_links_by_code_only()
    {
        $user = $this->person('Faye', '237670000506');
        $this->script(['status' => 'CONNECTED', 'phone' => '237670000506']);
        $url = \URL::temporarySignedRoute('whatsapp.mai.enter', now()->addDay(), ['user' => $user->id]);
        $parts = parse_url($url);
        $android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

        $page = $this->withHeader('User-Agent', $android)->get($parts['path'].'?'.$parts['query']);
        $page->assertOk();
        $html = $page->getContent();
        $download = strpos($html, 'Download for Android');
        $continue = strpos($html, 'Continue to Whats MAI');
        $this->assertNotFalse($download);
        $this->assertNotFalse($continue);
        $this->assertLessThan($continue, $download);
        $this->assertStringContainsString('data-mai-install', $html);
        $this->assertStringContainsString('/whatsapp-mai/manifest.webmanifest', $html);
        $this->assertStringContainsString('beforeinstallprompt', $html);
        $this->assertStringNotContainsString('whats-mai.webmanifest', $html);

        $manifest = $this->get('/whatsapp-mai/manifest.webmanifest');
        $manifest->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $manifest->headers->get('content-type'));
        $manifest->assertSee('mai-icon-192.png');
        $manifest->assertSee('mai-icon-512.png');
        $manifest->assertSee('"display":"standalone"', false);
        $manifest->assertSee('"start_url":"/whatsapp-mai/open"', false);
        $manifest->assertSee('"scope":"/whatsapp-mai/"', false);
        $this->assertSame([192, 192], $this->pngSize(public_path('branding/mai-icon-192.png')));
        $this->assertSame([512, 512], $this->pngSize(public_path('branding/mai-icon-512.png')));
        $worker = $this->get('/whatsapp-mai/sw.js');
        $worker->assertOk();
        $this->assertStringNotContainsString('respondWith', $worker->getContent());
        $this->assertStringContainsString('caches.delete', $worker->getContent());
        $installJs = file_get_contents(public_path('js/mai-install.js'));
        $this->assertStringContainsString('beforeinstallprompt', $installJs);
        $this->assertStringContainsString('.prompt()', $installJs);
        $this->assertStringContainsString("showSheet('dismissed')", $installJs);
        $this->assertStringContainsString('Install app', $installJs);
        $this->assertStringContainsString('Open in Chrome', $installJs);

        $this->script(['status' => 'NOT_CONNECTED']);
        $this->actingAs($user);
        $this->withHeader('User-Agent', $android)->get('/whatsapp-mai/link')
            ->assertOk()
            ->assertSee('Get the code')
            ->assertDontSee('Scan QR');
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $this->withHeader('User-Agent', $iphone)->get('/whatsapp-mai/link')
            ->assertOk()
            ->assertDontSee('Scan QR');
    }

    public function test_an_android_inbox_opens_without_waiting_for_a_full_sync()
    {
        $user = $this->person('Gia', '237670000507');
        $this->script(['status' => 'CONNECTED', 'phone' => '237670000507']);
        $this->actingAs($user);
        $android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $this->withHeader('User-Agent', $android)->get('/whatsapp-mai/inbox')->assertOk();
        $this->assertNotContains('GET /sessions/p237670000507/chats', $this->calls);
    }

    public function test_a_signed_out_visitor_is_sent_to_mai_sign_in_once()
    {
        $this->get('/whatsapp-mai/inbox')->assertRedirect('/whatsapp-mai/open');
        $this->get('/whatsapp-mai/link')->assertRedirect('/whatsapp-mai/open');
        $open = $this->get('/whatsapp-mai/open');
        $open->assertOk()->assertSee('Sign in to Whats MAI');
        $open->assertSee('/whatsapp-mai/manifest.webmanifest');
        $open->assertDontSee('whats-mai.webmanifest');
        $this->getJson('/whatsapp-mai/link/status')->assertStatus(401)->assertJsonPath('signin', url('/whatsapp-mai/open'));
    }

    public function test_the_business_line_stays_on_the_staff_inbox()
    {
        $user = $this->person('Desk', '237675321739');
        $this->script(['status' => 'CONNECTED', 'phone' => '237675321739']);
        $this->actingAs($user);
        $this->get('/whatsapp-mai')->assertRedirect('/admin/whatsapp/chats');
        $this->assertSame([], $this->posted());
    }

    public function test_a_phone_code_stays_on_the_same_attempt_until_a_fresh_code_replaces_it()
    {
        $user = $this->person('Hana', '237670000508');
        $this->script(['status' => 'AWAITING_QR', 'pairing_code' => 'WXYZ-1234', 'pairing_age_ms' => 1000]);
        $this->actingAs($user);

        session(['mai_link_phone' => '237670000508']);
        $waiting = $this->get('/whatsapp-mai/link');
        $waiting->assertOk()->assertSee('WXYZ-1234');
        $this->assertStringContainsString('id="pair-status">Waiting for approval in WhatsApp.', $waiting->getContent());
        $this->assertSame([], $this->posted());

        $this->script(['status' => 'AWAITING_QR', 'pairing_code' => 'WXYZ-1234', 'pairing_age_ms' => 61000]);
        $this->actingAs($user);
        session(['mai_link_phone' => '237670000508']);
        $expired = $this->get('/whatsapp-mai/link');
        $expired->assertOk();
        $this->assertStringContainsString('id="pair-status">This code expired. Create a new one.', $expired->getContent());
        $this->assertSame([], $this->posted());

        $this->script(['status' => 'ERROR', 'error' => 'The link failed.']);
        $this->actingAs($user);
        session(['mai_link_phone' => '237670000508']);
        $failed = $this->get('/whatsapp-mai/link');
        $failed->assertOk();
        $this->assertStringContainsString('id="pair-status">The link failed.', $failed->getContent());
        $this->assertSame([], $this->posted());

        $this->script(['status' => 'AWAITING_QR', 'pairing_code' => 'NEW1-2345', 'pairing_age_ms' => 0]);
        $this->actingAs($user);
        $this->postJson('/whatsapp-mai/link/pair', ['phone' => '670000508', 'dial' => '237', 'fresh' => 1])
            ->assertOk()
            ->assertJsonPath('pairing_code', 'NEW1-2345');
        $this->assertContains('POST /sessions/p237670000508/pair fresh', $this->posted());
    }

    public function test_a_code_for_another_account_is_rejected_and_a_qr_uses_the_same_redirect()
    {
        $owner = $this->person('Ivo', '237670000509');
        $other = $this->person('Jae', '237670000510');
        $session = new MaiSession();
        $session->public_id = 'c510';
        $session->user_id = $other->id;
        $session->provider = 'mai';
        $session->worker_session_id = 'p237670000510';
        $session->phone_number = '237670000510';
        $session->status = 'CONNECTED';
        $session->save();
        $this->script(['status' => 'CONNECTED', 'phone' => '237670000510', 'qr' => 'data:image/png;base64,abc']);
        $this->actingAs($owner);

        $this->postJson('/whatsapp-mai/link/pair', ['phone' => '670000510', 'dial' => '237'])
            ->assertOk()
            ->assertJsonPath('status', 'ERROR');
        $this->assertSame([], $this->posted());
        $owner->phone = '237670000510';
        $owner->save();
        $this->postJson('/whatsapp-mai/link/pair', ['phone' => '670000510', 'dial' => '237'])
            ->assertOk()
            ->assertJsonPath('error', 'That WhatsApp is already linked to another MAI account.');
        $this->assertSame([], $this->posted());
        $owner->phone = '237670000509';
        $owner->save();

        $this->script(['status' => 'CONNECTED', 'phone' => '237670000509', 'qr' => 'data:image/png;base64,abc']);
        $this->actingAs($owner);
        $qr = $this->postJson('/whatsapp-mai/link/qr', ['phone' => '670000509', 'dial' => '237']);
        $qr->assertOk()->assertJsonPath('status', 'CONNECTED')->assertJsonPath('redirect', url('/whatsapp-mai/inbox'));
        $posts = $this->posted();
        $this->get('/whatsapp-mai/link')->assertRedirect('/whatsapp-mai/inbox');
        $this->assertSame($posts, $this->posted());
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
        config(['cloud.whatsapp.link_transport' => function ($method, $path, $body = null) use ($state) {
            $line = $method.' '.$path;
            if (is_array($body) && ! empty($body['fresh'])) {
                $line .= ' fresh';
            }
            $this->calls[] = $line;

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

    protected function pngSize($path)
    {
        $raw = file_get_contents($path);
        $this->assertNotFalse($raw);
        $this->assertSame('PNG', substr($raw, 1, 3));

        return [unpack('N', substr($raw, 16, 4))[1], unpack('N', substr($raw, 20, 4))[1]];
    }
}
