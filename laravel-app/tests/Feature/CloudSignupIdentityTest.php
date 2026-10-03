<?php

namespace Tests\Feature;

use App\Services\Assistant\AssistantContactVoice;
use App\Services\Cloud\CloudSignupIdentity;
use App\Support\SiteMenu;
use App\WhatsApp\WhatsAppContact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CloudSignupIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(1);
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();
        });
        Schema::create('be_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
        });
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('wa_name')->nullable();
            $table->string('display_phone')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('call_name', 80)->nullable();
            $table->string('relationship', 80)->nullable();
            $table->string('preferred_language', 80)->nullable();
            $table->text('voice_note')->nullable();
            $table->timestamps();
        });
    }

    public function test_a_taken_username_is_refused_and_the_same_phone_is_allowed()
    {
        DB::table('users')->insert([
            'name' => 'Staff',
            'username' => 'tefumbole',
            'email' => 'staff@example.com',
            'phone' => '237670000001',
            'password' => 'secret',
            'is_active' => 1,
            'is_deleted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('be_users')->insert(['username' => 'portaluser']);
        $identity = app(CloudSignupIdentity::class);

        $this->assertTrue($identity->usernameTaken('Tefumbole'));
        $this->assertTrue($identity->usernameTaken('portaluser'));
        $this->assertTrue($identity->usernameTaken('Staff'));
        $this->assertFalse($identity->usernameTaken('alpha-bridge'));
        $this->assertFalse($identity->usernameTaken('237670000001'));
    }

    public function test_the_assistant_uses_the_name_you_call_someone()
    {
        $contact = new WhatsAppContact();
        $contact->wa_name = 'Marie Claire';
        $contact->normalized_phone = '237670000099';
        $contact->call_name = 'Mii';
        $contact->relationship = 'my wife';
        $contact->preferred_language = 'French';
        $contact->voice_note = 'Keep it warm and short.';
        $contact->save();

        $voice = app(AssistantContactVoice::class);
        $line = $voice->line($contact);
        $this->assertSame('Mii', $voice->preferredName($contact));
        $this->assertStringContainsString('my wife', $line);
        $this->assertStringContainsString('Mii', $line);
        $this->assertStringContainsString('Marie Claire', $line);
        $this->assertStringContainsString('French', $line);
        $this->assertStringContainsString('warm and short', $line);
    }

    public function test_site_content_lists_the_whatsapp_hub_menu()
    {
        $items = SiteMenu::whatsappItems();
        $this->assertSame('WhatsApp Hub', SiteMenu::sideItems()['whatsapp']);
        $this->assertArrayHasKey('brief', $items);
        $this->assertArrayHasKey('people', $items);
        $this->assertArrayHasKey('conversations', $items);
    }
}
