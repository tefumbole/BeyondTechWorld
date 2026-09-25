<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappOwnerAndGroups extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_owner_users')) {
            Schema::create('whatsapp_owner_users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id')->index();
                $table->string('normalized_phone', 32)->index();
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'normalized_phone']);
            });
        }
        if (! Schema::hasTable('whatsapp_groups')) {
            Schema::create('whatsapp_groups', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('group_jid', 191)->unique();
                $table->string('name', 191)->nullable();
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(false);
                $table->string('mode', 32)->default('OFF');
                $table->string('category', 64)->nullable();
                $table->string('organization', 191)->nullable();
                $table->unsignedInteger('raw_retention_days')->default(30);
                $table->unsignedInteger('summary_retention_days')->default(180);
                $table->unsignedInteger('action_retention_days')->default(365);
                $table->boolean('allow_inventory')->default(false);
                $table->boolean('allow_events')->default(false);
                $table->boolean('allow_finance')->default(false);
                $table->boolean('allow_payroll')->default(false);
                $table->boolean('allow_internship')->default(false);
                $table->text('memory_json')->nullable();
                $table->timestamp('last_summary_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_group_messages')) {
            Schema::create('whatsapp_group_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_id')->index();
                $table->string('provider_message_id', 191)->unique();
                $table->string('participant_phone', 32)->nullable()->index();
                $table->string('participant_jid', 191)->nullable();
                $table->string('participant_name', 191)->nullable();
                $table->text('body')->nullable();
                $table->text('derived_text')->nullable();
                $table->string('derived_label', 32)->nullable();
                $table->string('reply_to_message_id', 191)->nullable();
                $table->text('media_json')->nullable();
                $table->timestamp('message_at')->nullable()->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_group_participants')) {
            Schema::create('whatsapp_group_participants', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_id')->index();
                $table->string('phone', 32)->nullable()->index();
                $table->string('jid', 191)->nullable();
                $table->string('display_name', 191)->nullable();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->timestamps();
                $table->unique(['group_id', 'phone']);
            });
        }
        if (! Schema::hasTable('whatsapp_group_actions')) {
            Schema::create('whatsapp_group_actions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_id')->index();
                $table->unsignedBigInteger('message_id')->nullable()->index();
                $table->text('description');
                $table->string('responsible_name', 191)->nullable();
                $table->string('due_text', 64)->nullable();
                $table->string('kind', 32)->default('ACTION');
                $table->string('status', 32)->default('SUGGESTED');
                $table->string('confidence', 16)->default('medium');
                $table->unsignedInteger('task_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_group_audits')) {
            Schema::create('whatsapp_group_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->string('type', 64)->index();
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('permissions')) {
            $perm = Permission::firstOrCreate(['name' => 'whatsapp.owner', 'guard_name' => 'web']);
            foreach (Role::whereIn('id', [1, 2])->get() as $role) {
                try {
                    $role->givePermissionTo($perm);
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_group_audits');
        Schema::dropIfExists('whatsapp_group_actions');
        Schema::dropIfExists('whatsapp_group_participants');
        Schema::dropIfExists('whatsapp_group_messages');
        Schema::dropIfExists('whatsapp_groups');
        Schema::dropIfExists('whatsapp_owner_users');
    }
}
