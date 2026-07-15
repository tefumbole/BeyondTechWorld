<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ExtendTaskManagerTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('tasks', 'start_time')) {
                    $table->time('start_time')->nullable()->after('start_date');
                }
                if (! Schema::hasColumn('tasks', 'color')) {
                    $table->string('color', 20)->nullable()->after('priority');
                }
                if (! Schema::hasColumn('tasks', 'is_scheduled')) {
                    $table->boolean('is_scheduled')->default(false)->after('notification_template');
                }
                if (! Schema::hasColumn('tasks', 'scheduled_for')) {
                    $table->dateTime('scheduled_for')->nullable()->after('is_scheduled');
                }
                if (! Schema::hasColumn('tasks', 'schedules_json')) {
                    $table->longText('schedules_json')->nullable()->after('scheduled_for');
                }
                if (! Schema::hasColumn('tasks', 'notifications_sent')) {
                    $table->boolean('notifications_sent')->default(false)->after('schedules_json');
                }
                if (! Schema::hasColumn('tasks', 'created_by_admin_id')) {
                    $table->unsignedInteger('created_by_admin_id')->nullable()->index()->after('created_by');
                }
            });
        }

        if (! Schema::hasTable('task_cc')) {
            Schema::create('task_cc', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id')->index();
                $table->uuid('user_id')->index();
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('task_reminders')) {
            Schema::create('task_reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id')->index();
                $table->dateTime('reminder_time');
                $table->boolean('is_sent')->default(false);
                $table->timestamps();
                $table->index('reminder_time');
            });
        }

        if (! Schema::hasTable('task_attachments')) {
            Schema::create('task_attachments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id')->index();
                $table->uuid('update_id')->nullable();
                $table->string('file_name');
                $table->text('file_url');
                $table->string('attachment_type', 50)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_message_templates')) {
            Schema::create('task_message_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('subject')->nullable();
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions()
    {
        $names = [
            'tasks_module',
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.settings',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRoles = Role::whereIn('id', [1, 2])->get();
        foreach ($adminRoles as $role) {
            foreach ($names as $name) {
                $perm = Permission::where('name', $name)->first();
                if ($perm && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('task_message_templates');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('task_cc');

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                foreach (['start_time', 'color', 'is_scheduled', 'scheduled_for', 'schedules_json', 'notifications_sent', 'created_by_admin_id'] as $col) {
                    if (Schema::hasColumn('tasks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        foreach ([
            'tasks_module', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.settings',
        ] as $name) {
            Permission::where('name', $name)->delete();
        }
    }
}
