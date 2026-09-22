<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappHubFoundation extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('normalized_phone', 32)->unique();
                $table->string('display_phone', 48)->nullable();
                $table->string('wa_name', 191)->nullable();
                $table->timestamp('blocked_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_contact_links')) {
            Schema::create('whatsapp_contact_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('contact_id')->index();
                $table->string('linkable_type', 191);
                $table->unsignedBigInteger('linkable_id');
                $table->string('role', 32)->index();
                $table->timestamps();
                $table->unique(['contact_id', 'linkable_type', 'linkable_id', 'role'], 'wa_contact_link_unique');
                $table->index(['linkable_type', 'linkable_id'], 'wa_contact_link_morph');
            });
        }

        if (! Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('contact_id')->index();
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->string('mode', 16)->default('HUMAN')->index();
                $table->string('status', 16)->default('open')->index();
                $table->unsignedInteger('unread_count')->default(0);
                $table->text('first_message')->nullable();
                $table->text('last_message')->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamp('last_incoming_at')->nullable();
                $table->timestamp('last_outgoing_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('contact_id')->index();
                $table->string('direction', 16)->index();
                $table->string('type', 24)->default('TEXT')->index();
                $table->string('provider_message_id', 128)->nullable();
                $table->text('body')->nullable();
                $table->text('media_json')->nullable();
                $table->string('status', 16)->default('QUEUED')->index();
                $table->string('sender_type', 16)->default('SYSTEM');
                $table->unsignedInteger('sender_user_id')->nullable()->index();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('played_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamps();
                $table->unique('provider_message_id', 'wa_messages_provider_id_unique');
            });
        }

        if (! Schema::hasTable('whatsapp_webhook_events')) {
            Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('provider', 32)->default('wasender');
                $table->string('provider_event_id', 191)->nullable()->index();
                $table->string('fingerprint', 64)->unique();
                $table->string('event_type', 64)->index();
                $table->longText('payload')->nullable();
                $table->boolean('signature_verified')->default(false);
                $table->string('status', 16)->default('RECEIVED')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('processing_error')->nullable();
                $table->timestamp('received_at')->nullable()->index();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_calls')) {
            Schema::create('whatsapp_calls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('provider_call_id', 128)->nullable();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->string('caller_phone', 48)->nullable()->index();
                $table->string('call_type', 16)->default('audio');
                $table->string('status', 32)->default('RECEIVED')->index();
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamp('called_at')->nullable()->index();
                $table->timestamps();
                $table->unique('provider_call_id', 'wa_calls_provider_id_unique');
            });
        }

        if (! Schema::hasTable('whatsapp_settings')) {
            Schema::create('whatsapp_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 64)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp_module',
            'whatsapp.view',
            'whatsapp.manage',
            'whatsapp.conversations',
            'whatsapp.reply',
            'whatsapp.calls',
            'whatsapp.settings',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRoles = Role::whereIn('id', [1, 2])->get();
        foreach ($adminRoles as $role) {
            foreach ($names as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                    // already assigned
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_settings');
        Schema::dropIfExists('whatsapp_calls');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_contact_links');
        Schema::dropIfExists('whatsapp_contacts');
    }
}
