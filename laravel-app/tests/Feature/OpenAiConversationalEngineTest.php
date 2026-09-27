<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\Assistant\Providers\NullAiProvider;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Tests\WhatsAppHubTestCase;

class OpenAiConversationalEngineTest extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        WhatsAppSetting::putValue('ai_first', '0');
        WhatsAppSetting::putValue('assistant_collect_name', '0');
    }

    public function test_casual_conversation_never_handovers()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'Hello! How can I help you today?'],
            ['reply' => 'I am doing well, thank you for asking. How can Beyond help you?'],
            ['reply' => 'Not much — ready when you are. What do you need?'],
            ['reply' => 'Beyond is ready to help with rentals, training, and IT. What would you like to explore?'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $phone = '237650900001';
        foreach (['Hello', 'How are you today?', "What's up?", 'How is Beyond today?'] as $i => $body) {
            $this->postWebhook($this->incoming($phone, $body, 'OC'.$i))->assertStatus(200);
        }

        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $this->assertNotNull($conversation);
        $this->assertSame(WhatsAppConversation::MODE_AI, $conversation->mode);
        $this->assertSame(0, WhatsAppConversation::where('mode', WhatsAppConversation::MODE_HUMAN)->count());

        $assistantMsgs = WhatsAppMessage::where('sender_type', 'ASSISTANT')->where('direction', 'OUTGOING')->count();
        $this->assertGreaterThanOrEqual(4, $assistantMsgs);
    }

    public function test_unseen_paraphrase_stays_on_ai()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'Hope your day is going smoothly — what can I help with at Beyond?'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $this->postWebhook($this->incoming('237650900002', 'Hope you are having a lovely afternoon!', 'UP1'))->assertStatus(200);
        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $this->assertSame(WhatsAppConversation::MODE_AI, $conversation->mode);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('Beyond', $out->body);
    }

    public function test_explicit_human_request_hands_over_then_silences_ai()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'Hi there!'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $phone = '237650900003';
        $this->postWebhook($this->incoming($phone, 'Hello', 'H1'))->assertStatus(200);
        $this->postWebhook($this->incoming($phone, 'I want to speak to a person', 'H2'))->assertStatus(200);

        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $conversation->mode);

        $before = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->postWebhook($this->incoming($phone, 'Are you still there?', 'H3'))->assertStatus(200);
        $after = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $this->assertSame($before, $after);
    }

    public function test_unknown_policy_does_not_handover()
    {
        $decision = app(\App\Services\Assistant\AssistantPolicyService::class)->decide([
            'intent' => \App\Assistant\IntentCatalog::UNKNOWN,
            'confidence' => 0.2,
            'requires_erp' => false,
            'needs_clarification' => true,
            'slots' => [],
        ], []);
        $this->assertSame(\App\Assistant\IntentCatalog::ACTION_ANSWER, $decision['action']);
        $this->assertNotSame(\App\Assistant\IntentCatalog::ACTION_HANDOVER, $decision['action']);
    }

    public function test_general_av_question_gets_openai_direct_not_business_menu()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['content' => 'A line array stacks speakers to control vertical coverage for large audiences; a point source radiates from one point and suits smaller rooms.'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);

        $this->postWebhook($this->incoming(
            '237650900010',
            'what is the difference between line array and point source speaker',
            'LA1'
        ))->assertStatus(200);

        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('line array', strtolower($out->body));
        $this->assertStringNotContainsString('equipment rental', strtolower($out->body));
        $this->assertStringNotContainsString('training/internship', strtolower($out->body));
        $this->assertStringNotContainsString('or something else', strtolower($out->body));

        $diag = \Illuminate\Support\Facades\Cache::get('assistant_last_turn_diag');
        $this->assertNotNull($diag);
        $this->assertSame('OPENAI_DIRECT', $diag['response_source']);
        $this->assertSame('auto', $diag['tool_choice']);
    }

    public function test_missing_openai_key_does_not_show_business_menu()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [];
        $this->app->instance(AiProviderInterface::class, $fake);

        $this->postWebhook($this->incoming(
            '237650900011',
            'Explain gain before feedback briefly',
            'GB1'
        ))->assertStatus(200);

        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertNotNull($out);
        $this->assertStringNotContainsString('equipment rental', strtolower($out->body));
        $this->assertStringNotContainsString('training/internship', strtolower($out->body));
        $diag = \Illuminate\Support\Facades\Cache::get('assistant_last_turn_diag');
        $this->assertSame('FALLBACK_ERROR', $diag['response_source']);
    }

    protected function incoming($phone, $body, $id)
    {
        return [
            'event' => 'messages.received',
            'timestamp' => time(),
            'data' => [
                'messages' => [
                    'key' => [
                        'id' => $id,
                        'fromMe' => false,
                        'remoteJid' => $phone.'@s.whatsapp.net',
                        'cleanedSenderPn' => $phone,
                    ],
                    'messageBody' => $body,
                    'message' => ['conversation' => $body],
                ],
            ],
        ];
    }

    protected function fakeProvider()
    {
        return new class implements WhatsAppProviderInterface {
            public function sendText($phone, $message)
            {
                return ['success' => true, 'msg_id' => 'OA'.uniqid()];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'OD'];
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
                return ['success' => true, 'msg_id' => 'OG'];
            }
        };
    }
}
