<?php

namespace Tests\Feature;

use App\Http\Controllers\MaiVoiceController;
use App\Services\Voice\MaiVoiceStatus;
use App\Services\Voice\MaiVoiceSwitch;
use App\Services\Voice\StubVoiceProvider;
use App\Services\Voice\VoiceGateway;
use App\Services\Voice\VoiceToolBridge;
use App\User;
use App\Voice\VoiceCall;
use App\Voice\VoiceCallback;
use App\Voice\VoiceConnection;
use App\WhatsApp\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiVoiceTest extends TestCase
{
    protected $switchPath;

    protected $switchExisted;

    protected $switchBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->switchPath = MaiVoiceSwitch::path();
        $this->switchExisted = is_file($this->switchPath);
        $this->switchBackup = $this->switchExisted ? file_get_contents($this->switchPath) : null;
        require_once database_path('migrations/2026_10_03_190000_create_mai_voice.php');
        (new \CreateMaiVoice())->up();
        config([
            'voice.enabled' => false,
            'voice.engine' => 'scripted',
            'voice.simulate_ai_failure' => false,
            'voice.tool_runner' => null,
            'voice.clock' => null,
            'voice.recording' => true,
            'voice.customer_voice' => true,
            'voice.max_concurrent' => 2,
            'voice.max_calls_per_caller_per_hour' => 8,
            'voice.max_duration_seconds' => 600,
            'voice.max_silent_turns' => 3,
        ]);
        $stub = app(StubVoiceProvider::class);
        $stub->failTransfer = false;
        $stub->failProvider = false;
        $stub->transfers = [];
        $stub->hangups = [];
    }

    protected function tearDown(): void
    {
        if ($this->switchExisted) {
            file_put_contents($this->switchPath, $this->switchBackup);
        } elseif (is_file($this->switchPath)) {
            @unlink($this->switchPath);
        }
        parent::tearDown();
    }

    public function test_kill_switch_rejects_before_a_file_flip_answers()
    {
        $this->connection();
        config(['voice.enabled' => true]);
        MaiVoiceSwitch::set(false);
        $off = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'c1');
        $this->assertFalse($off['ok']);
        $this->assertSame('voice_off', $off['reason']);
        $this->assertSame('REJECTED', $off['call']->status);

        MaiVoiceSwitch::set(true);
        $on = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'c2');
        $this->assertTrue($on['ok']);
        $this->assertStringContainsString('virtual assistant', $on['speech']);
        $this->assertStringContainsString('BeyondTechWorld', $on['speech']);
        $this->assertStringContainsString('MAI', $on['speech']);
    }

    public function test_unknown_caller_is_told_this_is_an_assistant_and_asked_for_a_name()
    {
        $this->on();
        $this->connection();
        $opened = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'u1');
        $this->assertStringContainsString("I'm MAI, the virtual assistant", $opened['speech']);
        $this->assertStringContainsString('May I have your name?', $opened['speech']);
        $this->assertStringNotContainsString('Welcome back', $opened['speech']);
        $this->assertFalse((bool) $opened['call']->identity_verified);
        $this->assertFalse((bool) $opened['call']->recording);

        $named = app(VoiceGateway::class)->hear($opened['call'], 'My name is Ada');
        $this->assertStringContainsString('Ada', $named['speech']);
    }

    public function test_known_caller_is_greeted_without_becoming_verified()
    {
        $this->on();
        $this->connection();
        $this->contacts();
        DB::table('whatsapp_contacts')->insert([
            'normalized_phone' => '237670000111',
            'wa_name' => 'Saved name',
            'call_name' => 'Marie',
            'cloud_tenant_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opened = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'k1');
        $this->assertStringContainsString('Welcome back, Marie', $opened['speech']);
        $this->assertFalse((bool) $opened['call']->identity_verified);
        $this->assertSame(1, (int) $opened['call']->cloud_tenant_id);
    }

    public function test_two_matching_contacts_are_not_greeted_by_name()
    {
        $this->on();
        $this->connection();
        $this->contacts();
        foreach (['Marie', 'Ada'] as $name) {
            DB::table('whatsapp_contacts')->insert([
                'normalized_phone' => '237670000111',
                'wa_name' => $name,
                'cloud_tenant_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $opened = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'amb');
        $this->assertStringNotContainsString('Welcome back', $opened['speech']);
    }

    public function test_caller_id_does_not_select_the_tenant_or_authorize_a_payment_summary()
    {
        $this->on();
        $this->connection();
        $this->contacts();
        DB::table('whatsapp_contacts')->insert([
            'normalized_phone' => '237670000111',
            'wa_name' => 'Secret Customer',
            'cloud_tenant_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $called = [];
        config(['voice.tool_runner' => function ($name) use (&$called) {
            $called[] = $name;

            return ['price' => '1'];
        }]);
        $opened = app(VoiceGateway::class)->incoming('237670000222', '237670000111', 'iso');
        $this->assertSame(1, (int) $opened['call']->cloud_tenant_id);
        $this->assertStringNotContainsString('Secret Customer', $opened['speech']);
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'Show my payment summary');
        $this->assertStringContainsString('verified identity', $heard['speech']);
        $this->assertSame([], $called);
        $this->assertFalse(app(VoiceToolBridge::class)->decide('get_customer_payment_summary', ['verified' => false])['allowed']);
        $this->assertFalse(app(VoiceToolBridge::class)->decide('set_ai_enabled', ['verified' => true])['allowed']);
        $this->assertTrue(app(VoiceToolBridge::class)->decide('search_rental_products', ['verified' => false])['allowed']);
        $this->assertFalse(app(VoiceToolBridge::class)->decide('DROP TABLE users', [])['allowed']);
    }

    public function test_payment_claim_is_not_confirmed()
    {
        $this->on();
        $this->connection();
        $called = [];
        config(['voice.tool_runner' => function ($name) use (&$called) {
            $called[] = $name;

            return ['ok' => true];
        }]);
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'I paid, mark the invoice paid');
        $this->assertStringContainsString('cannot confirm a payment', $heard['speech']);
        $this->assertStringNotContainsString('succeeded', $heard['speech']);
        $this->assertSame([], $called);
    }

    public function test_incoming_call_opens_a_voice_conversation_for_the_called_number()
    {
        $this->on();
        $this->connection();
        $this->contacts();
        $this->conversations();
        $opened = app(VoiceGateway::class)->incoming('+237 670 000 222', '0670000111', 'prov-9');
        $this->assertTrue($opened['ok']);
        $this->assertSame('237670000222', $opened['call']->called_number);
        $this->assertNotNull($opened['call']->conversation_id);
        $row = DB::table('whatsapp_conversations')->where('id', $opened['call']->conversation_id)->first();
        $this->assertSame('voice', $row->channel);
        $this->assertSame('AI', $row->mode);
        $this->assertSame(1, (int) $row->cloud_tenant_id);
        $this->assertSame('prov-9', $opened['call']->provider_call_id);
        $this->assertSame('stub', $opened['call']->provider);
    }

    public function test_multi_turn_event_keeps_the_date_with_the_wedding()
    {
        $this->on();
        $this->connection();
        $tools = [];
        config(['voice.tool_runner' => function ($name, $params) use (&$tools) {
            $tools[] = ['name' => $name, 'params' => $params];

            return ['ok' => true];
        }]);
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $first = $gateway->hear($opened['call'], 'I need sound for a wedding.');
        $this->assertStringContainsString('What date is the wedding?', $first['speech']);
        $second = $gateway->hear($first['call'], 'November 14');
        $this->assertStringContainsString('where will the event take place', $second['speech']);
        $third = $gateway->hear($second['call'], 'Bamenda');
        $this->assertStringContainsString('wedding', $third['speech']);
        $this->assertStringContainsString('November 14', $third['speech']);
        $this->assertStringContainsString('Bamenda', $third['speech']);
        $names = array_column($tools, 'name');
        $this->assertContains('build_event_solution', $names);
        $built = null;
        foreach ($tools as $tool) {
            if ($tool['name'] === 'build_event_solution') {
                $built = $tool['params'];
            }
        }
        $this->assertSame('wedding', $built['event_type']);
        $this->assertSame('November 14', $built['event_date']);
    }

    public function test_product_price_and_rental_answers_come_from_the_tool_result()
    {
        $this->on();
        $this->connection();
        config(['voice.tool_runner' => function ($name) {
            if ($name === 'check_rental_availability') {
                return ['available' => false];
            }
            if ($name === 'search_rental_products') {
                return ['name' => 'JBL EON', 'price' => '15000 XAF'];
            }

            return null;
        }]);
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $product = $gateway->hear($opened['call'], 'Do you have JBL speakers?');
        $this->assertStringContainsString('JBL EON', $product['speech']);
        $this->assertStringNotContainsString('ZX-9000', $product['speech']);
        $price = $gateway->hear($product['call'], 'How much are they?');
        $this->assertStringContainsString('15000 XAF', $price['speech']);
        $rent = $gateway->hear($price['call'], 'Are they available next Saturday?');
        $this->assertStringContainsString('not available', $rent['speech']);
    }

    public function test_missing_catalogue_data_is_not_invented()
    {
        $this->on();
        $this->connection();
        config(['voice.tool_runner' => function () {
            return null;
        }]);
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $product = $gateway->hear($opened['call'], 'Do you have JBL speakers?');
        $this->assertStringContainsString('catalogue', $product['speech']);
        $this->assertStringNotContainsString('JBL EON', $product['speech']);
        $price = $gateway->hear($product['call'], 'How much is a line array?');
        $this->assertStringContainsString('authoritative price', $price['speech']);
        $this->assertStringNotContainsString('5000', $price['speech']);
    }

    public function test_quotation_stays_a_draft_and_does_not_write_a_sale()
    {
        $this->on();
        $this->connection();
        Schema::create('quotations', function ($table) {
            $table->increments('id');
            $table->string('status')->nullable();
        });
        $tools = [];
        config(['voice.tool_runner' => function ($name) use (&$tools) {
            $tools[] = $name;

            return ['status' => 'draft'];
        }]);
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $gateway->hear($opened['call'], 'I need sound for a wedding.');
        $call = VoiceCall::orderBy('id', 'desc')->first();
        $gateway->hear($call, 'November 14');
        $call = VoiceCall::orderBy('id', 'desc')->first();
        $gateway->hear($call, 'Bamenda');
        $call = VoiceCall::orderBy('id', 'desc')->first();
        $quote = $gateway->hear($call, 'Please prepare a quotation');
        $this->assertStringContainsString('not confirmed', $quote['speech']);
        $this->assertStringContainsString('approval', $quote['speech']);
        $this->assertContains('create_event_quotation_draft', $tools);
        $this->assertSame(0, DB::table('quotations')->count());
    }

    public function test_transfer_uses_the_configured_number_then_mai_stops_speaking()
    {
        $this->on();
        $this->connection();
        $this->contacts();
        $this->conversations();
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $moved = $gateway->hear($opened['call'], 'Transfer me to 237600000099');
        $this->assertSame('HUMAN', $moved['call']->handling);
        $this->assertSame('SUCCESS', $moved['call']->transfer_status);
        $stub = app(StubVoiceProvider::class);
        $this->assertSame('237699111222', $stub->transfers[0]['target']);
        $this->assertStringNotContainsString('600000099', $moved['speech']);
        $this->assertStringNotContainsString('699111222', $moved['speech']);
        $before = $this->assistantLines($moved['call']);
        $again = $gateway->hear($moved['call'], 'Hello, are you still there?');
        $this->assertFalse($again['spoke']);
        $this->assertSame('', $again['speech']);
        $this->assertCount(count($before), $this->assistantLines($again['call']));
        $mode = DB::table('whatsapp_conversations')->where('id', $opened['call']->conversation_id)->value('mode');
        $this->assertSame('HUMAN', $mode);
    }

    public function test_failed_transfer_offers_a_message()
    {
        $this->on();
        $this->connection();
        app(StubVoiceProvider::class)->failTransfer = true;
        $opened = $this->answer();
        $gateway = app(VoiceGateway::class);
        $failed = $gateway->hear($opened['call'], 'Let me speak with someone');
        $this->assertSame('FAILED', $failed['call']->transfer_status);
        $this->assertSame('MAI', $failed['call']->handling);
        $this->assertStringContainsString('take a message', $failed['speech']);
        $saved = $gateway->hear($failed['call'], 'The speakers were late');
        $row = VoiceCallback::first();
        $this->assertSame('The speakers were late', $row->message);
        $this->assertSame('237670000111', $row->caller_phone);
        $this->assertStringContainsString('message', strtolower($saved['speech']));
    }

    public function test_callback_does_not_speak_a_private_number()
    {
        $this->on();
        $this->connection();
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'Ask Roland to call me');
        $row = VoiceCallback::first();
        $this->assertSame('Roland', $row->requested_for);
        $this->assertSame('237670000111', $row->caller_phone);
        $this->assertStringNotContainsString('699111222', $heard['speech']);
        $this->assertStringContainsString('Roland', $heard['speech']);
        $this->assertSame([], app(StubVoiceProvider::class)->transfers);
    }

    public function test_hangup_is_a_controlled_provider_action()
    {
        $this->on();
        $this->connection();
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], "That's all, goodbye");
        $this->assertSame('ENDED', $heard['call']->status);
        $this->assertStringContainsString('Thank you for calling BeyondTechWorld', $heard['speech']);
        $this->assertNotEmpty(app(StubVoiceProvider::class)->hangups);
        $this->assertFalse((bool) $heard['call']->recording);
    }

    public function test_ai_failure_does_not_touch_a_whatsapp_message()
    {
        $this->on();
        $this->connection(['transfer_number' => null]);
        Schema::create('whatsapp_messages', function ($table) {
            $table->increments('id');
            $table->text('body')->nullable();
        });
        DB::table('whatsapp_messages')->insert(['id' => 1, 'body' => 'leave this message']);
        config(['voice.simulate_ai_failure' => true]);
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'Hello');
        $this->assertTrue($heard['ok']);
        $this->assertStringContainsString("can't continue", $heard['speech']);
        $this->assertSame('leave this message', DB::table('whatsapp_messages')->where('id', 1)->value('body'));
        $this->assertSame('ANSWERED', $heard['call']->status);
    }

    public function test_provider_failure_stays_on_the_voice_call()
    {
        $this->on();
        $this->connection();
        Schema::create('whatsapp_messages', function ($table) {
            $table->increments('id');
            $table->text('body')->nullable();
        });
        DB::table('whatsapp_messages')->insert(['id' => 1, 'body' => 'whatsapp stays']);
        app(StubVoiceProvider::class)->failProvider = true;
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'I want a human');
        $this->assertTrue($heard['ok']);
        $this->assertSame('FAILED', $heard['call']->transfer_status);
        $this->assertSame('provider_down', $heard['call']->status_reason);
        $this->assertSame('whatsapp stays', DB::table('whatsapp_messages')->where('id', 1)->value('body'));
    }

    public function test_conversational_engine_flag_does_not_call_out_and_falls_back()
    {
        $this->on();
        $this->connection(['transfer_number' => null]);
        config(['voice.engine' => 'conversational']);
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'Hello');
        $this->assertTrue($heard['ok']);
        $this->assertStringContainsString("can't continue", $heard['speech']);
        $this->assertSame('ai_failure', $heard['call']->status_reason);
    }

    public function test_duration_limit_silent_calls_concurrency_and_repeat_callers()
    {
        $this->on();
        $this->connection();
        config(['voice.max_duration_seconds' => 60]);
        $opened = $this->answer();
        $opened['call']->answered_at = Carbon::now()->subMinutes(5);
        $opened['call']->save();
        $limited = app(VoiceGateway::class)->hear($opened['call'], 'Still talking');
        $this->assertSame('ENDED', $limited['call']->status);
        $this->assertSame('duration', $limited['call']->status_reason);

        config(['voice.max_duration_seconds' => 600, 'voice.max_silent_turns' => 3]);
        $quiet = $this->answer('237670000333');
        $gateway = app(VoiceGateway::class);
        $gateway->hear($quiet['call'], ' ');
        $gateway->hear(VoiceCall::find($quiet['call']->id), '');
        $ended = $gateway->hear(VoiceCall::find($quiet['call']->id), '');
        $this->assertSame('ENDED', $ended['call']->status);
        $this->assertSame('silence', $ended['call']->status_reason);

        config(['voice.max_concurrent' => 1]);
        $busy = $this->answer('237670000444');
        $this->assertTrue($busy['ok']);
        $blocked = app(VoiceGateway::class)->incoming('237670000222', '237670000555', 'over');
        $this->assertSame('concurrency', $blocked['reason']);

        VoiceCall::where('status', 'ANSWERED')->update(['status' => 'ENDED']);
        config(['voice.max_concurrent' => 5, 'voice.max_calls_per_caller_per_hour' => 2]);
        $this->assertTrue($this->answer('237670000777')['ok']);
        $this->assertTrue($this->answer('237670000777')['ok']);
        $spam = app(VoiceGateway::class)->incoming('237670000222', '237670000777', 'spam');
        $this->assertSame('rate_limit', $spam['reason']);
    }

    public function test_customer_connection_and_unknown_number_are_refused()
    {
        $this->on();
        $this->connection([
            'cloud_tenant_id' => 2,
            'tenant_type' => 'CUSTOMER',
            'called_number' => '237670000999',
        ]);
        $customer = app(VoiceGateway::class)->incoming('237670000999', '237670000111', 'cust');
        $this->assertSame('customer_voice_disabled', $customer['reason']);
        $this->assertNull($customer['call']->conversation_id);

        $unknown = app(VoiceGateway::class)->incoming('237670000123', '237670000111', 'nope');
        $this->assertSame('unknown_number', $unknown['reason']);
        $this->assertNull($unknown['call']->cloud_tenant_id);
    }

    public function test_after_hours_and_french_greeting_use_the_language_pack()
    {
        $this->on();
        $this->connection([
            'language' => 'fr',
            'business_hours' => json_encode(['open' => '08:00', 'close' => '18:00', 'days' => [1, 2, 3, 4, 5]]),
        ]);
        config(['voice.clock' => function () {
            return Carbon::parse('2026-10-03 22:15:00');
        }]);
        $opened = $this->answer();
        $this->assertStringContainsString('fermés', $opened['speech']);
        $this->assertStringContainsString('assistant virtuel', $opened['speech']);
    }

    public function test_latency_is_recorded_and_recording_stays_off()
    {
        $this->on();
        $this->connection();
        $opened = $this->answer();
        $heard = app(VoiceGateway::class)->hear($opened['call'], 'Hello');
        $this->assertGreaterThanOrEqual(0, $heard['latency_ms']);
        $this->assertLessThan(2000, $heard['latency_ms']);
        $this->assertFalse((bool) $heard['call']->recording);
        $status = app(MaiVoiceStatus::class)->snapshot();
        $this->assertFalse($status['recording']);
        $this->assertFalse($status['customer_voice']);
        $this->assertFalse($status['live_number_connected']);
        $this->assertSame('stub', $status['provider']);
        $this->assertArrayNotHasKey('transfer_number', $status);
        $this->assertArrayNotHasKey('api_key', $status);
    }

    public function test_admin_switch_is_limited_to_platform_roles()
    {
        $guest = $this->get('/admin/mai-voice');
        $this->assertTrue(in_array($guest->status(), [302, 401, 403], true));

        $company = new User();
        $company->role_id = 5;
        $this->be($company);
        $denied = app(MaiVoiceController::class)->switchVoice(Request::create('/admin/mai-voice/switch', 'POST', ['enabled' => '1']));
        $this->assertSame(403, $denied->getStatusCode());

        $admin = new User();
        $admin->role_id = 2;
        $this->be($admin);
        app(MaiVoiceController::class)->switchVoice(Request::create('/admin/mai-voice/switch', 'POST', ['enabled' => '0']));
        $this->assertFalse(MaiVoiceSwitch::open());
    }

    public function test_whatsapp_and_website_channels_stay_separate()
    {
        $website = new WhatsAppConversation();
        $website->channel = 'website';
        $this->assertTrue($website->isWebsite());
        $this->assertSame('Website', $website->channelLabel());
        $this->assertSame('website', $website->assistantChannel());

        $whatsapp = new WhatsAppConversation();
        $whatsapp->channel = 'whatsapp';
        $this->assertSame('WhatsApp', $whatsapp->channelLabel());
        $this->assertSame('whatsapp', $whatsapp->assistantChannel());

        $voice = new WhatsAppConversation();
        $voice->channel = 'voice';
        $this->assertTrue($voice->isVoice());
        $this->assertSame('Voice', $voice->channelLabel());

        $processor = file_get_contents(app_path('Services/WhatsApp/WhatsAppWebhookProcessor.php'));
        $this->assertStringContainsString('function recordCall', $processor);
        $this->assertStringNotContainsString('VoiceGateway', $processor);
    }

    protected function on()
    {
        MaiVoiceSwitch::set(true);
    }

    protected function connection(array $extra = [])
    {
        return VoiceConnection::create(array_merge([
            'cloud_tenant_id' => 1,
            'tenant_type' => 'INTERNAL',
            'called_number' => '237670000222',
            'provider' => 'stub',
            'status' => 'ACTIVE',
            'ai_enabled' => true,
            'company_name' => 'BeyondTechWorld',
            'assistant_name' => 'MAI',
            'language' => 'en',
            'transfer_number' => '237699111222',
        ], $extra));
    }

    protected function answer($caller = '237670000111')
    {
        return app(VoiceGateway::class)->incoming('237670000222', $caller, 'call-'.$caller.'-'.VoiceCall::count());
    }

    protected function contacts()
    {
        Schema::create('whatsapp_contacts', function ($table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 32)->nullable();
            $table->string('display_phone', 48)->nullable();
            $table->string('wa_name', 191)->nullable();
            $table->string('call_name', 80)->nullable();
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->timestamps();
        });
    }

    protected function conversations()
    {
        if (! Schema::hasTable('whatsapp_contacts')) {
            $this->contacts();
        }
        Schema::create('whatsapp_conversations', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_id');
            $table->string('mode', 16)->default('AI');
            $table->string('status', 32)->default('OPEN');
            $table->string('channel', 32)->nullable();
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('first_message')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    protected function assistantLines(VoiceCall $call)
    {
        $lines = [];
        foreach ($call->transcriptArray() as $row) {
            if (isset($row['role']) && $row['role'] === 'assistant') {
                $lines[] = $row['text'];
            }
        }

        return $lines;
    }
}
