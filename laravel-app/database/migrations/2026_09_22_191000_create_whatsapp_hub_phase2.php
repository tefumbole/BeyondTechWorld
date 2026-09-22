<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappHubPhase2 extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->string('name', 191)->nullable();
                $table->string('normalized_phone', 32)->index();
                $table->string('company', 191)->nullable();
                $table->string('source', 32)->default('WHATSAPP')->index();
                $table->string('category', 48)->nullable()->index();
                $table->text('summary')->nullable();
                $table->string('status', 32)->default('NEW')->index();
                $table->string('priority', 16)->default('NORMAL')->index();
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->text('first_enquiry')->nullable();
                $table->text('latest_enquiry')->nullable();
                $table->timestamp('follow_up_at')->nullable()->index();
                $table->unsignedInteger('converted_customer_id')->nullable()->index();
                $table->timestamp('converted_at')->nullable();
                $table->string('lost_reason', 255)->nullable();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lead_activities')) {
            Schema::create('lead_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lead_id')->index();
                $table->string('type', 48)->index();
                $table->text('body')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->unsignedBigInteger('message_id')->nullable()->index();
                $table->text('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_conversation_events')) {
            Schema::create('whatsapp_conversation_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('type', 48)->index();
                $table->text('body')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->text('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_notes')) {
            Schema::create('whatsapp_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedInteger('author_id')->index();
                $table->text('body');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('whatsapp_calls') && ! Schema::hasColumn('whatsapp_calls', 'lead_id')) {
            Schema::table('whatsapp_calls', function (Blueprint $table) {
                $table->unsignedBigInteger('lead_id')->nullable()->index()->after('contact_id');
            });
        }

        $this->seedPermissions();
        $this->seedSla();
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp.leads',
            'whatsapp.leads.manage',
            'whatsapp.assign',
            'whatsapp.takeover',
            'whatsapp.notes',
            'whatsapp.documents',
        ];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($names as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
    }

    private function seedSla()
    {
        if (! Schema::hasTable('whatsapp_settings')) {
            return;
        }
        $defaults = [
            'sla_normal_minutes' => '30',
            'sla_warning_minutes' => '60',
            'sla_critical_minutes' => '240',
        ];
        foreach ($defaults as $key => $value) {
            if (! \App\WhatsApp\WhatsAppSetting::where('key', $key)->exists()) {
                \App\WhatsApp\WhatsAppSetting::putValue($key, $value);
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp_calls') && Schema::hasColumn('whatsapp_calls', 'lead_id')) {
            Schema::table('whatsapp_calls', function (Blueprint $table) {
                $table->dropColumn('lead_id');
            });
        }
        Schema::dropIfExists('whatsapp_notes');
        Schema::dropIfExists('whatsapp_conversation_events');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
}
