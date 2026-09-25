<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Product;
use App\Quotation;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\WhatsApp\ConversationAiSwitchService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\WhatsAppCallRequest;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\WhatsAppHubTestCase;

class WhatsAppConversationalTest extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        WhatsAppSetting::putValue('ai_first', '0');
    }

    public function test_greeting_asks_name_without_a_menu()
    {
        $this->postWebhook($this->incoming('237650100001', 'Hi', 'C1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('May I know your name?', $out->body);
        $this->assertStringNotContainsString('1.', $out->body);
        $this->assertStringNotContainsString('select an intent', strtolower($out->body));
    }

    public function test_name_and_organization_stay_on_the_lead()
    {
        $this->postWebhook($this->incoming('237650100002', 'Hello', 'C2A'))->assertStatus(200);
        $this->postWebhook($this->incoming('237650100002', 'Daniel', 'C2B'))->assertStatus(200);
        $this->postWebhook($this->incoming('237650100002', 'Grace Church', 'C2C'))->assertStatus(200);
        $lead = Lead::orderByDesc('id')->first();
        $this->assertSame('Daniel', $lead->name);
        $this->assertSame('Grace Church', $lead->company);
        $this->assertSame(0, Customer::count());
    }

    public function test_multi_turn_memory_is_sent_to_the_model()
    {
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'A wedding for 500 people in Douala on 10 October can use our sound team. What equipment should I check?'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);
        $this->postWebhook($this->incoming('237650100003', 'We have a wedding for 500 people in Douala on 10 October', 'C3'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('equipment', strtolower($out->body));
        $memory = \App\Assistant\AssistantMemory::first();
        $params = $memory->parameters();
        $this->assertSame('wedding', $params['event_type']);
        $this->assertSame(500, (int) $params['guests']);
    }

    public function test_search_tool_returns_only_catalogue_names()
    {
        $this->ensureProduct();
        $fake = new NullAiProvider();
        $fake->scripted = [
            ['reply' => 'I will check the catalogue.', 'tool' => 'search_rental_products', 'tool_params' => ['query' => 'mixer']],
            ['reply' => 'We have a Mixer at 18000. That price is from the catalogue.'],
        ];
        $this->app->instance(AiProviderInterface::class, $fake);
        $this->postWebhook($this->incoming('237650100004', 'What mixers do you have?', 'C4'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('Mixer', $out->body);
        $this->assertStringContainsString('18000', $out->body);
    }

    public function test_old_quotation_is_limited_to_the_owner()
    {
        $this->ensureProduct();
        $owner = Customer::create(['name' => 'Owner', 'phone_number' => '237650100005', 'is_active' => true]);
        $other = Customer::create(['name' => 'Other', 'phone_number' => '237650100099', 'is_active' => true]);
        $product = Product::first();
        $mine = Quotation::create(['reference_no' => 'Q-MINE', 'customer_id' => $owner->id, 'quotation_status' => 1, 'grand_total' => 1000]);
        $theirs = Quotation::create(['reference_no' => 'Q-OTHER', 'customer_id' => $other->id, 'quotation_status' => 1, 'grand_total' => 9999]);
        DB::table('product_quotation')->insert([
            'quotation_id' => $mine->id,
            'product_id' => $product->id,
            'qty' => 2,
            'net_unit_price' => 1000,
            'total' => 2000,
        ]);
        $result = app(AssistantToolExecutor::class)->execute('get_customer_quotation_details', [], [
            'roles' => ['customer'],
            'customer_id' => $owner->id,
        ]);
        $this->assertTrue($result['success']);
        $this->assertSame('Q-MINE', $result['reference']);
        $this->assertStringContainsString('historical', $result['message']);
        $this->assertStringContainsString('18000', $result['message']);
        $this->assertStringNotContainsString('Q-OTHER', $result['message']);
        $this->assertNotSame($theirs->id, $result['id']);
    }

    public function test_human_request_and_manual_takeover_silence_ai()
    {
        $this->postWebhook($this->incoming('237650100006', 'I want a person', 'C6'))->assertStatus(200);
        $conversation = WhatsAppConversation::first();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $conversation->mode);
        $staff = User::create([
            'name' => 'Agent',
            'email' => 'agent@example.test',
            'password' => Hash::make('secret'),
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        WhatsAppSetting::putValue('default_handover_user_id', (string) $staff->id);
        $this->postWebhook($this->incoming('237650100007', 'Hi', 'C7A'))->assertStatus(200);
        $second = WhatsAppConversation::orderByDesc('id')->first();
        $before = WhatsAppMessage::where('conversation_id', $second->id)->where('sender_type', 'ASSISTANT')->count();
        app(WhatsAppConversationService::class)->reply($second, 'I have this from here.', $staff->id);
        $second = $second->fresh();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $second->mode);
        $this->assertSame($staff->id, (int) $second->assigned_user_id);
        $this->assertNotNull(WhatsAppConversationEvent::where('body', 'HUMAN_TAKEOVER_BY_REPLY')->first());
        $this->postWebhook($this->incoming('237650100007', 'Still there?', 'C7B'))->assertStatus(200);
        $this->postWebhook($this->incoming('237650100007', 'Hello again', 'C7C'))->assertStatus(200);
        $this->postWebhook($this->incoming('237650100007', 'Anyone?', 'C7D'))->assertStatus(200);
        $after = WhatsAppMessage::where('conversation_id', $second->id)->where('sender_type', 'ASSISTANT')->count();
        $this->assertSame($before, $after);
    }

    public function test_return_to_ai_keeps_the_note_internal()
    {
        $this->postWebhook($this->incoming('237650100008', 'Hi', 'C8'))->assertStatus(200);
        $conversation = WhatsAppConversation::first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();
        app(WhatsAppConversationService::class)->addNote($conversation, 'Handed back after the quote review.', 1);
        app(WhatsAppConversationService::class)->enableAi($conversation, 1);
        $this->assertSame(WhatsAppConversation::MODE_AI, $conversation->fresh()->mode);
        $this->assertNull(WhatsAppMessage::where('body', 'like', '%Handed back%')->first());
    }

    public function test_call_request_does_not_claim_a_call_was_placed()
    {
        $this->postWebhook($this->incoming('237650100009', 'Call me', 'C9'))->assertStatus(200);
        $row = WhatsAppCallRequest::first();
        $this->assertSame(WhatsAppCallRequest::REQUESTED, $row->status);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('have not called', $out->body);
    }

    public function test_stale_ai_reply_is_discarded()
    {
        $this->postWebhook($this->incoming('237650100010', 'Hi', 'C10'))->assertStatus(200);
        $conversation = WhatsAppConversation::first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();
        $before = WhatsAppMessage::where('sender_type', 'ASSISTANT')->count();
        $result = app(WhatsAppConversationService::class)->assistantReply($conversation, 'This should not send.');
        $this->assertTrue($result['discarded']);
        $this->assertSame($before, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
    }

    public function test_provider_failure_hands_over_without_inventing()
    {
        $fake = new NullAiProvider();
        $fake->fail = true;
        $this->app->instance(AiProviderInterface::class, $fake);
        $this->postWebhook($this->incoming('237650100011', 'Could you walk me through a site survey?', 'C11'))->assertStatus(200);
        $this->assertSame(1, WhatsAppMessage::where('direction', 'INCOMING')->count());
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('passed this to our team', $out->body);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, WhatsAppConversation::first()->mode);
    }

    public function test_checkout_and_otp_win_over_chat()
    {
        $router = app(AssistantIntentRouter::class);
        $checkout = $router->deterministic('CHECK OUT', ['bill_pending' => 1]);
        $this->assertSame('ATTENDANCE_OUT', $checkout['intent']);
        $otp = $router->deterministic('482913', ['verification_pending' => 1]);
        $this->assertSame('VERIFY_OTP', $otp['intent']);
    }

    public function test_ai_first_does_not_rewrite_owned_chats()
    {
        WhatsAppSetting::putValue('default_conversation_mode', 'HUMAN');
        WhatsAppSetting::putValue('ai_first', '0');
        $this->postWebhook($this->incoming('237650100012', 'Hi', 'C12'))->assertStatus(200);
        $existing = WhatsAppConversation::first();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $existing->mode);
        $existing->assigned_user_id = 1;
        $existing->save();
        WhatsAppSetting::putValue('ai_first', '1');
        $this->postWebhook($this->incoming('237650100013', 'Hi', 'C13'))->assertStatus(200);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $existing->fresh()->mode);
        $created = WhatsAppConversation::orderByDesc('id')->first();
        $this->assertSame(WhatsAppConversation::MODE_AI, $created->mode);
        $preview = app(ConversationAiSwitchService::class)->preview();
        $this->assertGreaterThan(0, $preview['excluded']['assigned']);
        app(ConversationAiSwitchService::class)->switchEligible(1);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $existing->fresh()->mode);
    }

    protected function ensureProduct()
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->decimal('qty', 12, 2)->default(0);
                $table->decimal('price', 12, 2)->nullable();
                $table->decimal('rent_price_per_day', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function ($table) {
                $table->increments('id');
                $table->string('reference_no')->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->decimal('grand_total', 12, 2)->nullable();
                $table->integer('quotation_status')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('product_quotation')) {
            Schema::create('product_quotation', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id');
                $table->unsignedInteger('product_id');
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('net_unit_price', 12, 2)->nullable();
                $table->decimal('total', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Product::where('name', 'Mixer')->exists()) {
            Product::create([
                'name' => 'Mixer',
                'code' => 'MIX1',
                'is_active' => true,
                'qty' => 3,
                'price' => 18000,
                'rent_price_per_day' => 18000,
            ]);
        }
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
                return ['success' => true, 'msg_id' => 'CT'.uniqid()];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'CD'];
            }

            public function sendImage($phone, $localPath, $caption = null)
            {
                return ['success' => true];
            }

            public function sessionStatus()
            {
                return ['connected' => true, 'status' => 'connected', 'session_name' => 'test'];
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
                return ['success' => true, 'msg_id' => 'G'.uniqid()];
            }
        };
    }
}
