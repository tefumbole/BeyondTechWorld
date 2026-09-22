<?php

namespace Tests\Feature;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppLeadService;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase2Test extends WhatsAppHubTestCase
{
    public function test_greeting_does_not_create_lead()
    {
        $this->postWebhook($this->incomingText('+237675100001', 'Hello', 'P2G1'))->assertStatus(200);
        $this->assertSame(0, Lead::count());
        $this->assertSame(1, WhatsAppConversation::count());
        $this->assertSame(WhatsAppConversation::STATUS_WAITING_STAFF, WhatsAppConversation::first()->status);
    }

    public function test_meaningful_unknown_enquiry_creates_lead()
    {
        $this->postWebhook($this->incomingText('+237675100002', 'I need sound and LED screens for a wedding', 'P2L1'))
            ->assertStatus(200);
        $this->assertSame(1, Lead::count());
        $lead = Lead::first();
        $this->assertSame(LeadCatalog::STATUS_NEW, $lead->status);
        $this->assertSame('237675100002', $lead->normalized_phone);
        $this->assertNotNull($lead->category);
        $this->assertStringContainsString('wedding', strtolower($lead->first_enquiry));
    }

    public function test_known_customer_does_not_auto_create_lead()
    {
        Customer::create(['name' => 'Known Cust', 'phone_number' => '675100003', 'is_active' => true]);
        $this->postWebhook($this->incomingText('+237675100003', 'I need speakers for a concert', 'P2K1'))
            ->assertStatus(200);
        $this->assertSame(0, Lead::count());
    }

    public function test_second_enquiry_updates_same_open_lead()
    {
        $this->postWebhook($this->incomingText('+237675100004', 'I need speakers for Saturday', 'P2S1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675100004', 'Also need lighting for the same event', 'P2S2'))->assertStatus(200);
        $this->assertSame(1, Lead::count());
        $this->assertStringContainsString('lighting', strtolower(Lead::first()->latest_enquiry));
    }

    public function test_takeover_release_pause_close_reopen()
    {
        $this->postWebhook($this->incomingText('+237675100005', 'Hello', 'P2T1'))->assertStatus(200);
        $conversation = WhatsAppConversation::first();
        $service = app(WhatsAppConversationService::class);
        $service->takeover($conversation, 7);
        $this->assertSame(7, (int) $conversation->fresh()->assigned_user_id);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $conversation->fresh()->mode);
        $service->release($conversation->fresh(), 7);
        $this->assertNull($conversation->fresh()->assigned_user_id);
        $service->pause($conversation->fresh(), 7);
        $this->assertSame(WhatsAppConversation::MODE_PAUSED, $conversation->fresh()->mode);
        $service->close($conversation->fresh(), 7);
        $this->assertSame(WhatsAppConversation::STATUS_CLOSED, $conversation->fresh()->status);
        $service->reopen($conversation->fresh(), 7);
        $this->assertSame(WhatsAppConversation::STATUS_OPEN, $conversation->fresh()->status);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $conversation->fresh()->mode);
    }

    public function test_internal_note_is_not_sent_on_whatsapp()
    {
        $this->postWebhook($this->incomingText('+237675100006', 'Hello', 'P2N1'))->assertStatus(200);
        $fake = $this->fakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $fake);
        $conversation = WhatsAppConversation::first();
        $note = app(WhatsAppConversationService::class)->addNote($conversation, 'Price is 400k — do not tell yet', 1);
        $this->assertInstanceOf(WhatsAppNote::class, $note);
        $this->assertSame([], $fake->sent);
        $this->assertSame(0, WhatsAppMessage::where('direction', 'OUTGOING')->count());
        $this->assertSame('Price is 400k — do not tell yet', WhatsAppNote::first()->body);
    }

    public function test_staff_reply_marks_waiting_customer()
    {
        $this->postWebhook($this->incomingText('+237675100007', 'Hello', 'P2R1'))->assertStatus(200);
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $conversation = WhatsAppConversation::first();
        $result = app(WhatsAppConversationService::class)->reply($conversation, 'We can help', 1);
        $this->assertTrue($result['success']);
        $this->assertSame(WhatsAppConversation::STATUS_WAITING_CUSTOMER, $conversation->fresh()->status);
    }

    public function test_convert_links_existing_customer()
    {
        $this->postWebhook($this->incomingText('+237675100008', 'I need CCTV for the office', 'P2C1'))->assertStatus(200);
        $this->assertSame(1, Lead::count());
        Customer::create(['name' => 'Existing', 'phone_number' => '237675100008', 'is_active' => true]);
        $before = Customer::count();
        $result = app(WhatsAppLeadService::class)->convert(Lead::first(), 1, true);
        $this->assertFalse($result['created']);
        $this->assertSame($before, Customer::count());
        $this->assertSame(LeadCatalog::STATUS_CONVERTED, Lead::first()->status);
        $this->assertNotNull(Lead::first()->converted_customer_id);
    }

    public function test_convert_creates_customer_when_missing()
    {
        $this->postWebhook($this->incomingText('+237675100009', 'I need CCTV installation please', 'P2C2'))->assertStatus(200);
        $before = Customer::count();
        $result = app(WhatsAppLeadService::class)->convert(Lead::first(), 1, true);
        $this->assertTrue($result['created']);
        $this->assertSame($before + 1, Customer::count());
        $this->assertSame(LeadCatalog::STATUS_CONVERTED, Lead::first()->status);
    }

    public function test_document_path_outside_app_is_rejected()
    {
        $this->postWebhook($this->incomingText('+237675100010', 'Hello', 'P2D1'))->assertStatus(200);
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $conversation = WhatsAppConversation::with('contact')->first();
        $result = app(WhatsAppConversationService::class)->sendExistingDocument($conversation, '/etc/hosts', 'hosts', null, 1);
        $this->assertFalse($result['success']);
        $this->assertSame(0, WhatsAppMessage::where('type', 'DOCUMENT')->count());
    }

    public function test_role_without_permission_cannot_open_leads()
    {
        $role = Role::find(3);
        $dummy = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $role->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $user = $this->makeUser(3, '675333333');
        $this->actingAs($user);
        $response = $this->get('/admin/whatsapp/leads');
        $response->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));
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

                return ['success' => true, 'msg_id' => 'P2OUT'];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true, 'msg_id' => 'P2DOC'];
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
