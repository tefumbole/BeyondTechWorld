<?php

namespace Tests\Feature;

use App\Mai\MaiPeer;
use App\Mai\MaiSession;
use App\Services\Mai\MaiIdentity;
use App\Services\Mai\MaiInboxStore;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiContactIsolationTest extends TestCase
{
    public static $hooks = 0;
    protected function setUp(): void
    {
        parent::setUp();
        config(['cloud.mai_customer.enabled' => true]);
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 32)->unique();
            $table->string('display_phone', 48)->nullable();
            $table->string('wa_name', 191)->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contact_id')->index();
            $table->unsignedInteger('mai_session_id')->nullable()->index();
            $table->string('peer_label', 191)->nullable();
            $table->string('mode', 16)->default('HUMAN');
            $table->string('status', 16)->default('OPEN');
            $table->string('channel', 32)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('last_message')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_incoming_at')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('direction', 16);
            $table->string('type', 24)->default('TEXT');
            $table->string('provider_message_id', 128)->nullable()->unique();
            $table->text('body')->nullable();
            $table->text('media_json')->nullable();
            $table->string('status', 16)->default('DELIVERED');
            $table->string('sender_type', 16)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mai_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('public_id', 64);
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('provider', 32);
            $table->string('worker_session_id', 64)->nullable();
            $table->string('phone_number', 32)->nullable();
            $table->string('expected_phone', 32)->nullable();
            $table->string('status', 32)->default('CONNECTED');
            $table->boolean('ai_enabled')->default(0);
            $table->unsignedInteger('sync_generation')->default(0);
            $table->unsignedInteger('sync_seq')->default(0);
            $table->boolean('history_ready')->default(0);
            $table->timestamps();
        });
        Schema::create('mai_peers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mai_session_id')->index();
            $table->string('remote_jid', 191);
            $table->string('phone_digits', 32)->nullable();
            $table->string('lid', 64)->nullable();
            $table->string('saved_name', 191)->nullable();
            $table->string('profile_name', 191)->nullable();
            $table->timestamps();
            $table->unique(['mai_session_id', 'remote_jid']);
        });
        Schema::create('mai_webhooks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('mai_session_id')->nullable();
            $table->string('url', 500);
            $table->text('secret');
            $table->boolean('active')->default(1);
            $table->timestamps();
        });
        Schema::create('mai_webhook_deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mai_webhook_id')->index();
            $table->string('event_id', 80);
            $table->text('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error', 191)->nullable();
            $table->timestamps();
        });
        Schema::create('mai_outbound_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mai_session_id')->index();
            $table->string('idempotency_key', 80);
            $table->string('to_phone', 32);
            $table->text('body');
            $table->string('status', 16)->default('pending');
            $table->string('provider_message_id', 128)->nullable();
            $table->string('last_error', 191)->nullable();
            $table->timestamps();
        });
        Schema::create('mai_inbound_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mai_session_id')->index();
            $table->string('event_id', 80);
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['mai_session_id', 'event_id']);
        });
    }

    public function test_two_accounts_in_one_tenant_keep_their_own_names()
    {
        $contact = $this->contact('Owner Book');
        $ada = $this->linked(1, 'p237670000111');
        $bola = $this->linked(2, 'p237670000222');
        $adaChat = $this->chat($contact, $ada, 'Owner Book');
        $bolaChat = $this->chat($contact, $bola, 'Owner Book');
        app(MaiIdentity::class)->remember($ada, [
            'jid' => '237699000333@s.whatsapp.net',
            'phone' => '237699000333',
            'saved_name' => 'Ada Name',
        ]);
        app(MaiIdentity::class)->remember($bola, [
            'jid' => '237699000333@s.whatsapp.net',
            'phone' => '237699000333',
            'saved_name' => 'Bola Name',
            'profile_name' => 'Profile Only',
        ]);

        $this->assertSame('Ada Name', app(MaiIdentity::class)->display($adaChat->fresh(), $contact));
        $this->assertSame('Bola Name', app(MaiIdentity::class)->display($bolaChat->fresh(), $contact));
        $this->assertNotSame('Owner Book', app(MaiIdentity::class)->display($adaChat, $contact));

        $peer = MaiPeer::where('mai_session_id', $ada->id)->first();
        $peer->saved_name = 'Ada Two';
        $peer->save();
        $adaChat = $adaChat->fresh();
        $bolaChat = $bolaChat->fresh();
        $this->assertSame('Ada Two', app(MaiIdentity::class)->display($adaChat, $contact->fresh()));
        $this->assertSame('Bola Name', app(MaiIdentity::class)->display($bolaChat, $contact->fresh()));
        $this->assertSame('Owner Book', $contact->fresh()->wa_name);
        $this->assertSame(0, MaiPeer::where('mai_session_id', $bola->id)->where('saved_name', 'Ada Two')->count());
    }

    public function test_a_profile_update_does_not_replace_the_saved_name()
    {
        $contact = $this->contact('');
        $contact->normalized_phone = '250788000111';
        $contact->display_phone = '+250788000111';
        $contact->save();
        $session = $this->linked(31, 'p237670000031');
        $chat = $this->chat($contact, $session, '');
        $identity = app(MaiIdentity::class);
        $identity->remember($session, [
            'jid' => '250788000111@s.whatsapp.net',
            'phone' => '250788000111',
            'saved_name' => 'Pa. Nasrah Umwali',
        ]);
        $identity->remember($session, [
            'jid' => '250788000111@s.whatsapp.net',
            'phone' => '250788000111',
            'profile_name' => 'Nasrah',
            'name' => 'Nasrah',
        ]);
        $identity->remember($session, [
            'jid' => '250788000111@s.whatsapp.net',
            'phone' => '250788000111',
            'saved_name' => 'Nasrah',
        ]);
        $peer = MaiPeer::where('mai_session_id', $session->id)->where('phone_digits', '250788000111')->first();
        $this->assertSame('Pa. Nasrah Umwali', $peer->saved_name);
        $this->assertSame('Nasrah', $peer->profile_name);
        $this->assertSame('Pa. Nasrah Umwali', $identity->display($chat->fresh(), $contact->fresh()));

        $identity->remember($session, [
            'jid' => '250788000111@s.whatsapp.net',
            'phone' => '250799999999',
            'saved_name' => 'Pa. Nasrah Umwali',
        ]);
        $this->assertSame('250788000111', $peer->fresh()->phone_digits);

        $identity->remember($session, [
            'jid' => '250788000222@s.whatsapp.net',
            'phone' => '250788000222',
            'saved_name' => 'David Office',
        ]);
        $identity->remember($session, [
            'jid' => '250788000222@s.whatsapp.net',
            'phone' => '250788000222',
            'saved_name' => 'David Church',
        ]);
        $renamed = MaiPeer::where('mai_session_id', $session->id)->where('phone_digits', '250788000222')->first();
        $this->assertSame('David Church', $renamed->saved_name);
        $this->assertSame('Pa. Nasrah Umwali', $peer->fresh()->saved_name);
    }

    public function test_a_verified_lid_mapping_keeps_the_saved_name_and_the_messages()
    {
        $session = $this->linked(32, 'p237670000032');
        $lidContact = $this->contact('Nasrah');
        $lidContact->normalized_phone = '155512345465';
        $lidContact->display_phone = '155512345465';
        $lidContact->wa_name = 'Nasrah';
        $lidContact->save();
        $lidChat = $this->chat($lidContact, $session, 'Nasrah');
        $message = new WhatsAppMessage();
        $message->conversation_id = $lidChat->id;
        $message->contact_id = $lidContact->id;
        $message->direction = WhatsAppMessage::DIR_IN;
        $message->type = 'TEXT';
        $message->body = 'kept';
        $message->provider_message_id = 'mai-lid-kept';
        $message->save();
        app(MaiIdentity::class)->remember($session, [
            'jid' => '250788004793@s.whatsapp.net',
            'phone' => '250788004793',
            'lid' => '155512345465',
            'saved_name' => 'PA Nasrah Umwali',
            'profile_name' => 'Nasrah',
        ]);

        $this->assertSame('PA Nasrah Umwali', app(MaiIdentity::class)->display($lidChat->fresh(), $lidContact->fresh()));
        $this->artisan('mai:repair-labels', ['--session' => 32])
            ->expectsOutput('examined=1')
            ->expectsOutput('propose id='.$lidChat->id.' from=Nasrah to=PA Nasrah Umwali')
            ->expectsOutput('rebuilt=1 cleared=0 kept=0 dry=no')
            ->assertExitCode(0);
        $this->assertSame('PA Nasrah Umwali', $lidChat->fresh()->peer_label);
        $this->assertSame(1, WhatsAppMessage::where('conversation_id', $lidChat->id)->count());
        $this->assertSame(1, WhatsAppConversation::where('mai_session_id', $session->id)->count());
    }

    public function test_a_profile_saved_on_the_lid_does_not_beat_the_address_book()
    {
        $session = $this->linked(33, 'p237670000033');
        $phoneContact = $this->contact('');
        $phoneContact->normalized_phone = '250788004793';
        $phoneContact->display_phone = '+250788004793';
        $phoneContact->wa_name = 'PA Nasrah Umwali';
        $phoneContact->save();
        $lidContact = new WhatsAppContact();
        $lidContact->normalized_phone = '155512345465';
        $lidContact->display_phone = '155512345465';
        $lidContact->wa_name = 'Nasrah';
        $lidContact->save();
        $phoneChat = $this->chat($phoneContact, $session, 'PA Nasrah Umwali');
        $lidChat = $this->chat($lidContact, $session, 'Nasrah');
        $kept = new WhatsAppMessage();
        $kept->conversation_id = $lidChat->id;
        $kept->contact_id = $lidContact->id;
        $kept->direction = WhatsAppMessage::DIR_IN;
        $kept->type = 'TEXT';
        $kept->body = 'kept';
        $kept->provider_message_id = 'mai-two-threads';
        $kept->save();
        MaiPeer::create([
            'mai_session_id' => $session->id,
            'remote_jid' => '155512345465@lid',
            'phone_digits' => '250788004793',
            'lid' => '155512345465',
            'saved_name' => 'Nasrah',
            'profile_name' => 'Nasrah',
        ]);
        MaiPeer::create([
            'mai_session_id' => $session->id,
            'remote_jid' => '250788004793@s.whatsapp.net',
            'phone_digits' => '250788004793',
            'lid' => '155512345465',
            'saved_name' => 'PA Nasrah Umwali',
            'profile_name' => 'Nasrah',
        ]);
        $identity = app(MaiIdentity::class);
        $this->assertSame('PA Nasrah Umwali', $identity->display($phoneChat->fresh(), $phoneContact->fresh()));
        $this->assertSame('PA Nasrah Umwali', $identity->display($lidChat->fresh(), $lidContact->fresh()));
        $this->artisan('mai:repair-labels', ['--session' => $session->id, '--dry-run' => true])
            ->expectsOutput('examined=2')
            ->expectsOutput('propose id='.$lidChat->id.' from=Nasrah to=PA Nasrah Umwali')
            ->expectsOutput('rebuilt=1 cleared=0 kept=1 dry=yes')
            ->assertExitCode(0);
        $this->assertSame('Nasrah', $lidChat->fresh()->peer_label);
        $this->assertSame('PA Nasrah Umwali', $phoneChat->fresh()->peer_label);
        $this->assertSame(1, WhatsAppMessage::where('conversation_id', $lidChat->id)->count());
        $this->assertSame(2, WhatsAppConversation::where('mai_session_id', $session->id)->count());

        $identity->remember($session, [
            'jid' => '250788004793@s.whatsapp.net',
            'phone' => '250788004793',
            'saved_name' => 'Ann',
        ]);
        $this->assertSame('Ann', $identity->display($phoneChat->fresh(), $phoneContact->fresh()));
        $this->assertSame(['237675321739', '23775321739'], $identity->searchAliases('237675321739'));
        $this->assertSame(['23775321739', '237675321739'], $identity->searchAliases('23775321739'));
        $this->assertSame(['250794006160'], $identity->searchAliases('250794006160'));
    }

    public function test_a_lid_thread_and_its_phone_thread_become_one_chat()
    {
        $session = $this->linked(34, 'p237670000034');
        $other = $this->linked(35, 'p237670000035');
        $phoneContact = new WhatsAppContact();
        $phoneContact->normalized_phone = '250788004793';
        $phoneContact->display_phone = '+250788004793';
        $phoneContact->wa_name = 'PA Nasrah Umwali';
        $phoneContact->save();
        $lidContact = new WhatsAppContact();
        $lidContact->normalized_phone = '155512345465';
        $lidContact->display_phone = '155512345465';
        $lidContact->wa_name = 'Nasrah';
        $lidContact->save();
        $phoneChat = $this->chat($phoneContact, $session, 'PA Nasrah Umwali');
        $phoneChat->last_message = 'good afternoon sir';
        $phoneChat->last_activity_at = '2026-11-27 10:00:00';
        $phoneChat->save();
        $lidChat = $this->chat($lidContact, $session, 'Nasrah');
        $lidChat->last_message = 'good afternoon sir';
        $lidChat->last_activity_at = '2026-11-28 14:25:00';
        $lidChat->unread_count = 2;
        $lidChat->save();
        $otherChat = $this->chat($lidContact, $other, 'Nasrah');
        $kept = new WhatsAppMessage();
        $kept->conversation_id = $lidChat->id;
        $kept->contact_id = $lidContact->id;
        $kept->direction = WhatsAppMessage::DIR_IN;
        $kept->type = 'TEXT';
        $kept->body = 'kept on the phone chat';
        $kept->provider_message_id = 'mai-only-lid';
        $kept->save();
        MaiPeer::create([
            'mai_session_id' => $session->id,
            'remote_jid' => '155512345465@lid',
            'phone_digits' => '155512345465',
            'lid' => '155512345465',
            'saved_name' => 'Nasrah',
            'profile_name' => 'Nasrah',
        ]);
        MaiPeer::create([
            'mai_session_id' => $session->id,
            'remote_jid' => '250788004793@s.whatsapp.net',
            'phone_digits' => '250788004793',
            'lid' => '155512345465',
            'saved_name' => 'PA Nasrah Umwali',
            'profile_name' => 'Nasrah',
        ]);

        app(\App\Services\Mai\PersonalHub::class)->foldDuplicates($session);

        $this->assertSame(1, WhatsAppConversation::where('mai_session_id', $session->id)->count());
        $this->assertNull(WhatsAppConversation::find($lidChat->id));
        $phone = WhatsAppConversation::find($phoneChat->id);
        $this->assertSame('PA Nasrah Umwali', $phone->peer_label);
        $this->assertSame(2, (int) $phone->unread_count);
        $this->assertSame(1, WhatsAppMessage::where('conversation_id', $phone->id)->count());
        $this->assertSame('kept on the phone chat', WhatsAppMessage::where('provider_message_id', 'mai-only-lid')->value('body'));
        $this->assertSame($phone->id, (int) WhatsAppMessage::where('provider_message_id', 'mai-only-lid')->value('conversation_id'));
        $this->assertNotNull(WhatsAppConversation::find($otherChat->id));
        $this->assertSame('Nasrah', $otherChat->fresh()->peer_label);
    }

    public function test_a_scoped_repair_cannot_see_another_session_when_queries_are_isolated()
    {
        config(['cloud.isolate_queries' => true]);
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->unsignedInteger('cloud_tenant_id')->nullable();
        });
        $built = app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () {
            $ada = $this->linked(41, 'p237670000041');
            $bola = $this->linked(42, 'p237670000042');
            $contact = $this->contact('Hidden');
            app(MaiIdentity::class)->remember($ada, [
                'jid' => '237699000333@s.whatsapp.net',
                'phone' => '237699000333',
                'saved_name' => 'Ada Only',
            ]);

            return [
                $ada,
                $this->chat($contact, $ada, 'Old Ada'),
                $this->chat($contact, $bola, 'Old Bola'),
            ];
        });
        $ada = $built[0];
        $adaChat = $built[1];
        $bolaChat = $built[2];
        $this->assertSame(0, WhatsAppConversation::query()->count());
        $this->artisan('mai:repair-labels', ['--session' => $ada->id, '--dry-run' => true])
            ->expectsOutput('examined=1')
            ->expectsOutput('propose id='.$adaChat->id.' from=Old Ada to=Ada Only')
            ->assertExitCode(0);
        $this->assertSame('Old Ada', WhatsAppConversation::withoutGlobalScopes()->find($adaChat->id)->peer_label);
        $this->assertSame('Old Bola', WhatsAppConversation::withoutGlobalScopes()->find($bolaChat->id)->peer_label);
    }

    public function test_a_lid_is_not_turned_into_a_phone_number()
    {
        $contact = $this->contact('');
        $contact->normalized_phone = '1555123499999999';
        $contact->wa_name = 'Owner Book';
        $contact->save();
        $session = $this->linked(3, 'p237670000333');
        $chat = $this->chat($contact, $session, 'Owner Book');
        app(MaiIdentity::class)->remember($session, [
            'jid' => '1555123499999999@lid',
            'lid' => '1555123499999999',
            'profile_name' => 'Lid Profile',
        ]);

        $peer = MaiPeer::where('mai_session_id', $session->id)->first();
        $this->assertSame('', (string) $peer->phone_digits);
        $this->assertSame('Lid Profile', app(MaiIdentity::class)->display($chat->fresh(), $contact));
    }

    public function test_repair_rebuilds_trusted_labels_and_leaves_the_business_book()
    {
        $contact = $this->contact('Owner Book');
        $ada = $this->linked(4, 'p237670000444');
        $bola = $this->linked(9, 'p237670000999');
        $same = $this->linked(10, 'p237670001010');
        $adaChat = $this->chat($contact, $ada, 'Owner Book');
        $bolaChat = $this->chat($contact, $bola, 'Owner Book');
        $sameChat = $this->chat($contact, $same, 'Owner Book');
        $business = new WhatsAppConversation();
        $business->contact_id = $contact->id;
        $business->peer_label = 'Owner Book';
        $business->mode = 'HUMAN';
        $business->status = 'OPEN';
        $business->save();
        app(MaiIdentity::class)->remember($ada, [
            'jid' => '237699000333@s.whatsapp.net',
            'phone' => '237699000333',
            'saved_name' => 'Ada Name',
        ]);
        app(MaiIdentity::class)->remember($bola, [
            'jid' => '237699000333@s.whatsapp.net',
            'phone' => '237699000333',
        ]);
        app(MaiIdentity::class)->remember($same, [
            'jid' => '237699000333@s.whatsapp.net',
            'phone' => '237699000333',
            'saved_name' => 'Owner Book',
        ]);
        Cache::put('wa.book.237699000333', 'Owner Book', now()->addDay());
        Cache::put('wa.address_book.v2', ['237699000333' => 'Owner Book'], now()->addDay());

        $this->artisan('mai:repair-labels', ['--dry-run' => true])
            ->expectsOutput('Pass --session=ID so the repair stays on one connection.')
            ->assertExitCode(1);
        $this->artisan('mai:repair-labels', ['--dry-run' => true, '--session' => $ada->id])
            ->expectsOutput('examined=1')
            ->expectsOutput('rebuilt=1 cleared=0 kept=0 dry=yes')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--dry-run' => true, '--session' => $bola->id])
            ->expectsOutput('examined=1')
            ->expectsOutput('rebuilt=0 cleared=1 kept=0 dry=yes')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--dry-run' => true, '--session' => $same->id])
            ->expectsOutput('examined=1')
            ->expectsOutput('rebuilt=0 cleared=0 kept=1 dry=yes')
            ->assertExitCode(0);
        $this->assertSame('Owner Book', $adaChat->fresh()->peer_label);
        $this->assertSame('Owner Book', $contact->fresh()->wa_name);
        $this->assertSame('Owner Book', $contact->fresh()->displayName());
        $this->assertSame('Owner Book', $business->fresh()->peer_label);
        $this->assertSame('Owner Book', Cache::get('wa.book.237699000333'));
        $this->assertSame(['237699000333' => 'Owner Book'], Cache::get('wa.address_book.v2'));

        $this->artisan('mai:repair-labels', ['--session' => $ada->id])
            ->expectsOutput('rebuilt=1 cleared=0 kept=0 dry=no')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--session' => $bola->id])
            ->expectsOutput('rebuilt=0 cleared=1 kept=0 dry=no')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--session' => $same->id])
            ->expectsOutput('rebuilt=0 cleared=0 kept=1 dry=no')
            ->assertExitCode(0);
        $this->assertSame('Ada Name', $adaChat->fresh()->peer_label);
        $this->assertSame('+237699000333', $bolaChat->fresh()->peer_label);
        $this->assertSame('Owner Book', $sameChat->fresh()->peer_label);
        $this->assertSame('Owner Book', $contact->fresh()->wa_name);
        $this->assertSame('Owner Book', $business->fresh()->peer_label);
        $this->assertSame('Owner Book', Cache::get('wa.book.237699000333'));
        $this->assertSame(['237699000333' => 'Owner Book'], Cache::get('wa.address_book.v2'));

        $this->artisan('mai:repair-labels', ['--session' => $ada->id])
            ->expectsOutput('rebuilt=0 cleared=0 kept=1 dry=no')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--session' => $bola->id])
            ->expectsOutput('rebuilt=0 cleared=0 kept=1 dry=no')
            ->assertExitCode(0);
        $this->artisan('mai:repair-labels', ['--session' => $same->id])
            ->expectsOutput('rebuilt=0 cleared=0 kept=1 dry=no')
            ->assertExitCode(0);
        $this->assertSame('Owner Book', Cache::get('wa.book.237699000333'));
    }

    public function test_history_does_not_reply_and_a_live_message_still_can()
    {
        $contact = $this->contact('');
        $session = $this->linked(5, 'p237670000555');
        $session->ai_enabled = 1;
        $session->save();
        $chat = $this->chat($contact, $session, '');
        $chat->mode = 'AI';
        $chat->save();
        $calls = 0;
        config(['cloud.mai_customer.ai_reply' => function () use (&$calls) {
            $calls++;

            return 'auto';
        }]);

        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'old-1',
            'live' => false,
            'remote' => '237699000333',
            'jid' => '237699000333@s.whatsapp.net',
            'text' => 'Imported',
        ]);
        $this->assertSame(0, $calls);
        $this->assertSame(1, WhatsAppMessage::count());
        $this->assertSame(0, (int) $chat->fresh()->unread_count);

        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'old-1',
            'live' => false,
            'remote' => '237699000333',
            'text' => 'Imported',
        ]);
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'live-2',
            'live' => true,
            'remote' => '237699000333',
            'text' => 'Second',
        ]);
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'live-1',
            'live' => true,
            'remote' => '237699000333',
            'text' => 'First',
        ]);
        $this->assertSame(2, $calls);
        $this->assertSame(3, WhatsAppMessage::count());
        $this->assertSame(2, (int) $chat->fresh()->unread_count);

        $chat->mode = 'HUMAN';
        $chat->save();
        app(MaiInboxStore::class)->accept([
            'session_id' => $session->worker_session_id,
            'event_id' => 'live-3',
            'live' => true,
            'remote' => '237699000333',
            'text' => 'After takeover',
        ]);
        $this->assertSame(2, $calls);
    }

    public function test_history_stays_incomplete_until_the_batch_is_stored()
    {
        self::$hooks = 0;
        $this->app->bind(\App\Services\Mai\MaiWebhookService::class, function () {
            return new class extends \App\Services\Mai\MaiWebhookService {
                public function dispatch($session, $eventId, array $payload)
                {
                    \Tests\Feature\MaiContactIsolationTest::$hooks++;
                }
            };
        });
        $session = $this->linked(11, 'p237670001111');
        $session->ai_enabled = 1;
        $session->sync_generation = 1;
        $session->sync_seq = 0;
        $session->history_ready = 0;
        $session->save();
        $calls = 0;
        config(['cloud.mai_customer.ai_reply' => function () use (&$calls) {
            $calls++;

            return 'auto';
        }]);
        $chat = $this->chat($this->contact(''), $session, '');
        $chat->mode = 'AI';
        $chat->save();
        $old = time() - 86400;
        $sync = app(\App\Services\Mai\MaiSync::class);
        $sync->ingest($session, [
            $this->historyMessage(1, 'h1', 'Old', $old),
            ['generation' => 1, 'seq' => 2, 'kind' => 'history', 'progress' => 40, 'ready' => true],
        ], 1);

        $session = $session->fresh();
        $this->assertSame(0, (int) $session->history_ready);
        $this->assertSame(40, (int) Cache::get('mai.sync.'.$session->id));
        $this->assertSame(0, $calls);
        $this->assertSame(0, self::$hooks);
        $this->assertSame(1, WhatsAppMessage::count());

        $sync->ingest($session, [
            $this->historyMessage(1, 'h1', 'Old', $old),
            ['generation' => 1, 'seq' => 3, 'kind' => 'message', 'id' => 'live-9', 'live' => true, 'remote' => '237699000333', 'jid' => '237699000333@s.whatsapp.net', 'text' => 'Now', 'at' => time()],
            $this->historyMessage(4, 'h2', 'Older still', $old - 30),
            ['generation' => 1, 'seq' => 5, 'kind' => 'history-page', 'jid' => '237699000333@s.whatsapp.net', 'state' => 'received', 'count' => 1],
            ['generation' => 1, 'seq' => 6, 'kind' => 'history', 'progress' => 100, 'ready' => true],
        ], 1);

        $session = $session->fresh();
        $this->assertSame(1, (int) $session->history_ready);
        $this->assertSame(1, $calls);
        $this->assertSame(1, self::$hooks);
        $this->assertSame(3, WhatsAppMessage::count());
        $this->assertSame('received', Cache::get('mai.older.'.$session->id.'.237699000333'));
        $older = WhatsAppMessage::where('body', 'Older still')->first();
        $live = WhatsAppMessage::where('body', 'Now')->first();
        $this->assertTrue($older->created_at->lt($live->created_at));

        $sync->ingest($session->fresh(), [[
            'generation' => 1,
            'seq' => 7,
            'kind' => 'history-page',
            'jid' => '237688000111@s.whatsapp.net',
            'state' => 'failed',
        ]], 1);
        $this->assertSame('failed', Cache::get('mai.older.'.$session->id.'.237688000111'));
        $this->assertSame(1, (int) $session->fresh()->history_ready);
    }

    public function test_a_file_flag_does_not_finish_history_before_the_batch()
    {
        $session = $this->linked(12, 'p237670001212');
        $session->phone_number = '237670001212';
        $session->sync_generation = 1;
        $session->sync_seq = 0;
        $session->history_ready = 0;
        $session->save();
        config(['cloud.whatsapp.link_transport' => function () {
            return [
                'generation' => 1,
                'history_ready' => true,
                'events' => [
                    ['generation' => 1, 'seq' => 1, 'kind' => 'history', 'progress' => 40, 'ready' => false],
                ],
            ];
        }]);
        app(\App\Services\Mai\MaiSync::class)->pull($session->fresh());
        $this->assertSame(0, (int) $session->fresh()->history_ready);
        $this->assertSame(40, (int) Cache::get('mai.sync.'.$session->id));

        $phone = '237670001212';
        config(['cloud.whatsapp.link_transport' => function ($method, $path) use ($phone) {
            if (strpos($path, '/history') !== false) {
                if (strpos($path, 'p'.$phone) === false) {
                    return ['state' => 'failed'];
                }

                return ['state' => 'pending'];
            }

            return ['state' => 'unavailable'];
        }]);
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $this->assertSame('pending', $engine->olderHistory($phone, '237699000333@s.whatsapp.net', ['id' => 'abc'])['state']);
        $this->assertSame('unavailable', $engine->olderHistory($phone, '237699000333@s.whatsapp.net', [])['state']);
        $this->assertSame('failed', $engine->olderHistory('237670009999', '237699000333@s.whatsapp.net', ['id' => 'abc'])['state']);
    }

    public function test_groups_quotes_receipts_and_media_stay_on_that_connection()
    {
        $ada = $this->linked(6, 'p237670000666');
        $bola = $this->linked(7, 'p237670000777');
        $ada->ai_enabled = 1;
        $ada->save();
        $calls = 0;
        config(['cloud.mai_customer.ai_reply' => function () use (&$calls) {
            $calls++;

            return 'auto';
        }]);
        $jid = '120363123456789012@g.us';
        app(MaiInboxStore::class)->accept([
            'session_id' => $ada->worker_session_id,
            'event_id' => 'g-1',
            'id' => 'g-1',
            'live' => true,
            'jid' => $jid,
            'saved_name' => 'Ada Group',
            'participant' => '237611111111@s.whatsapp.net',
            'participant_saved' => 'Ada Friend',
            'text' => 'In the group',
            'quote' => 'Earlier line',
            'type' => 'IMAGE',
        ]);
        app(MaiInboxStore::class)->accept([
            'session_id' => $bola->worker_session_id,
            'event_id' => 'g-1',
            'id' => 'g-1',
            'live' => false,
            'jid' => $jid,
            'saved_name' => 'Bola Group',
            'participant' => '237611111111@s.whatsapp.net',
            'participant_saved' => 'Bola Friend',
            'text' => 'In the group',
        ]);
        $this->assertSame(0, $calls);
        $adaChat = WhatsAppConversation::where('mai_session_id', $ada->id)->first();
        $bolaChat = WhatsAppConversation::where('mai_session_id', $bola->id)->first();
        $this->assertSame('Ada Group', app(MaiIdentity::class)->display($adaChat, $adaChat->contact));
        $this->assertSame('Bola Group', app(MaiIdentity::class)->display($bolaChat, $bolaChat->contact));
        $adaMessage = WhatsAppMessage::where('conversation_id', $adaChat->id)->first();
        $this->assertSame('Earlier line', $adaMessage->quoteText());
        $this->assertTrue(! empty($adaMessage->media()['unavailable']));
        $this->assertSame('Ada Friend', $adaMessage->media()['participant_label']);
        $this->assertSame('Bola Friend', WhatsAppMessage::where('conversation_id', $bolaChat->id)->first()->media()['participant_label']);

        $dir = storage_path('app/whatsapp-link/sessions/'.$ada->worker_session_id.'/media');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir.'/pic.jpg', 'picture');
        app(MaiInboxStore::class)->accept([
            'session_id' => $ada->worker_session_id,
            'event_id' => 'pic-1',
            'id' => 'pic-1',
            'live' => false,
            'remote' => '237699000333',
            'jid' => '237699000333@s.whatsapp.net',
            'text' => 'Photo',
            'type' => 'IMAGE',
            'file' => 'sessions/'.$ada->worker_session_id.'/media/pic.jpg',
        ]);
        $photo = WhatsAppMessage::where('provider_message_id', 'mai:'.$ada->id.':pic-1')->first();
        $this->assertSame('whatsapp-media/mai/'.$ada->id.'/pic.jpg', $photo->media()['stored']);
        $this->assertTrue(empty($photo->media()['unavailable']));
        $this->assertTrue(is_file(storage_path('app/'.$photo->media()['stored'])));

        $photo->direction = WhatsAppMessage::DIR_OUT;
        $photo->status = WhatsAppMessage::STATUS_SENT;
        $photo->save();
        app(MaiInboxStore::class)->accept([
            'session_id' => $ada->worker_session_id,
            'event_id' => 'rcpt-pic-1',
            'kind' => 'receipt',
            'id' => 'pic-1',
            'receipt' => 4,
        ]);
        $this->assertSame(WhatsAppMessage::STATUS_READ, $photo->fresh()->status);
        @unlink($dir.'/pic.jpg');
        @unlink(storage_path('app/'.$photo->media()['stored']));
    }

    public function test_a_message_already_stored_does_not_stop_the_chat()
    {
        $session = $this->linked(9, 'p2376700009');
        $other = $this->contact('Already stored');
        $chat = $this->chat($other, $session, 'Already stored');
        $kept = new WhatsAppMessage();
        $kept->conversation_id = $chat->id;
        $kept->contact_id = $other->id;
        $kept->direction = WhatsAppMessage::DIR_OUT;
        $kept->type = 'TEXT';
        $kept->provider_message_id = 'DUPCHAT1';
        $kept->body = 'Keep this';
        $kept->status = WhatsAppMessage::STATUS_SENT;
        $kept->sender_type = 'ECHO';
        $kept->save();
        config(['cloud.whatsapp.link_transport' => function ($method, $path, $body) {
            if (substr($path, -9) === '/messages') {
                return ['messages' => [[
                    'id' => 'DUPCHAT1',
                    'from' => '237699000999@s.whatsapp.net',
                    'from_me' => true,
                    'text' => 'Keep this',
                    'at' => (string) time(),
                    'receipt' => 2,
                ]]];
            }

            return ['status' => 'CONNECTED'];
        }]);

        app(\App\Services\Mai\PersonalHub::class)->sync($session);

        $this->assertSame(1, WhatsAppMessage::where('provider_message_id', 'DUPCHAT1')->count());
    }

    public function test_unread_on_mai_matches_the_phone()
    {
        $readContact = $this->contact('Read Person');
        $session = $this->linked(12, 'p23767000012');
        $read = $this->chat($readContact, $session, 'Read Person');
        $read->unread_count = 6;
        $read->save();
        $waiting = new WhatsAppContact();
        $waiting->normalized_phone = '237688000444';
        $waiting->display_phone = '+237688000444';
        $waiting->wa_name = 'Waiting Person';
        $waiting->save();
        $open = $this->chat($waiting, $session, 'Waiting Person');
        $open->unread_count = 9;
        $open->save();
        $leftoverContact = new WhatsAppContact();
        $leftoverContact->normalized_phone = '237611000555';
        $leftoverContact->display_phone = '+237611000555';
        $leftoverContact->wa_name = 'Old Count';
        $leftoverContact->save();
        $leftover = $this->chat($leftoverContact, $session, 'Old Count');
        $leftover->unread_count = 4;
        $leftover->save();
        config(['cloud.whatsapp.link_transport' => function ($method, $path) {
            if (strpos($path, '/chats') !== false) {
                return [
                    'read_ready' => true,
                    'chats' => [
                    [
                        'jid' => '237699000333@s.whatsapp.net',
                        'phone' => '237699000333',
                        'saved_name' => 'Read Person',
                        'preview' => 'Seen',
                        'unread' => 0,
                        'at' => time(),
                    ],
                    [
                        'jid' => '237688000444@s.whatsapp.net',
                        'phone' => '237688000444',
                        'saved_name' => 'Waiting Person',
                        'preview' => 'Hello',
                        'unread' => -1,
                        'at' => time(),
                    ],
                    ],
                ];
            }
            if (strpos($path, '/messages') !== false) {
                return ['messages' => [[
                    'id' => 'old-read',
                    'from' => '237699000333@s.whatsapp.net',
                    'from_me' => false,
                    'text' => 'Already read on the phone',
                    'at' => (string) time(),
                ]]];
            }

            return ['generation' => 1, 'events' => []];
        }]);

        app(\App\Services\Mai\PersonalHub::class)->sync($session, false);

        $this->assertSame(0, (int) $read->fresh()->unread_count);
        $this->assertSame(1, (int) $open->fresh()->unread_count);
        $this->assertSame(0, (int) $leftover->fresh()->unread_count);
        $this->assertSame('Read Person', app(\App\Services\Mai\PersonalHub::class)->label($readContact->fresh(), $read->fresh()));
    }

    public function test_a_saved_contact_with_no_messages_appears()
    {
        $session = $this->linked(13, 'p23767000013');
        config(['cloud.whatsapp.link_transport' => function ($method, $path) {
            if (strpos($path, '/contacts') !== false) {
                return ['contacts' => [
                    [
                        'phone' => '250788000111',
                        'saved_name' => 'Nasrah',
                        'name' => 'Nasrah',
                        'saved' => true,
                    ],
                    [
                        'phone' => '237655000222',
                        'profile_name' => 'Push Only',
                        'name' => 'Push Only',
                        'saved' => false,
                    ],
                    [
                        'phone' => '237675321739',
                        'saved_name' => 'Business Line',
                        'saved' => true,
                    ],
                ]];
            }
            if (strpos($path, '/chats') !== false) {
                return ['read_ready' => true, 'chats' => []];
            }

            return ['generation' => 1, 'events' => []];
        }]);

        app(\App\Services\Mai\PersonalHub::class)->sync($session, false);

        $contact = WhatsAppContact::where('normalized_phone', '250788000111')->first();
        $this->assertNotNull($contact);
        $chat = WhatsAppConversation::where('mai_session_id', $session->id)
            ->where('contact_id', $contact->id)
            ->first();
        $this->assertNotNull($chat);
        $this->assertNull($chat->last_activity_at);
        $this->assertSame(0, (int) $chat->unread_count);
        $this->assertSame('Nasrah', app(\App\Services\Mai\PersonalHub::class)->label($contact, $chat));
        $this->assertNull(WhatsAppContact::where('normalized_phone', '237655000222')->first());
        $this->assertNull(WhatsAppContact::where('normalized_phone', '237675321739')->first());
    }

    public function test_a_shorter_address_book_rename_replaces_the_stored_label()
    {
        $session = $this->linked(14, 'p23767000014');
        $contact = new WhatsAppContact();
        $contact->normalized_phone = '250788001589';
        $contact->display_phone = '+250788001589';
        $contact->wa_name = 'Tefu Ngwayu';
        $contact->save();
        $chat = $this->chat($contact, $session, 'Tefu Ngwayu');
        app(MaiIdentity::class)->remember($session, [
            'jid' => '250788001589@s.whatsapp.net',
            'phone' => '250788001589',
            'saved_name' => 'Tefu Ngwayu',
        ]);
        config(['cloud.whatsapp.link_transport' => function ($method, $path) {
            if (strpos($path, '/contacts') !== false) {
                return ['contacts' => [[
                    'phone' => '250788001589',
                    'saved_name' => 'Don Hump',
                    'name' => 'Don Hump',
                    'saved' => true,
                ]]];
            }
            if (strpos($path, '/chats') !== false) {
                return ['read_ready' => true, 'chats' => []];
            }

            return ['generation' => 1, 'events' => []];
        }]);

        app(\App\Services\Mai\PersonalHub::class)->sync($session, false);

        $peer = MaiPeer::where('mai_session_id', $session->id)->where('remote_jid', '250788001589@s.whatsapp.net')->first();
        $this->assertSame('Don Hump', $peer->saved_name);
        $this->assertSame('Don Hump', $chat->fresh()->peer_label);
        $this->assertSame(1, WhatsAppConversation::where('mai_session_id', $session->id)->count());
    }

    public function test_an_edit_uses_the_linked_socket()
    {
        $contact = $this->contact('Owner Book');
        $session = $this->linked(8, 'p237670000888');
        $session->phone_number = '237670000888';
        $session->save();
        $chat = $this->chat($contact, $session, '');
        $message = new WhatsAppMessage();
        $message->conversation_id = $chat->id;
        $message->contact_id = $contact->id;
        $message->direction = WhatsAppMessage::DIR_OUT;
        $message->type = 'TEXT';
        $message->body = 'Hello';
        $message->status = WhatsAppMessage::STATUS_SENT;
        $message->media_json = json_encode(['key' => ['id' => 'abc', 'from_me' => true]]);
        $message->sent_at = now();
        $message->save();
        $seen = [];
        config(['cloud.whatsapp.link_transport' => function ($method, $path) use (&$seen) {
            $seen[] = $method.' '.$path;

            return ['success' => true];
        }]);
        $result = app(\App\Services\WhatsApp\WhatsAppConversationService::class)->editOutgoing($chat, $message->fresh(), 'Hello there');
        $this->assertTrue(! empty($result['success']));
        $this->assertSame('Hello there', $message->fresh()->body);
        $this->assertContains('POST /sessions/p237670000888/edit', $seen);
    }

    protected function historyMessage($seq, $id, $text, $at)
    {
        return [
            'generation' => 1,
            'seq' => $seq,
            'kind' => 'message',
            'id' => $id,
            'live' => false,
            'remote' => '237699000333',
            'jid' => '237699000333@s.whatsapp.net',
            'text' => $text,
            'at' => $at,
        ];
    }

    protected function contact($name)
    {
        $contact = new WhatsAppContact();
        $contact->normalized_phone = '237699000333';
        $contact->display_phone = '+237699000333';
        $contact->wa_name = $name;
        $contact->save();

        return $contact;
    }

    protected function linked($id, $worker)
    {
        $session = new MaiSession();
        $session->id = $id;
        $session->public_id = 'c'.$id;
        $session->user_id = $id;
        $session->cloud_tenant_id = 9;
        $session->provider = 'mai';
        $session->worker_session_id = $worker;
        $session->phone_number = '237670000'.$id;
        $session->status = 'CONNECTED';
        $session->save();

        return $session;
    }

    protected function chat(WhatsAppContact $contact, MaiSession $session, $label)
    {
        $chat = new WhatsAppConversation();
        $chat->contact_id = $contact->id;
        $chat->mai_session_id = $session->id;
        $chat->peer_label = $label;
        $chat->mode = 'HUMAN';
        $chat->status = 'OPEN';
        $chat->save();

        return $chat;
    }
}
