<?php

namespace Tests\Feature;

use App\Appointment\Appointment;
use App\Appointment\AppointmentAvailability;
use App\Appointment\AppointmentReminder;
use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\Calendar\CalendarProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\Appointment\AppointmentService;
use App\Services\Assistant\Providers\NullAiProvider;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Carbon\Carbon;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase9Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        config(['assistant.enabled' => true, 'assistant.provider' => 'null', 'services.calendar.reminders_enabled' => false]);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
    }

    public function test_missing_windows_are_not_invented()
    {
        $this->postWebhook($this->incoming('+237670000901', 'I want an appointment tomorrow regarding network installation', 'S9A1'))
            ->assertStatus(200);
        $out = WhatsAppMessage::where('sender_type', 'ASSISTANT')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('will not invent', $out->body);
        $this->assertSame(0, Appointment::count());
    }

    public function test_customer_must_choose_a_configured_slot()
    {
        $this->window();
        User::create([
            'name' => 'Roland',
            'email' => 'roland'.uniqid().'@example.test',
            'password' => 'x',
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        $this->postWebhook($this->incoming('+237670000902', 'I want an appointment with Roland tomorrow regarding network installation', 'S9B1'))
            ->assertStatus(200);
        $this->assertSame(0, Appointment::count());
        $ask = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('Reply with the number', $ask->body);
        $this->postWebhook($this->incoming('+237670000902', '1', 'S9B2'))->assertStatus(200);
        $row = Appointment::first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('APT-', $row->reference);
        $this->assertSame('network installation', $row->purpose);
        $this->assertSame('Network Assessment', $row->category);
        $this->assertSame('CONFIRMED', $row->status);
        $this->assertSame('NOT_CONFIGURED', $row->google_sync_status);
        $this->assertNull($row->google_event_id);
        $confirmed = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('APPOINTMENT CONFIRMED', $confirmed->body);
        $this->assertStringContainsString('not connected', $confirmed->body);
        $this->assertSame(1, Appointment::count());
    }

    public function test_calendar_provider_stores_the_event_id()
    {
        $this->window();
        $this->app->instance(CalendarProviderInterface::class, new Phase9CalendarFake());
        $this->postWebhook($this->incoming('+237670000903', 'I want an appointment tomorrow regarding training', 'S9C1'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000903', '1', 'S9C2'))->assertStatus(200);
        $row = Appointment::first();
        $this->assertSame('SYNCED', $row->google_sync_status);
        $this->assertSame('evt-1', $row->google_event_id);
        $confirmed = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('Google Calendar', $confirmed->body);
    }

    public function test_taken_slot_is_not_offered_again()
    {
        $this->window();
        $this->postWebhook($this->incoming('+237670000904', 'Book me tomorrow for a site visit', 'S9D1'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000904', '1', 'S9D2'))->assertStatus(200);
        $first = Appointment::first()->starts_at->format('H:i');
        $this->postWebhook($this->incoming('+237670000905', 'I want an appointment tomorrow regarding support', 'S9D3'))->assertStatus(200);
        $offer = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('1. '.Carbon::tomorrow()->format('j F').' '.$first, $offer->body);
    }

    public function test_confirm_cancel_and_other_contact()
    {
        $this->window();
        $this->postWebhook($this->incoming('+237670000906', 'I want an appointment tomorrow regarding training', 'S9E1'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000906', '1', 'S9E2'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000906', 'CONFIRM', 'S9E3'))->assertStatus(200);
        $row = Appointment::first();
        $this->assertSame('CONFIRM', $row->fresh()->customer_response);
        $this->postWebhook($this->incoming('+237670000907', 'CANCEL', 'S9E4'))->assertStatus(200);
        $this->assertSame('CONFIRMED', $row->fresh()->status);
        $denied = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('could not find', $denied->body);
        $this->postWebhook($this->incoming('+237670000906', 'CANCEL', 'S9E5'))->assertStatus(200);
        $this->assertSame('CANCELLED', $row->fresh()->status);
    }

    public function test_reminder_sends_once_and_stays_off_until_enabled()
    {
        $conversation = $this->conversation('+237670000908', 'S9F1');
        $row = Appointment::create([
            'reference' => 'APT-9001',
            'category' => 'Training',
            'purpose' => 'Training',
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'staff_label' => 'BeyondTechWorld',
            'starts_at' => Carbon::now()->addHours(23),
            'ends_at' => Carbon::now()->addHours(24),
            'status' => 'CONFIRMED',
            'source' => 'WHATSAPP',
            'google_sync_status' => 'NOT_CONFIGURED',
        ]);
        $this->artisan('whatsapp:appointment-reminders')->assertExitCode(0);
        $this->assertSame(0, AppointmentReminder::count());
        $sent = app(AppointmentService::class)->sendDueReminders();
        $this->assertSame(1, $sent);
        $this->assertSame(0, app(AppointmentService::class)->sendDueReminders());
        $this->assertSame(1, AppointmentReminder::where('appointment_id', $row->id)->count());
        $notice = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('Reminder:', $notice->body);
    }

    public function test_social_replies_stay_in_the_conversation()
    {
        $this->postWebhook($this->incoming('+237670000909', 'Hello', 'S9G1'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000909', 'how are you today', 'S9G2'))->assertStatus(200);
        $this->postWebhook($this->incoming('+237670000909', "I'm great and you?", 'S9G3'))->assertStatus(200);
        $bodies = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderBy('id')->pluck('body')->all();
        $joined = implode("\n", $bodies);
        $this->assertStringContainsString('Beyond Assistant', $joined);
        $this->assertStringContainsString("I'm doing well", $joined);
        $this->assertStringContainsString('Glad to hear it', $joined);
        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $this->assertSame(WhatsAppConversation::MODE_AI, $conversation->mode);
    }

    public function test_owner_chat_returns_to_ai_for_a_normal_message()
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner'.uniqid().'@example.test',
            'password' => 'x',
            'phone' => '250794006160',
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        \App\WhatsApp\WhatsAppOwnerUser::create([
            'user_id' => $user->id,
            'normalized_phone' => '250794006160',
            'enabled' => true,
        ]);
        WhatsAppSetting::putValue('default_conversation_mode', 'HUMAN');
        $this->postWebhook($this->incoming('+250794006160', 'Hello', 'S9H1'))->assertStatus(200);
        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->assigned_user_id = $user->id;
        $conversation->save();
        $this->postWebhook($this->incoming('+250794006160', 'how are you today', 'S9H2'))->assertStatus(200);
        $conversation = $conversation->fresh();
        $this->assertSame(WhatsAppConversation::MODE_AI, $conversation->mode);
        $this->assertNull($conversation->assigned_user_id);
        $reply = WhatsAppMessage::where('conversation_id', $conversation->id)->where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString("I'm doing well", $reply->body);
    }

    public function test_calendar_notification_updates_the_erp_time()
    {
        config(['services.calendar.channel_token' => 'channel-token']);
        $fake = new Phase9CalendarFake();
        $this->app->instance(CalendarProviderInterface::class, $fake);
        $row = Appointment::create([
            'reference' => 'APT-9002',
            'category' => 'Other',
            'purpose' => 'Follow up',
            'staff_label' => 'BeyondTechWorld',
            'starts_at' => Carbon::parse('2026-10-01 14:00:00'),
            'ends_at' => Carbon::parse('2026-10-01 15:00:00'),
            'status' => 'CONFIRMED',
            'source' => 'ERP',
            'google_event_id' => 'evt-9',
            'google_sync_status' => 'SYNCED',
        ]);
        $this->postJson('/api/webhooks/google-calendar', [], [
            'X-Goog-Channel-Token' => 'wrong',
            'X-Goog-Resource-State' => 'exists',
            'X-Goog-Resource-URI' => 'https://www.googleapis.com/calendar/v3/calendars/primary/events/evt-9',
        ])->assertStatus(403);
        $this->postJson('/api/webhooks/google-calendar', [], [
            'X-Goog-Channel-Token' => 'channel-token',
            'X-Goog-Resource-State' => 'exists',
            'X-Goog-Resource-URI' => 'https://www.googleapis.com/calendar/v3/calendars/primary/events/evt-9',
        ])->assertStatus(200);
        $this->assertSame('2026-10-02 16:00:00', $row->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    protected function window()
    {
        AppointmentAvailability::create([
            'weekday' => Carbon::tomorrow()->dayOfWeek,
            'starts_time' => '14:00:00',
            'ends_time' => '17:00:00',
            'slot_minutes' => 60,
            'location' => 'Office',
            'enabled' => true,
        ]);
    }

    protected function conversation($phone, $id)
    {
        $this->postWebhook($this->incoming($phone, 'Hello', $id))->assertStatus(200);

        return WhatsAppConversation::orderByDesc('id')->first();
    }

    protected function incoming($phone, $body, $id)
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
            public function sendText($phone, $message)
            {
                return ['success' => true, 'msg_id' => uniqid('s9')];
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

            public function listGroups()
            {
                return ['success' => true, 'groups' => []];
            }

            public function sendGroupText($groupJid, $message)
            {
                return ['success' => true, 'msg_id' => uniqid('g')];
            }
        };
    }
}

class Phase9CalendarFake implements CalendarProviderInterface
{
    public function isConfigured()
    {
        return true;
    }

    public function createEvent(array $event)
    {
        return ['success' => true, 'configured' => true, 'event_id' => 'evt-1'];
    }

    public function updateEvent($eventId, array $event)
    {
        return ['success' => true, 'configured' => true, 'event_id' => $eventId];
    }

    public function cancelEvent($eventId)
    {
        return ['success' => true, 'configured' => true];
    }

    public function getEvent($eventId)
    {
        return [
            'success' => true,
            'configured' => true,
            'event' => [
                'id' => $eventId,
                'start' => ['dateTime' => '2026-10-02T16:00:00+01:00'],
                'end' => ['dateTime' => '2026-10-02T17:00:00+01:00'],
            ],
        ];
    }
}
