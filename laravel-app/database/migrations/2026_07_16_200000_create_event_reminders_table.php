<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateEventRemindersTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_reminders')) {
            Schema::create('event_reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->dateTime('remind_at');
                $table->text('message')->nullable();
                $table->string('channel', 32)->default('whatsapp');
                $table->string('recipient_type', 32)->default('all_workers');
                $table->string('recipient_phone', 64)->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->text('send_error')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['remind_at', 'sent_at']);
                $table->index('event_id');
            });
        }

        foreach (['event_reminders.view', 'event_reminders.create', 'event_reminders.send'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach (['event_reminders.view', 'event_reminders.create', 'event_reminders.send'] as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_reminders');
    }
}
