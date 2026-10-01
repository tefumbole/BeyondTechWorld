<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\MobileMoneyHolderService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;
use Tests\WhatsAppHubTestCase;

class WebsiteChatTest extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        $this->app->instance(MobileMoneyHolderService::class, $this->fakeMomo(null));
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('website_ai_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        WhatsAppSetting::putValue('ai_first', '0');
        Cache::flush();
    }

    public function test_session_asks_for_phone_first()
    {
        $first = $this->postJson('/api/website-chat/session', ['path' => '/rentals'])->assertStatus(200)->json();
        $this->assertTrue($first['success']);
        $this->assertSame('need_phone', $first['onboarding']);

        $poll = $this->getJson('/api/website-chat/messages?token='.$first['token'].'&after=0')->assertStatus(200)->json();
        // Phone is collected by the widget form — no chat prompt is seeded.
        $this->assertSame([], $poll['messages']);
    }

    public function test_cameroon_phone_uses_campay_name_not_system()
    {
        Customer::create([
            'name' => 'System Only Name',
            'phone_number' => '237675000111',
            'is_active' => true,
        ]);
        $this->app->instance(MobileMoneyHolderService::class, $this->fakeMomo('JEAN PAUL KAMGA'));

        $session = $this->postJson('/api/website-chat/session')->json();
        $res = $this->verifyPhone($session['token'], '675000111');

        $this->assertSame('ready', $res['onboarding']);
        $this->assertTrue($res['identified']['system_associated']);
        $this->assertSame('System Only Name', $res['identified']['system_name']);
        $this->assertTrue(collect($res['messages'])->contains(function ($m) {
            return $m['role'] === 'assistant' && stripos($m['body'], 'Hi Jean') !== false;
        }));
        $this->assertFalse(collect($res['messages'])->contains(function ($m) {
            return stripos($m['body'], 'System') !== false;
        }));
    }

    public function test_unknown_phone_asks_for_name_then_greets()
    {
        $this->app->instance(MobileMoneyHolderService::class, $this->fakeMomo(null));
        $session = $this->postJson('/api/website-chat/session')->json();

        $phoneTurn = $this->verifyPhone($session['token'], '+237675999888');
        $this->assertSame('need_name', $phoneTurn['onboarding']);
        $this->assertTrue(collect($phoneTurn['messages'])->contains(function ($m) {
            return $m['role'] === 'assistant' && stripos($m['body'], 'name') !== false;
        }));

        $nameTurn = $this->postJson('/api/website-chat/messages', [
            'token' => $session['token'],
            'body' => 'Marie Claire',
        ])->assertStatus(200)->json();
        $this->assertSame('ready', $nameTurn['onboarding']);
        $this->assertTrue(collect($nameTurn['messages'])->contains(function ($m) {
            return $m['role'] === 'assistant' && stripos($m['body'], 'Hi Marie') !== false;
        }));
    }

    public function test_session_create_and_resume_same_token()
    {
        $first = $this->postJson('/api/website-chat/session', ['path' => '/rentals'])->assertStatus(200)->json();
        $this->assertTrue($first['success']);
        $this->assertNotEmpty($first['token']);
        $token = $first['token'];
        $id = $first['conversation_id'];

        $second = $this->postJson('/api/website-chat/session', ['token' => $token, 'path' => '/about'])->assertStatus(200)->json();
        $this->assertTrue($second['success']);
        $this->assertSame($token, $second['token']);
        $this->assertSame($id, $second['conversation_id']);
        $this->assertSame(1, WhatsAppConversation::where('channel', 'website')->count());
    }

    public function test_visitor_message_runs_assistant_synchronously()
    {
        $token = $this->completeOnboarding('237675111222', 'Ada Lovelace');
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'We can help with sound rental. What date do you need?'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $res = $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'I need sound for a wedding',
        ])->assertStatus(200)->json();

        $this->assertTrue($res['success']);
        $assistantBodies = collect($res['messages'])->where('role', 'assistant')->pluck('body');
        $this->assertTrue($assistantBodies->contains(function ($b) {
            return stripos($b, 'sound rental') !== false || stripos($b, 'wedding') !== false;
        }));
        $this->assertNotNull(WhatsAppMessage::where('provider_message_id', 'like', 'webmsg:%')->first());
    }

    public function test_website_lead_source_and_no_wasender()
    {
        $provider = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $provider);
        $token = $this->completeOnboarding('237675333444', 'Lead Tester');
        $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'Looking for LED screens for my church event next month',
        ])->assertStatus(200);

        $lead = Lead::orderByDesc('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame(LeadCatalog::SOURCE_WEBSITE, $lead->source);
        $this->assertSame(0, count($provider->sent));
    }

    public function test_human_handover_keeps_ai_silent()
    {
        $token = $this->completeOnboarding('237675555666', 'Handover User');
        $conversation = WhatsAppConversation::where('session_token', $token)->first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();

        $before = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'Please connect me to a human now',
        ])->assertStatus(200);
        $after = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->assertSame($before, $after);
    }

    public function test_staff_reply_stores_for_poll_without_provider()
    {
        $provider = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $provider);
        $token = $this->completeOnboarding('237675777888', 'Staff Poll');
        $conversation = WhatsAppConversation::where('session_token', $token)->first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();

        $user = $this->makeUser(1);
        $result = app(WhatsAppConversationService::class)->reply($conversation, 'Hello from staff', $user->id);
        $this->assertTrue($result['success']);
        $this->assertSame(0, count($provider->sent));

        $poll = $this->getJson('/api/website-chat/messages?token='.$token.'&after=0')->assertStatus(200)->json();
        $this->assertTrue(collect($poll['messages'])->contains(function ($m) {
            return $m['role'] === 'staff' && strpos($m['body'], 'Hello from staff') !== false;
        }));
    }

    public function test_return_to_ai_resumes()
    {
        $token = $this->completeOnboarding('237675888999', 'Resume User');
        $conversation = WhatsAppConversation::where('session_token', $token)->first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();
        app(WhatsAppConversationService::class)->enableAi($conversation);

        $fake = new NullAiProvider();
        $fake->scripted = [['reply' => 'Welcome back — how can I help?']];
        $this->app->instance(AiProviderInterface::class, $fake);

        $res = $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'Hi again',
        ])->assertStatus(200)->json();
        $this->assertTrue(collect($res['messages'])->contains(function ($m) {
            return $m['role'] === 'assistant' && stripos($m['body'], 'Welcome back') !== false;
        }));
    }

    public function test_rate_limit_on_messages()
    {
        $session = $this->postJson('/api/website-chat/session')->json();
        $token = $session['token'];
        Cache::put('website_chat_msg:127.0.0.1', 30, now()->addMinute());
        $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'ping',
        ])->assertStatus(429);
    }

    public function test_refresh_same_token_does_not_duplicate_conversation()
    {
        $token = $this->completeOnboarding('237675121314', 'Refresh User');
        $leadsBefore = Lead::count();
        $convBefore = WhatsAppConversation::where('channel', 'website')->count();

        $this->postJson('/api/website-chat/session', ['token' => $token])->assertStatus(200);
        $this->assertSame($convBefore, WhatsAppConversation::where('channel', 'website')->count());
        $this->assertSame($leadsBefore, Lead::count());
    }

    protected function completeOnboarding($phone, $name)
    {
        $this->app->instance(MobileMoneyHolderService::class, $this->fakeMomo($name));
        $session = $this->postJson('/api/website-chat/session')->json();
        $token = $session['token'];
        $local = preg_replace('/^237/', '', $phone);
        $res = $this->verifyPhone($token, $local);
        $this->assertSame('ready', $res['onboarding']);

        return $token;
    }

    protected function verifyPhone($token, $body)
    {
        $otp = new class extends \App\Services\WhatsApp\WhatsAppVerificationService {
            public $last;

            public function issue($contactId, $conversationId, $identityType, $identityId, $purpose, $providerMessageId = null)
            {
                $result = parent::issue($contactId, $conversationId, $identityType, $identityId, $purpose, $providerMessageId);
                $this->last = $result;

                return $result;
            }
        };
        $this->app->instance(\App\Services\WhatsApp\WhatsAppVerificationService::class, $otp);
        $sent = $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => $body,
        ])->assertStatus(200)->json();
        $this->assertSame('need_otp', $sent['onboarding']);
        $this->assertNotEmpty($otp->last['code']);

        return $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => $otp->last['code'],
        ])->assertStatus(200)->json();
    }

    protected function fakeMomo($name)
    {
        return new class($name) extends MobileMoneyHolderService {
            private $fixed;

            public function __construct($name)
            {
                $this->fixed = $name;
            }

            public function lookup($phone)
            {
                if ($this->fixed) {
                    return ['name' => $this->fixed, 'address' => null, 'source' => 'campay'];
                }

                return ['name' => null, 'address' => null, 'source' => null];
            }
        };
    }

    protected function fakeProvider()
    {
        return new class implements WhatsAppProviderInterface {
            public $sent = [];

            public function sendText($phone, $message)
            {
                $this->sent[] = ['to' => $phone, 'body' => $message];

                return ['success' => true, 'msg_id' => 'WEB'.uniqid()];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'WD'];
            }

            public function sendImage($phone, $localPath, $caption = null)
            {
                return ['success' => true];
            }

            public function sessionStatus()
            {
                return ['connected' => true, 'status' => 'connected', 'session_name' => 'test', 'configured' => true];
            }

            public function isConfigured()
            {
                return true;
            }

            public function listGroups()
            {
                return ['success' => true, 'groups' => []];
            }

            public function sendGroupText($groupJid, $message)
            {
                return ['success' => true, 'msg_id' => 'WG'];
            }
        };
    }
}
