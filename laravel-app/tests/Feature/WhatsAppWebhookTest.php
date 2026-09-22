<?php

namespace Tests\Feature;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Employee;
use App\InternshipEnrolment;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppHubQuery;
use App\Services\WhatsApp\WhatsAppIdentityService;
use App\User;
use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppWebhookEvent;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppWebhookTest extends WhatsAppHubTestCase
{
    public function test_rejects_invalid_signature()
    {
        $response = $this->withHeaders(['X-Webhook-Signature' => 'wrong', 'Content-Type' => 'application/json'])
            ->json('POST', '/api/webhooks/wasender', ['event' => 'messages.received']);

        $response->assertStatus(401);
        $this->assertSame(0, WhatsAppWebhookEvent::count());
    }

    public function test_rejects_missing_signature()
    {
        $this->json('POST', '/api/webhooks/wasender', ['event' => 'messages.received'])->assertStatus(401);
    }

    public function test_rejects_malformed_payload()
    {
        $this->withHeaders($this->signedHeaders(''))
            ->call('POST', '/api/webhooks/wasender', [], [], [], [
                'HTTP_X_WEBHOOK_SIGNATURE' => 'test-secret',
                'CONTENT_TYPE' => 'application/json',
            ], 'not-json')
            ->assertStatus(400);
    }

    public function test_accepts_valid_signature_and_incoming_text()
    {
        $payload = $this->incomingText('+237675000001', 'Hello Beyond', 'MSG1');
        $this->postWebhook($payload)->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertSame(1, WhatsAppWebhookEvent::count());
        $this->assertSame(1, WhatsAppConversation::count());
        $message = WhatsAppMessage::first();
        $this->assertSame('INCOMING', $message->direction);
        $this->assertSame('TEXT', $message->type);
        $this->assertSame('Hello Beyond', $message->body);
        $this->assertSame(1, (int) WhatsAppConversation::first()->unread_count);
    }

    public function test_duplicate_webhook_is_idempotent()
    {
        $payload = $this->incomingText('+237675000002', 'Once', 'MSGDUP');
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200)->assertJson(['duplicate' => true]);
        $this->assertSame(1, WhatsAppWebhookEvent::count());
        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_unsupported_event_is_ignored()
    {
        $this->postWebhook(['event' => 'session.status', 'timestamp' => 1, 'data' => ['ok' => true]])
            ->assertStatus(200);
        $event = WhatsAppWebhookEvent::first();
        $this->assertSame('IGNORED', $event->status);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_incoming_media_metadata()
    {
        $payload = [
            'event' => 'messages.received',
            'timestamp' => 100,
            'data' => [
                'messages' => [
                    'key' => ['id' => 'IMG1', 'fromMe' => false, 'remoteJid' => '237675000003@s.whatsapp.net', 'cleanedSenderPn' => '237675000003'],
                    'message' => ['imageMessage' => ['mimetype' => 'image/jpeg', 'caption' => 'Photo', 'url' => 'https://example.test/a.jpg']],
                ],
            ],
        ];
        $this->postWebhook($payload)->assertStatus(200);
        $message = WhatsAppMessage::first();
        $this->assertSame('IMAGE', $message->type);
        $this->assertSame('Photo', $message->body);
        $this->assertSame('image/jpeg', $message->media()['mimetype']);
    }

    public function test_sent_delivered_read_failed_updates()
    {
        $this->postWebhook($this->incomingText('+237675000004', 'Hi', 'IN4'))->assertStatus(200);
        $out = WhatsAppMessage::create([
            'conversation_id' => WhatsAppConversation::first()->id,
            'contact_id' => WhatsAppConversation::first()->contact_id,
            'direction' => 'OUTGOING',
            'type' => 'TEXT',
            'provider_message_id' => 'OUT4',
            'body' => 'Reply',
            'status' => 'QUEUED',
        ]);

        $this->postWebhook($this->statusUpdate('OUT4', 2))->assertStatus(200);
        $this->assertSame('SENT', $out->fresh()->status);
        $this->assertNotNull($out->fresh()->sent_at);

        $this->postWebhook($this->statusUpdate('OUT4', 3))->assertStatus(200);
        $this->assertSame('DELIVERED', $out->fresh()->status);

        $this->postWebhook($this->statusUpdate('OUT4', 4))->assertStatus(200);
        $this->assertSame('READ', $out->fresh()->status);
        $this->assertNotNull($out->fresh()->read_at);

        $fail = WhatsAppMessage::create([
            'conversation_id' => $out->conversation_id,
            'contact_id' => $out->contact_id,
            'direction' => 'OUTGOING',
            'type' => 'TEXT',
            'provider_message_id' => 'OUT4F',
            'body' => 'Nope',
            'status' => 'QUEUED',
        ]);
        $this->postWebhook($this->statusUpdate('OUT4F', 0))->assertStatus(200);
        $this->assertSame('FAILED', $fail->fresh()->status);
    }

    public function test_conversation_reuse_and_unread()
    {
        $this->postWebhook($this->incomingText('+237675000005', 'One', 'U1'))->assertStatus(200);
        $this->postWebhook($this->incomingText('+237675000005', 'Two', 'U2'))->assertStatus(200);
        $this->assertSame(1, WhatsAppConversation::count());
        $this->assertSame(2, (int) WhatsAppConversation::first()->unread_count);
        $this->assertSame(2, WhatsAppMessage::count());
    }

    public function test_unknown_contact_creates_whatsapp_contact_not_lead()
    {
        $this->postWebhook($this->incomingText('+237675999999', 'New', 'NEW1'))->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_contacts', ['normalized_phone' => '237675999999']);
        $this->assertSame(0, \Illuminate\Support\Facades\Schema::hasTable('leads') ? \DB::table('leads')->count() : 0);
    }

    public function test_known_customer_employee_user_and_multiple_identities()
    {
        User::create([
            'name' => 'Roland', 'email' => 'roland@example.test', 'password' => bcrypt('x'),
            'phone' => '675321739', 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $user = User::where('email', 'roland@example.test')->first();
        Employee::create(['name' => 'Roland Emp', 'phone_number' => '675321739', 'user_id' => $user->id, 'is_active' => true]);
        Customer::create(['name' => 'Roland Cust', 'phone_number' => '675321739', 'is_active' => true]);
        InternshipEnrolment::create(['student_user_id' => $user->id, 'status' => 'active']);

        $matches = app(WhatsAppIdentityService::class)->resolve('675321739');
        $roles = array_column($matches, 'role');
        $this->assertContains('user', $roles);
        $this->assertContains('employee', $roles);
        $this->assertContains('customer', $roles);
        $this->assertContains('intern', $roles);

        $this->postWebhook($this->incomingText('237675321739', 'Hi', 'ID1'))->assertStatus(200);
        $contact = \App\WhatsApp\WhatsAppContact::first();
        $this->assertGreaterThanOrEqual(3, $contact->links()->count());
    }

    public function test_incoming_call_and_duplicate_call()
    {
        $payload = [
            'event' => 'call',
            'timestamp' => 200,
            'data' => [
                'call' => [
                    'id' => 'CALL1',
                    'from' => '237675000010@s.whatsapp.net',
                    'date' => '2026-09-22T10:00:00.000Z',
                    'isVideo' => true,
                    'status' => 'offer',
                ],
            ],
        ];
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, WhatsAppCall::count());
        $call = WhatsAppCall::first();
        $this->assertSame('video', $call->call_type);
        $this->assertSame('RECEIVED', $call->status);
    }

    public function test_staff_reply_uses_provider()
    {
        $this->postWebhook($this->incomingText('+237675000020', 'Need help', 'R1'))->assertStatus(200);
        $fake = new class implements WhatsAppProviderInterface {
            public $sent = [];
            public function sendText($phone, $message)
            {
                $this->sent[] = [$phone, $message];

                return ['success' => true, 'msg_id' => 'STAFF1'];
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
        $this->app->instance(WhatsAppProviderInterface::class, $fake);
        $conversation = WhatsAppConversation::first();
        $service = $this->app->make(WhatsAppConversationService::class);
        $result = $service->reply($conversation, 'We will help', 1);
        $this->assertTrue($result['success']);
        $out = WhatsAppMessage::where('direction', 'OUTGOING')->first();
        $this->assertSame('SENT', $out->status);
        $this->assertSame('STAFF1', $out->provider_message_id);
        $this->assertSame(0, (int) $conversation->fresh()->unread_count);
    }

    public function test_guest_cannot_open_hub()
    {
        $this->get('/admin/whatsapp')->assertRedirect();
    }

    public function test_permission_enforced()
    {
        $role = Role::find(3);
        $dummy = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $role->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $user = $this->makeUser(3, '675222222');
        $this->actingAs($user);
        $response = $this->get('/admin/whatsapp');
        $response->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));
    }

    public function test_diagnostics_do_not_leak_secrets()
    {
        config(['services.whatsapp.wasender_api_key' => 'super-secret-key']);
        config(['services.whatsapp.wasender_webhook_secret' => 'super-secret-hook']);
        $diag = app(WhatsAppHubQuery::class)->diagnostics();
        $blob = json_encode($diag);
        $this->assertStringNotContainsString('super-secret-key', $blob);
        $this->assertStringNotContainsString('super-secret-hook', $blob);
        $this->assertSame('Configured', $diag['webhook_secret']);
        $this->assertSame('Configured', $diag['wasender_key']);
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

    protected function statusUpdate($id, $code)
    {
        return [
            'event' => 'messages.update',
            'timestamp' => time(),
            'data' => [
                'update' => ['status' => $code],
                'key' => ['id' => $id, 'fromMe' => true, 'remoteJid' => '237675000004@s.whatsapp.net'],
            ],
        ];
    }
}
