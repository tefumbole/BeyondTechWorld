<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateOnlineInvitationModule extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('online_invitation_categories')) {
            Schema::create('online_invitation_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->boolean('is_active')->default(1);
            });
        }

        if (! Schema::hasTable('online_invitation_templates')) {
            Schema::create('online_invitation_templates', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->text('background')->nullable();
                $table->boolean('is_active')->default(1);
                $table->integer('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('online_invitation_events')) {
            Schema::create('online_invitation_events', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->dateTime('event_at');
                $table->integer('template_id')->nullable();
                $table->boolean('is_active')->default(1);
                $table->integer('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('online_invitation_event_category')) {
            Schema::create('online_invitation_event_category', function (Blueprint $table) {
                $table->integer('event_id');
                $table->integer('category_id');
                $table->primary(['event_id', 'category_id'], 'oi_event_category_pk');
            });
        }

        if (! Schema::hasTable('online_invitations')) {
            Schema::create('online_invitations', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('event_id')->index();
                $table->integer('category_id')->nullable()->index();
                $table->integer('user_id')->nullable()->index();
                $table->integer('customer_id')->nullable()->index();
                $table->string('recipient_name')->nullable();
                $table->string('recipient_phone', 50)->nullable();
                $table->string('recipient_email')->nullable();
                $table->text('message')->nullable();
                $table->string('rsvp')->nullable();
                $table->string('border_color', 16)->nullable();
                $table->string('font_color', 16)->nullable();
                $table->enum('status', ['awaiting_sending', 'sent', 'failed'])->default('awaiting_sending');
                $table->dateTime('sent_at')->nullable();
                $table->unsignedInteger('send_attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('token', 64)->nullable()->unique();
                $table->string('rsvp_status', 20)->default('pending'); // pending|accepted|declined
                $table->dateTime('rsvp_at')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('used_at')->nullable();
                $table->boolean('is_active')->default(1);
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->index(['status', 'is_active'], 'oi_status_active_idx');
                $table->index(['event_id', 'recipient_phone'], 'oi_event_phone_idx');
                $table->index(['rsvp_status', 'event_id'], 'oi_rsvp_event_idx');
            });
        }

        if (! Schema::hasTable('online_invitation_request_links')) {
            Schema::create('online_invitation_request_links', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('event_id')->index();
                $table->integer('category_id')->index();
                $table->string('token', 64)->unique();
                $table->boolean('is_active')->default(1);
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('use_count')->default(0);
                $table->integer('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('online_invitation_reminders')) {
            Schema::create('online_invitation_reminders', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('event_id')->index();
                $table->dateTime('remind_at');
                $table->text('message')->nullable();
                $table->string('audience', 20)->default('accepted'); // accepted|sent|all
                $table->string('status', 20)->default('scheduled'); // scheduled|sent|cancelled
                $table->dateTime('sent_at')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
                $table->index(['status', 'remind_at'], 'oi_reminder_due_idx');
            });
        }

        $perms = [
            'online_invitation_category',
            'online_invitation_template',
            'online_invitation_event',
            'online_invitation_send_invitation',
            'online_invitation_admit',
            'invitations_module',
        ];
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ([1, 2] as $roleId) {
            $role = Role::find($roleId);
            if (! $role) {
                continue;
            }
            foreach ($perms as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('online_invitation_reminders');
        Schema::dropIfExists('online_invitation_request_links');
        Schema::dropIfExists('online_invitations');
        Schema::dropIfExists('online_invitation_event_category');
        Schema::dropIfExists('online_invitation_events');
        Schema::dropIfExists('online_invitation_templates');
        Schema::dropIfExists('online_invitation_categories');
    }
}
