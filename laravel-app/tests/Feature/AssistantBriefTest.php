<?php

namespace Tests\Feature;

use App\Assistant\AssistantBrief;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use App\Services\Assistant\AssistantBriefService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssistantBriefTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_01_160000_create_cloud_platform_foundation.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_03_160000_create_assistant_briefs.php', '--force' => true]);
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('mode')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        app(CloudTenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_a_company_brief_is_used_only_for_that_company()
    {
        $beyond = CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => 'ACTIVE',
        ]);
        $other = CloudTenant::create([
            'name' => 'Alpha Bridge',
            'slug' => 'alpha-bridge',
            'type' => CloudTenantType::CUSTOMER,
            'status' => 'ACTIVE',
        ]);
        AssistantBrief::create([
            'cloud_tenant_id' => $beyond->id,
            'title' => '72 hours of praise',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addDays(3),
            'details' => 'Doors open at 6 at the church hall.',
            'enabled' => true,
        ]);
        AssistantBrief::create([
            'cloud_tenant_id' => $other->id,
            'title' => 'Alpha private install',
            'details' => 'Only Alpha should hear this.',
            'enabled' => true,
        ]);
        AssistantBrief::create([
            'cloud_tenant_id' => $beyond->id,
            'title' => 'Old trip',
            'ends_at' => now()->subDay(),
            'details' => 'This trip is finished.',
            'enabled' => true,
        ]);

        app(CloudTenantContext::class)->set($beyond);
        $text = app(AssistantBriefService::class)->promptText();
        $this->assertStringContainsString('72 hours of praise', $text);
        $this->assertStringContainsString('church hall', $text);
        $this->assertStringNotContainsString('Alpha private install', $text);
        $this->assertStringNotContainsString('finished', $text);

        app(CloudTenantContext::class)->set($other);
        $alpha = app(AssistantBriefService::class)->promptText();
        $this->assertStringContainsString('Alpha private install', $alpha);
        $this->assertStringNotContainsString('72 hours of praise', $alpha);
    }

    public function test_deleting_a_conversation_removes_its_messages()
    {
        $tenant = CloudTenant::create([
            'name' => 'BeyondTechWorld',
            'slug' => 'beyondtechworld',
            'type' => CloudTenantType::INTERNAL,
            'status' => 'ACTIVE',
        ]);
        app(CloudTenantContext::class)->set($tenant);
        $conversation = new WhatsAppConversation();
        $conversation->mode = 'HUMAN';
        $conversation->status = 'OPEN';
        $conversation->save();
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversation->id,
            'body' => 'Hello',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(WhatsAppConversationService::class)->destroy($conversation);
        $this->assertSame(0, WhatsAppConversation::count());
        $this->assertSame(0, WhatsAppMessage::count());
    }
}
