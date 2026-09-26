<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Http\Middleware\VerifyCsrfToken;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
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
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('website_ai_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        WhatsAppSetting::putValue('ai_first', '0');
        Cache::flush();
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
        $session = $this->postJson('/api/website-chat/session')->json();
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'We can help with sound rental. What date do you need?'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $res = $this->postJson('/api/website-chat/messages', [
            'token' => $session['token'],
            'body' => 'I need sound for a wedding',
        ])->assertStatus(200)->json();

        $this->assertTrue($res['success']);
        $bodies = collect($res['messages'])->pluck('body')->implode(' ');
        $this->assertStringContainsString('wedding', strtolower($bodies));
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->where('direction', 'OUTGOING')->first();
        $this->assertNotNull($out);
        $this->assertStringStartsWith('webmsg:', (string) $out->provider_message_id);
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $out->status);
    }

    public function test_website_lead_source_and_no_wasender()
    {
        $provider = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $provider);
        $session = $this->postJson('/api/website-chat/session')->json();
        $this->postJson('/api/website-chat/messages', [
            'token' => $session['token'],
            'body' => 'Looking for LED screens for my church event next month',
        ])->assertStatus(200);

        $lead = Lead::orderByDesc('id')->first();
        $this->assertNotNull($lead);
        $this->assertSame(LeadCatalog::SOURCE_WEBSITE, $lead->source);
        $this->assertSame(0, count($provider->sent));
    }

    public function test_human_handover_keeps_ai_silent()
    {
        $session = $this->postJson('/api/website-chat/session')->json();
        $conversation = WhatsAppConversation::find($session['conversation_id']);
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();

        $before = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->postJson('/api/website-chat/messages', [
            'token' => $session['token'],
            'body' => 'Please connect me to a human now',
        ])->assertStatus(200);
        $after = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->assertSame($before, $after);
    }

    public function test_staff_reply_stores_for_poll_without_provider()
    {
        $provider = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $provider);
        $session = $this->postJson('/api/website-chat/session')->json();
        $conversation = WhatsAppConversation::find($session['conversation_id']);
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();

        $user = $this->makeUser(1);
        $result = app(WhatsAppConversationService::class)->reply($conversation, 'Hello from staff', $user->id);
        $this->assertTrue($result['success']);
        $this->assertSame(0, count($provider->sent));

        $poll = $this->getJson('/api/website-chat/messages?token='.$session['token'].'&after=0')->assertStatus(200)->json();
        $this->assertTrue(collect($poll['messages'])->contains(function ($m) {
            return $m['role'] === 'staff' && strpos($m['body'], 'Hello from staff') !== false;
        }));
    }

    public function test_return_to_ai_resumes()
    {
        $session = $this->postJson('/api/website-chat/session')->json();
        $conversation = WhatsAppConversation::find($session['conversation_id']);
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();
        app(WhatsAppConversationService::class)->enableAi($conversation);

        $fake = new NullAiProvider();
        $fake->scripted = [['reply' => 'Welcome back — how can I help?']];
        $this->app->instance(AiProviderInterface::class, $fake);

        $res = $this->postJson('/api/website-chat/messages', [
            'token' => $session['token'],
            'body' => 'Hi again',
        ])->assertStatus(200)->json();
        $this->assertTrue(collect($res['messages'])->contains(function ($m) {
            return $m['role'] === 'assistant';
        }));
    }

    public function test_rate_limit_on_messages()
    {
        $session = $this->postJson('/api/website-chat/session')->json();
        $token = $session['token'];
        // Exhaust cache-based controller limit (30/min) quickly via Cache pre-seed
        Cache::put('website_chat_msg:127.0.0.1', 30, now()->addMinute());
        $this->postJson('/api/website-chat/messages', [
            'token' => $token,
            'body' => 'ping',
        ])->assertStatus(429);
    }

    public function test_refresh_same_token_does_not_duplicate_conversation()
    {
        $session = $this->postJson('/api/website-chat/session')->json();
        $token = $session['token'];
        $this->postJson('/api/website-chat/messages', ['token' => $token, 'body' => 'Need a projector'])->assertStatus(200);
        $leadsBefore = Lead::count();
        $convBefore = WhatsAppConversation::where('channel', 'website')->count();

        $this->postJson('/api/website-chat/session', ['token' => $token])->assertStatus(200);
        $this->assertSame($convBefore, WhatsAppConversation::where('channel', 'website')->count());
        $this->assertSame($leadsBefore, Lead::count());
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
