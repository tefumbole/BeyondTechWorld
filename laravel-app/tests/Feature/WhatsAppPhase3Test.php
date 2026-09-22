<?php

namespace Tests\Feature;

use App\Assistant\AssistantActivity;
use App\Assistant\IntentCatalog;
use App\Booking;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Product;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantPolicyService;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\BeyondAssistantService;
use App\Services\Assistant\Providers\NullAiProvider;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase3Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        $this->extraTables();
    }

    public function test_disabled_assistant_does_not_reply()
    {
        config(['assistant.enabled' => false]);
        WhatsAppSetting::putValue('assistant_enabled', '0');
        $this->postWebhook($this->incomingText('+237675300001', 'Hello', 'A3D1'))->assertStatus(200);
        $this->assertSame(0, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
    }

    public function test_human_and_paused_modes_stay_silent()
    {
        $this->enableAssistant();
        $this->postWebhook($this->incomingText('+237675300002', 'Hello', 'A3H1'))->assertStatus(200);
        $c = WhatsAppConversation::first();
        $c->mode = WhatsAppConversation::MODE_HUMAN;
        $c->save();
        $msg = WhatsAppMessage::where('direction', 'INCOMING')->first();
        $this->assertTrue(app(BeyondAssistantService::class)->handleIncoming($msg)['skipped']);
        $c->mode = WhatsAppConversation::MODE_PAUSED;
        $c->save();
        $this->assertTrue(app(BeyondAssistantService::class)->handleIncoming($msg)['skipped']);
    }

    public function test_greeting_in_ai_mode()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300003', 'Hello', 'A3G1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('Beyond Assistant', $out->body);
    }

    public function test_company_and_service_enquiry()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300004', 'What services do you offer?', 'A3S1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertNotSame('', trim($out->body));
    }

    public function test_rental_clarification_and_memory()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300005', 'I need sound for my wedding.', 'A3R1'))->assertStatus(200);
        $this->assertGreaterThan(0, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
        $this->postWebhook($this->incomingText('+237675300005', 'Saturday, about 500 guests', 'A3R2'))->assertStatus(200);
        $mem = \App\Assistant\AssistantMemory::first();
        $this->assertSame('wedding', $mem->parameters()['event_type']);
        $this->assertSame('saturday', $mem->parameters()['event_date']);
        $this->assertSame(500, $mem->parameters()['guests']);
    }

    public function test_product_search_does_not_claim_date_availability()
    {
        Product::create(['name' => 'JBL Speaker', 'code' => 'JBL1', 'is_active' => true, 'rent_price_per_day' => 25000]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300006', 'Do you have JBL speakers?', 'A3P1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('not a confirmed booking', strtolower($out->body));
    }

    public function test_booking_status_for_known_customer()
    {
        Customer::create(['name' => 'Book Cust', 'phone_number' => '675300007', 'is_active' => true]);
        $cust = Customer::where('phone_number', '237675300007')->first() ?: Customer::where('phone_number', '675300007')->first();
        Booking::create(['reference_no' => 'BK-100', 'customer_id' => $cust->id, 'booking_status' => 'Pending', 'payment_status' => 'Due']);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300007', 'What is the status of my booking?', 'A3B1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('BK-100', $out->body);
    }

    public function test_ambiguous_bookings_ask_which()
    {
        Customer::create(['name' => 'Two Books', 'phone_number' => '675300008', 'is_active' => true]);
        $cust = Customer::orderByDesc('id')->first();
        Booking::create(['reference_no' => 'BK-1', 'customer_id' => $cust->id, 'booking_status' => 'Pending']);
        Booking::create(['reference_no' => 'BK-2', 'customer_id' => $cust->id, 'booking_status' => 'Confirmed']);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300008', 'What is the status of my booking?', 'A3B2'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertStringContainsString('more than one booking', strtolower($out->body));
    }

    public function test_internship_current_task()
    {
        $user = User::create([
            'name' => 'Intern A', 'email' => 'inta@example.test', 'password' => bcrypt('x'),
            'phone' => '675300009', 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $en = InternshipEnrolment::create(['student_user_id' => $user->id, 'status' => 'active']);
        if (Schema::hasTable('internship_task_assignments')) {
            InternshipTaskAssignment::create(['enrolment_id' => $en->id, 'status' => 'released']);
        }
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300009', 'What is my current task?', 'A3I1'))->assertStatus(200);
        $this->assertGreaterThan(0, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
    }

    public function test_sensitive_balance_is_not_disclosed()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300010', 'How much do I owe?', 'A3F1'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringNotContainsString('500', $out->body);
        $this->assertTrue(stripos($out->body, 'cannot confirm') !== false || stripos($out->body, 'team member') !== false);
    }

    public function test_customer_claimed_payment_is_not_confirmed()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300011', 'I paid 500,000 CFA today. Confirm my payment.', 'A3F2'))->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertFalse(stripos($out->body, 'confirmed') !== false && stripos($out->body, 'received') !== false);
        $this->assertTrue(stripos($out->body, 'cannot confirm') !== false || stripos($out->body, 'verify') !== false || stripos($out->body, 'team') !== false);
    }

    public function test_human_request_hands_over()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300012', 'I want to speak with someone', 'A3U1'))->assertStatus(200);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, WhatsAppConversation::first()->mode);
        $this->assertSame(AssistantActivity::HANDED_OVER, AssistantActivity::first()->status);
    }

    public function test_low_confidence_hands_over()
    {
        $decision = app(AssistantPolicyService::class)->decide([
            'intent' => IntentCatalog::UNKNOWN,
            'confidence' => 0.1,
            'requires_erp' => false,
        ], []);
        $this->assertSame(IntentCatalog::ACTION_HANDOVER, $decision['action']);
    }

    public function test_unauthorized_payment_tool_is_blocked()
    {
        $result = app(AssistantToolExecutor::class)->execute('get_customer_payment_summary', [], ['roles' => ['customer'], 'customer_id' => 1]);
        $this->assertFalse($result['success']);
        $this->assertSame('policy_blocked', $result['error']);
    }

    public function test_unknown_tool_rejected()
    {
        $result = app(AssistantToolExecutor::class)->execute('drop_database', [], ['roles' => []]);
        $this->assertFalse($result['success']);
        $this->assertSame('unknown_tool', $result['error']);
    }

    public function test_provider_failure_keeps_inbound()
    {
        $fake = new NullAiProvider();
        $fake->fail = true;
        $this->app->instance(AiProviderInterface::class, $fake);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675300013', 'Hello', 'A3X1'))->assertStatus(200);
        $this->assertSame(1, WhatsAppMessage::where('direction', 'INCOMING')->count());
    }

    public function test_duplicate_inbound_does_not_double_reply()
    {
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $payload = $this->incomingText('+237675300014', 'Hello', 'A3DUP');
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
    }

    public function test_diagnostics_do_not_show_ai_key()
    {
        config(['assistant.api_key' => 'sk-secret-should-not-leak']);
        $diag = app(\App\Services\WhatsApp\WhatsAppHubQuery::class)->diagnostics();
        $this->assertStringNotContainsString('sk-secret-should-not-leak', json_encode($diag));
        $this->assertSame('Configured', $diag['assistant_provider']);
    }

    public function test_suggested_reply_does_not_send()
    {
        $this->enableAssistant();
        $this->postWebhook($this->incomingText('+237675300015', 'Hello', 'A3SUG'))->assertStatus(200);
        $c = WhatsAppConversation::first();
        $c->mode = WhatsAppConversation::MODE_HUMAN;
        $c->save();
        $before = WhatsAppMessage::where('direction', 'OUTGOING')->count();
        $draft = app(BeyondAssistantService::class)->suggest($c);
        $this->assertNotSame('', trim($draft));
        $this->assertSame($before, WhatsAppMessage::where('direction', 'OUTGOING')->count());
    }

    public function test_intent_router_human_and_greeting()
    {
        $router = app(AssistantIntentRouter::class);
        $this->assertSame(IntentCatalog::HUMAN_REQUEST, $router->deterministic('Can I speak with staff?')['intent']);
        $this->assertSame(IntentCatalog::GREETING, $router->deterministic('Hello')['intent']);
    }

    protected function enableAssistant()
    {
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
    }

    protected function extraTables()
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->decimal('rent_price_per_day', 12, 2)->nullable();
                $table->decimal('rent_price_per_hour', 12, 2)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('reference_no')->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('booking_status')->nullable();
                $table->string('payment_status')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('reference_no')->nullable();
                $table->unsignedInteger('customer_id')->nullable();
                $table->integer('quotation_status')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_task_assignments')) {
            Schema::create('internship_task_assignments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('enrolment_id')->nullable();
                $table->unsignedInteger('program_task_id')->nullable();
                $table->string('status')->nullable();
                $table->date('scheduled_work_date')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('customer_id')->nullable();
                $table->decimal('grand_total', 12, 2)->nullable();
                $table->decimal('paid_amount', 12, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    protected function incomingText($phone, $body, $id)
    {
        $digits = preg_replace('/\D/', '', $phone);

        return [
            'event' => 'messages.received',
            'timestamp' => time(),
            'data' => [
                'messages' => [
                    'key' => [
                        'id' => $id,
                        'fromMe' => false,
                        'remoteJid' => $digits.'@s.whatsapp.net',
                        'cleanedSenderPn' => $digits,
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
            public $sent = [];

            public function sendText($phone, $message)
            {
                $this->sent[] = [$phone, $message];

                return ['success' => true, 'msg_id' => 'AI'.count($this->sent)];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true];
            }

            public function sendImage($phone, $localPath, $caption = null)
            {
                return ['success' => true];
            }

            public function sessionStatus()
            {
                return ['connected' => true, 'status' => 'CONNECTED', 'session_name' => 'test', 'configured' => true];
            }

            public function isConfigured()
            {
                return true;
            }
        };
    }
}
