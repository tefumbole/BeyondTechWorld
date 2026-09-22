<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappHubPhase3 extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('assistant_knowledge')) {
            Schema::create('assistant_knowledge', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 191);
                $table->string('category', 48)->default('general')->index();
                $table->text('content');
                $table->boolean('enabled')->default(true)->index();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assistant_memories')) {
            Schema::create('assistant_memories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->unique();
                $table->string('active_intent', 64)->nullable();
                $table->text('parameters_json')->nullable();
                $table->text('missing_json')->nullable();
                $table->string('last_tool', 64)->nullable();
                $table->text('last_tool_result')->nullable();
                $table->unsignedTinyInteger('clarification_count')->default(0);
                $table->text('suggested_reply')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assistant_activities')) {
            Schema::create('assistant_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('message_id')->nullable()->index();
                $table->string('incoming_fingerprint', 64)->nullable()->unique();
                $table->string('intent', 64)->nullable()->index();
                $table->decimal('confidence', 5, 2)->nullable();
                $table->string('action', 48)->nullable();
                $table->string('tools_requested', 191)->nullable();
                $table->string('tools_executed', 191)->nullable();
                $table->string('tool_status', 32)->nullable();
                $table->text('response_preview')->nullable();
                $table->boolean('sent')->default(false);
                $table->string('provider', 32)->nullable();
                $table->string('model', 64)->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('handover_reason', 191)->nullable();
                $table->string('status', 24)->default('started')->index();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        $this->seedPermissions();
        $this->seedSettings();
        $this->seedKnowledge();
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp.ai',
            'whatsapp.ai.manage',
            'whatsapp.ai.knowledge',
            'whatsapp.ai.tools',
            'whatsapp.ai.activity',
            'whatsapp.ai.suggest',
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

    private function seedSettings()
    {
        if (! Schema::hasTable('whatsapp_settings')) {
            return;
        }
        if (! \App\WhatsApp\WhatsAppSetting::where('key', 'assistant_enabled')->exists()) {
            \App\WhatsApp\WhatsAppSetting::putValue('assistant_enabled', '0');
        }
    }

    private function seedKnowledge()
    {
        if (\App\Assistant\AssistantKnowledge::query()->exists()) {
            return;
        }
        $rows = [
            ['company', 'Company', 'Beyond Enterprise (BeyondTechWorld) provides event production, audio, lighting, LED screens, equipment rental, IT support, networking, CCTV, software, training, and internship programmes in Cameroon. WhatsApp: +237 675 321 739. Website: https://beyondtechworld.com'],
            ['services', 'Services', 'Core services: equipment rental (sound, lighting, LED), event production, IT support, networking, CCTV, cybersecurity awareness, software development, professional training, and student internships.'],
            ['hours', 'Business hours', 'Staff typically respond during Cameroon business hours, Monday to Saturday. WhatsApp messages received after hours are stored and answered as soon as a team member is available.'],
            ['rental', 'Rental process', 'For sound, lighting or LED rental we collect event type, date, location and audience size, then a staff member confirms equipment and sends a quotation from the existing ERP quotation module. The assistant does not create bookings or quotations automatically.'],
            ['internship', 'Internship process', 'Internship applicants apply through BeyondTechWorld. Accepted interns receive tasks in the internship portal. The assistant can state the current released task and general progress. Work is not submitted or graded on WhatsApp.'],
            ['support', 'Support process', 'For complaints, payments, contracts or anything uncertain, ask to speak with staff. A team member will take over the WhatsApp conversation.'],
        ];
        foreach ($rows as $row) {
            \App\Assistant\AssistantKnowledge::create([
                'category' => $row[0],
                'title' => $row[1],
                'content' => $row[2],
                'enabled' => true,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('assistant_activities');
        Schema::dropIfExists('assistant_memories');
        Schema::dropIfExists('assistant_knowledge');
    }
}
