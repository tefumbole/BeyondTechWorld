<?php

namespace Tests\Feature;

use App\Services\Voice\MaiVoiceSwitch;
use App\Services\Voice\TwilioWhatsAppVoiceProvider;
use App\Voice\VoiceCall;
use App\Voice\VoiceCallback;
use App\Voice\VoiceConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TwilioWhatsAppVoiceTest extends TestCase
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
            'voice.twilio.auth_token' => 'test-token-not-a-secret',
            'voice.twilio.realtime_bridge' => false,
            'voice.twilio.stream_url' => '',
            'voice.twilio.outbound_calls' => false,
            'voice.twilio.recording' => false,
            'voice.tool_runner' => null,
        ]);
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

    public function test_invalid_signature_is_rejected_and_creates_no_call()
    {
        $this->connection();
        MaiVoiceSwitch::set(true);
        $response = $this->post('/api/webhooks/twilio/whatsapp-voice', $this->payload(), [
            'X-Twilio-Signature' => 'not-a-signature',
        ]);
        $response->assertStatus(403);
        $this->assertSame(0, VoiceCall::count());
    }

    public function test_empty_token_fails_closed()
    {
        config(['voice.twilio.auth_token' => '']);
        $response = $this->post('/api/webhooks/twilio/whatsapp-voice', $this->payload(), [
            'X-Twilio-Signature' => $this->sign($this->payload()),
        ]);
        $response->assertStatus(403);
        $this->assertSame(0, VoiceCall::count());
    }

    public function test_valid_webhook_uses_the_called_sender_and_not_the_caller()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $this->contacts();
        DB::table('whatsapp_contacts')->insert([
            'normalized_phone' => '237670000111',
            'wa_name' => 'Other company person',
            'cloud_tenant_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response = $this->signedPost($this->payload());
        $response->assertStatus(200);
        $body = $response->getContent();
        $this->assertStringNotContainsString('<Record', $body);
        $this->assertStringNotContainsString('<Dial', $body);
        $this->assertStringContainsString('<Hangup/>', $body);
        $call = VoiceCall::first();
        $this->assertSame('twilio-whatsapp', $call->provider);
        $this->assertSame('CA123', $call->provider_call_id);
        $this->assertSame(1, (int) $call->cloud_tenant_id);
        $this->assertStringNotContainsString('Other company person', (string) $call->transcript);
        $this->assertFalse((bool) $call->recording);
        $this->assertFalse((bool) $call->identity_verified);
    }

    public function test_duplicate_call_sid_does_not_open_a_second_call()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $this->signedPost($this->payload())->assertStatus(200);
        $again = $this->signedPost($this->payload());
        $again->assertStatus(200);
        $this->assertStringContainsString('<Hangup/>', $again->getContent());
        $this->assertSame(1, VoiceCall::count());
    }

    public function test_kill_switch_and_customer_sender_do_not_start_a_stream()
    {
        $this->connection();
        MaiVoiceSwitch::set(false);
        $off = $this->signedPost($this->payload());
        $this->assertStringContainsString('<Reject/>', $off->getContent());
        $this->assertSame('voice_off', VoiceCall::first()->status_reason);

        $this->connection([
            'cloud_tenant_id' => 2,
            'tenant_type' => 'CUSTOMER',
            'called_number' => '237670000999',
        ]);
        MaiVoiceSwitch::set(true);
        $customer = $this->signedPost($this->payload('CA999', 'whatsapp:+237670000111', 'whatsapp:+237670000999'));
        $this->assertStringContainsString('<Reject/>', $customer->getContent());
        $this->assertSame('customer_voice_disabled', VoiceCall::orderBy('id', 'desc')->first()->status_reason);
    }

    public function test_a_normal_telephone_request_is_not_answered()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $response = $this->signedPost($this->payload('CAPSTN', '+237670000111', 'whatsapp:+237670000222'));
        $this->assertStringContainsString('<Reject/>', $response->getContent());
        $this->assertStringNotContainsString('<Dial', $response->getContent());
        $this->assertSame(0, VoiceCall::count());
    }

    public function test_known_caller_unknown_caller_and_multi_turn_event()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $this->contacts();
        DB::table('whatsapp_contacts')->insert([
            'normalized_phone' => '237670000111',
            'call_name' => 'Marie',
            'cloud_tenant_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->signedPost($this->payload());
        $call = VoiceCall::first();
        $this->assertStringContainsString('Welcome back, Marie', (string) $call->transcript);
        $this->assertFalse((bool) $call->identity_verified);

        $tools = [];
        config(['voice.tool_runner' => function ($name, $params) use (&$tools) {
            $tools[] = ['name' => $name, 'params' => $params];

            return ['ok' => true];
        }]);
        $gateway = $this->gateway();
        $first = $gateway->hear($call, 'I need sound for a wedding.');
        $second = $gateway->hear($first['call'], 'November 14');
        $third = $gateway->hear($second['call'], 'Bamenda');
        $this->assertStringContainsString('November 14', $third['speech']);
        $this->assertStringContainsString('wedding', $third['speech']);
        $this->assertContains('build_event_solution', array_column($tools, 'name'));
    }

    public function test_tools_callback_message_hangup_and_failures_do_not_call_twilio()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $called = [];
        config(['voice.tool_runner' => function ($name) use (&$called) {
            $called[] = $name;
            if ($name === 'search_rental_products') {
                return null;
            }

            return ['status' => 'draft'];
        }]);
        $this->signedPost($this->payload());
        $gateway = $this->gateway();
        $call = VoiceCall::first();
        $price = $gateway->hear($call, 'How much are the speakers?');
        $this->assertStringContainsString('authoritative price', $price['speech']);
        $human = $gateway->hear($price['call'], 'Can I speak with Roland?');
        $this->assertSame('FAILED', $human['call']->transfer_status);
        $this->assertStringContainsString('take a message', $human['speech']);
        $saved = $gateway->hear($human['call'], 'Please call me about the wedding');
        $this->assertSame('Please call me about the wedding', VoiceCallback::first()->message);

        $callback = $gateway->hear($saved['call'], 'Ask Roland to call me');
        $this->assertSame('Roland', VoiceCallback::orderBy('id', 'desc')->first()->requested_for);
        $this->assertStringNotContainsString('699111222', $callback['speech']);

        $ended = $gateway->hear($callback['call'], "That's all, goodbye");
        $this->assertSame('ENDED', $ended['call']->status);

        $provider = new TwilioWhatsAppVoiceProvider();
        $this->assertFalse($provider->transfer($ended['call'], '+237699111222')['ok']);
        $this->assertSame('pstn_not_allowed', $provider->transfer($ended['call'], '+237699111222')['reason']);
        $this->assertFalse($provider->placeOutbound('whatsapp:+237670000111')['ok']);
        $this->assertContains('search_rental_products', $called);
    }

    public function test_ai_failure_and_caller_hangup_stay_on_the_voice_call()
    {
        MaiVoiceSwitch::set(true);
        $this->connection(['transfer_number' => null]);
        $this->signedPost($this->payload());
        config(['voice.simulate_ai_failure' => true]);
        $heard = $this->gateway()->hear(VoiceCall::first(), 'Hello');
        $this->assertStringContainsString("can't continue", $heard['speech']);

        config(['voice.simulate_ai_failure' => false]);
        $status = $this->signedPost([
            'CallSid' => 'CA123',
            'CallStatus' => 'completed',
        ], '/api/webhooks/twilio/whatsapp-voice/status');
        $status->assertStatus(204);
        $this->assertSame('ENDED', VoiceCall::first()->fresh()->status);
        $this->assertSame('caller_hangup', VoiceCall::first()->status_reason);
    }

    public function test_realtime_bridge_stays_off_unless_explicitly_armed_and_never_records()
    {
        MaiVoiceSwitch::set(true);
        $this->connection();
        $off = $this->signedPost($this->payload());
        $this->assertStringNotContainsString('<Stream', $off->getContent());
        $this->assertStringNotContainsString('<Record', $off->getContent());

        config([
            'voice.twilio.realtime_bridge' => true,
            'voice.twilio.stream_url' => 'wss://example.test/mai-stream',
        ]);
        $on = $this->signedPost($this->payload('CASTREAM'));
        $this->assertStringContainsString('<Stream url="wss://example.test/mai-stream"/>', $on->getContent());
        $this->assertStringNotContainsString('<Record', $on->getContent());
        $this->assertStringNotContainsString('<Dial', $on->getContent());
    }

    protected function connection(array $extra = [])
    {
        return VoiceConnection::create(array_merge([
            'cloud_tenant_id' => 1,
            'tenant_type' => 'INTERNAL',
            'called_number' => '237670000222',
            'provider' => 'twilio-whatsapp',
            'status' => 'ACTIVE',
            'ai_enabled' => true,
            'company_name' => 'BeyondTechWorld',
            'assistant_name' => 'MAI',
            'language' => 'en',
            'transfer_number' => '237699111222',
        ], $extra));
    }

    protected function contacts()
    {
        if (Schema::hasTable('whatsapp_contacts')) {
            return;
        }
        Schema::create('whatsapp_contacts', function ($table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 32)->nullable();
            $table->string('wa_name', 191)->nullable();
            $table->string('call_name', 80)->nullable();
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->timestamps();
        });
    }

    protected function payload($sid = 'CA123', $from = 'whatsapp:+237670000111', $to = 'whatsapp:+237670000222')
    {
        return [
            'CallSid' => $sid,
            'From' => $from,
            'To' => $to,
            'CallStatus' => 'ringing',
        ];
    }

    protected function signedPost(array $params, $path = '/api/webhooks/twilio/whatsapp-voice')
    {
        return $this->post($path, $params, [
            'X-Twilio-Signature' => $this->sign($params, $path),
        ]);
    }

    protected function sign(array $params, $path = '/api/webhooks/twilio/whatsapp-voice')
    {
        ksort($params);
        $data = 'http://localhost'.$path;
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, 'test-token-not-a-secret', true));
    }

    protected function gateway()
    {
        return new \App\Services\Voice\VoiceGateway(
            new TwilioWhatsAppVoiceProvider(),
            app(\App\Services\Voice\VoiceScriptedTurn::class)
        );
    }
}
