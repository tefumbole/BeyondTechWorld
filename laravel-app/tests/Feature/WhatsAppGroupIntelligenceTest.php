<?php

namespace Tests\Feature;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\Product;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupAction;
use App\WhatsApp\WhatsAppGroupAudit;
use App\WhatsApp\WhatsAppGroupMessage;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppOwnerUser;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class GroupFakeProvider implements WhatsAppProviderInterface
{
    public $groups = [];

    public $sent = [];

    public function sendText($phone, $message)
    {
        $this->sent[] = ['private', $message];

        return ['success' => true, 'msg_id' => 'P'.uniqid()];
    }

    public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
    {
        return ['success' => true, 'msg_id' => 'D'];
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
        return ['success' => true, 'groups' => $this->groups];
    }

    public function sendGroupText($groupJid, $message)
    {
        $this->sent[] = ['group', $groupJid, $message];

        return ['success' => true, 'msg_id' => 'G'.uniqid()];
    }
}

class WhatsAppGroupIntelligenceTest extends WhatsAppHubTestCase
{
    protected $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new GroupFakeProvider();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fake);
        config(['assistant.enabled' => true]);
        WhatsAppSetting::putValue('assistant_enabled', '1');
        WhatsAppSetting::putValue('default_conversation_mode', 'HUMAN');
        WhatsAppSetting::putValue('ai_first', '0');
        $role = Role::firstOrCreate(['id' => 1, 'name' => 'Admin', 'guard_name' => 'web']);
        $this->grantWhatsApp($role);
    }

    public function test_owner_can_turn_ai_first_on_without_rewriting_chats()
    {
        $this->owner('237650200001');
        $kept = $this->humanChat('237650200099', 'Kept');
        $kept->assigned_user_id = 9;
        $kept->save();
        $this->postWebhook($this->text('237650200001', 'AI FIRST ON', 'OWN1'))->assertStatus(200);
        $this->assertSame('1', WhatsAppSetting::getValue('ai_first', '0'));
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $kept->fresh()->mode);
    }

    public function test_unauthorized_number_cannot_disable_ai()
    {
        $this->postWebhook($this->text('237650200002', 'AI OFF', 'OWN2'))->assertStatus(200);
        $this->assertSame('1', WhatsAppSetting::getValue('assistant_enabled', '0'));
    }

    public function test_owner_bulk_switch_requires_yes()
    {
        $owner = $this->owner('237650200003');
        $open = $this->humanChat('237650200088', 'Open');
        $owned = $this->humanChat('237650200077', 'Owned');
        $owned->assigned_user_id = $owner->id;
        $owned->save();
        $this->postWebhook($this->text('237650200003', 'Switch all conversations to AI', 'OWN3A'))->assertStatus(200);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $open->fresh()->mode);
        $preview = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('eligible', strtolower($preview->body));
        $this->postWebhook($this->text('237650200003', 'YES', 'OWN3B'))->assertStatus(200);
        $this->assertSame(WhatsAppConversation::MODE_AI, $open->fresh()->mode);
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $owned->fresh()->mode);
    }

    public function test_discovered_groups_stay_off()
    {
        $this->fake->groups = [['jid' => '120363001@g.us', 'name' => 'Staff Group', 'description' => 'Ops']];
        $rows = app(\App\Services\WhatsApp\GroupRegistryService::class)->discover();
        $this->assertCount(1, $rows);
        $this->assertFalse((bool) $rows[0]->enabled);
        $this->assertSame(WhatsAppGroup::OFF, $rows[0]->mode);
    }

    public function test_monitor_stores_without_speaking()
    {
        $group = $this->group('Staff Group', WhatsAppGroup::MONITOR);
        $this->postWebhook($this->groupText($group->group_jid, '237650200010', 'See you at the hall', 'G1'))->assertStatus(200);
        $this->assertSame(1, WhatsAppGroupMessage::count());
        $this->assertSame([], $this->fake->sent);
        $this->assertSame(0, Customer::count());
    }

    public function test_mention_only_and_active_stay_quiet_until_invoked()
    {
        $mention = $this->group('Mention Group', WhatsAppGroup::MENTION);
        $this->postWebhook($this->groupText($mention->group_jid, '237650200011', 'hello team', 'G2'))->assertStatus(200);
        $this->assertSame([], $this->fake->sent);
        $active = $this->group('Active Group', WhatsAppGroup::ACTIVE);
        $this->postWebhook($this->groupText($active->group_jid, '237650200012', 'hello team', 'G3'))->assertStatus(200);
        $this->assertSame([], $this->fake->sent);
        $this->postWebhook($this->groupText($mention->group_jid, '237650200011', '@Beyond summarize today', 'G4'))->assertStatus(200);
        $this->assertNotEmpty($this->fake->sent);
    }

    public function test_participant_phone_is_not_the_group_jid_and_duplicates_are_ignored()
    {
        $group = $this->group('Staff Group', WhatsAppGroup::MONITOR);
        $payload = $this->groupText($group->group_jid, '237650200013', 'John will collect the equipment tomorrow.', 'G5');
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, WhatsAppGroupMessage::count());
        $row = WhatsAppGroupMessage::first();
        $this->assertSame('237650200013', $row->participant_phone);
        $this->assertNotSame($group->group_jid, $row->participant_phone);
        $this->assertSame(WhatsAppGroupAction::SUGGESTED, WhatsAppGroupAction::first()->status);
    }

    public function test_summary_separates_proposals_decisions_and_issues()
    {
        $group = $this->group('Management', WhatsAppGroup::MONITOR);
        $this->postWebhook($this->groupText($group->group_jid, '237650200014', 'Roland should prepare the quotation.', 'G6'))->assertStatus(200);
        $this->postWebhook($this->groupText($group->group_jid, '237650200014', 'We agreed to keep the Saturday wedding.', 'G7'))->assertStatus(200);
        $this->postWebhook($this->groupText($group->group_jid, '237650200014', 'The LED processor has not arrived.', 'G8'))->assertStatus(200);
        $text = app(\App\Services\WhatsApp\GroupIntelligenceService::class)->summary($group, now()->startOfDay(), 'decisions');
        $this->assertStringContainsString('We agreed', $text);
        $this->assertStringContainsString('not decisions', $text);
        $this->assertStringContainsString('Roland should prepare', $text);
        $this->assertStringContainsString('not arrived', $text);
    }

    public function test_group_inventory_uses_erp_and_labels_conflict()
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
        Product::create(['name' => 'KS118', 'code' => 'KS118', 'is_active' => true, 'qty' => 10, 'price' => 1000, 'rent_price_per_day' => 1000]);
        $group = $this->group('Technical Team', WhatsAppGroup::MENTION);
        $group->allow_inventory = true;
        $group->save();
        $this->postWebhook($this->groupText($group->group_jid, '237650200015', 'We have 12 line arrays available.', 'G9'))->assertStatus(200);
        $this->postWebhook($this->groupText($group->group_jid, '237650200015', '@Beyond how many KS118 are available?', 'G10'))->assertStatus(200);
        $joined = $this->groupSends();
        $this->assertStringContainsString('10', $joined);
        $this->assertStringContainsString('not inventory', $joined);
        $conflict = app(\App\Services\WhatsApp\GroupIntelligenceService::class)->compareClaim('12', 10);
        $this->assertStringContainsString('group discussion says 12', $conflict);
        $this->assertStringContainsString('ERP currently records 10', $conflict);
    }

    public function test_sensitive_group_question_stays_private()
    {
        $group = $this->group('Interns', WhatsAppGroup::ACTIVE);
        $group->allow_internship = true;
        $group->allow_payroll = false;
        $group->save();
        $this->humanChat('237650200016', 'Intern');
        $this->postWebhook($this->groupText($group->group_jid, '237650200016', '@Beyond what is my salary?', 'G11'))->assertStatus(200);
        $joined = $this->groupSends();
        $this->assertStringContainsString('privately', $joined);
        $this->assertStringNotContainsString('500000', $joined);
        $this->assertNotNull(WhatsAppGroupAudit::where('type', 'sensitive_denied')->first());
    }

    public function test_draft_send_requires_the_named_group()
    {
        $this->owner('237650200017');
        $this->group('Technical Team', WhatsAppGroup::MONITOR);
        $this->group('Events', WhatsAppGroup::MONITOR);
        $this->postWebhook($this->text('237650200017', 'Draft a reply to the Technical Team about the delayed setup.', 'OWN4'))->assertStatus(200);
        $draft = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('was not sent', $draft->body);
        $this->postWebhook($this->text('237650200017', 'SEND', 'OWN5'))->assertStatus(200);
        $ask = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertStringContainsString('Send this to Technical Team?', $ask->body);
        $this->assertSame([], array_values(array_filter($this->fake->sent, function ($row) {
            return $row[0] === 'group';
        })));
        $this->postWebhook($this->text('237650200017', 'send this to Events', 'OWN6'))->assertStatus(200);
        $this->postWebhook($this->text('237650200017', 'YES', 'OWN7'))->assertStatus(200);
        $sent = $this->groupSends();
        $this->assertStringContainsString('Technical Team', $sent);
    }

    public function test_takeover_and_group_memory_stay_isolated()
    {
        $this->owner('237650200018');
        $this->humanChat('237650200050', 'Daniel');
        $this->postWebhook($this->text('237650200018', 'Take over Daniel', 'OWN8'))->assertStatus(200);
        $daniel = WhatsAppConversation::whereHas('contact', function ($q) {
            $q->where('wa_name', 'Daniel');
        })->first();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $daniel->mode);
        $a = $this->group('Staff Group', WhatsAppGroup::MONITOR);
        $b = $this->group('Interns', WhatsAppGroup::MONITOR);
        $a->setMemory(['last_topic' => 'staff-only']);
        $a->save();
        $b->setMemory(['last_topic' => 'intern-only']);
        $b->save();
        $this->assertSame('staff-only', $a->fresh()->memory()['last_topic']);
        $this->assertSame('intern-only', $b->fresh()->memory()['last_topic']);
    }

    public function test_cross_group_summary_and_morning_brief_use_stored_messages()
    {
        $owner = $this->owner('237650200019');
        $tech = $this->group('Technical Team', WhatsAppGroup::MONITOR);
        $events = $this->group('Events', WhatsAppGroup::MONITOR);
        $this->postWebhook($this->groupText($tech->group_jid, '237650200030', 'Saturday setup is behind schedule.', 'G12'))->assertStatus(200);
        $this->postWebhook($this->groupText($events->group_jid, '237650200031', 'Saturday setup is behind schedule.', 'G13'))->assertStatus(200);
        $cross = app(\App\Services\WhatsApp\GroupIntelligenceService::class)->crossGroup();
        $this->assertEquals(1, substr_count($cross, 'Saturday setup is behind schedule.'));
        $brief = app(\App\Services\WhatsApp\GroupIntelligenceService::class)->brief($owner);
        $this->assertStringContainsString('stored message', $brief);
        $this->assertStringContainsString('ERP', $brief);
        $this->assertStringContainsString('Technical Team', $brief);
    }

    protected function owner($phone)
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => $phone.'@example.test',
            'password' => Hash::make('secret'),
            'phone' => $phone,
            'role_id' => Role::where('guard_name', 'web')->where('name', 'Admin')->value('id') ?: 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        WhatsAppOwnerUser::create([
            'user_id' => $user->id,
            'normalized_phone' => $phone,
            'enabled' => true,
        ]);

        return $user;
    }

    protected function humanChat($phone, $name)
    {
        $this->postWebhook($this->text($phone, 'Hello', 'H'.$phone))->assertStatus(200);
        $conversation = WhatsAppConversation::orderByDesc('id')->first();
        $conversation->contact->wa_name = $name;
        $conversation->contact->save();
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->save();

        return $conversation;
    }

    protected function group($name, $mode)
    {
        return WhatsAppGroup::create([
            'group_jid' => '120363'.substr(md5($name), 0, 6).'@g.us',
            'name' => $name,
            'enabled' => $mode !== WhatsAppGroup::OFF,
            'mode' => $mode,
            'raw_retention_days' => 30,
            'summary_retention_days' => 180,
            'action_retention_days' => 365,
        ]);
    }

    protected function text($phone, $body, $id)
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

    protected function groupText($jid, $phone, $body, $id)
    {
        return [
            'event' => 'messages-group.received',
            'timestamp' => time(),
            'data' => [
                'messages' => [
                    'key' => [
                        'id' => $id,
                        'fromMe' => false,
                        'remoteJid' => $jid,
                        'participant' => $phone.'@s.whatsapp.net',
                        'cleanedParticipantPn' => $phone,
                    ],
                    'messageBody' => $body,
                    'pushName' => 'Speaker',
                    'message' => ['conversation' => $body],
                ],
            ],
        ];
    }

    protected function groupSends()
    {
        $bits = [];
        foreach ($this->fake->sent as $row) {
            if ($row[0] === 'group') {
                $bits[] = $row[2];
            }
        }

        return implode("\n", $bits);
    }
}
