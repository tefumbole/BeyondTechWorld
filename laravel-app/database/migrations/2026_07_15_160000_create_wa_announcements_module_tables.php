<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWaAnnouncementsModuleTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('wa_announcement_settings')) {
            Schema::create('wa_announcement_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('company_name')->nullable();
                $table->string('default_header')->nullable();
                $table->string('serial_prefix')->default('BEY/ANN/');
                $table->unsignedInteger('next_serial')->default(1);
                $table->unsignedTinyInteger('serial_padding')->default(4);
                $table->string('timezone')->default('Africa/Kigali');
                $table->string('timezone_offset', 10)->default('+02:00');
                $table->timestamps();
            });

            DB::table('wa_announcement_settings')->insert([
                'company_name' => 'Beyond Enterprise',
                'default_header' => 'Beyond Enterprise',
                'serial_prefix' => 'BEY/ANN/',
                'next_serial' => 1,
                'serial_padding' => 4,
                'timezone' => 'Africa/Kigali',
                'timezone_offset' => '+02:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('wa_announcement_categories')) {
            Schema::create('wa_announcement_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::table('wa_announcement_categories')->insert([
                'name' => 'General',
                'slug' => 'general',
                'description' => 'Default category',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('wa_announcement_templates')) {
            Schema::create('wa_announcement_templates', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unsignedInteger('category_id')->nullable()->index();
                $table->string('subject')->nullable();
                $table->string('header')->nullable();
                $table->longText('body')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wa_announcements')) {
            Schema::create('wa_announcements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('reference')->nullable()->index();
                $table->string('subject');
                $table->string('header')->nullable();
                $table->longText('body')->nullable();
                $table->string('footer')->nullable();
                $table->unsignedInteger('category_id')->nullable()->index();
                $table->string('status', 30)->default('draft')->index(); // draft|scheduled|sent|deleted
                $table->string('whatsapp_status', 30)->default('draft'); // draft|scheduled|pending|sent|partial
                $table->boolean('send_whatsapp')->default(true);
                $table->boolean('is_scheduled')->default(false);
                $table->dateTime('scheduled_for')->nullable()->index();
                $table->longText('schedules_json')->nullable();
                $table->longText('recipients_json')->nullable();
                $table->longText('cc_json')->nullable();
                $table->longText('send_results_json')->nullable();
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('cc_sent_count')->default(0);
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->uuid('cloned_from_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wa_announcement_reminders')) {
            Schema::create('wa_announcement_reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('announcement_id')->index();
                $table->dateTime('reminder_time')->index();
                $table->boolean('is_sent')->default(false);
                $table->timestamps();
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions()
    {
        $names = [
            'announcements_module',
            'announcements.view',
            'announcements.create',
            'announcements.delete',
            'announcements.settings',
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
            // Map legacy announcement_index role users into new module
            try {
                if ($role->hasPermissionTo('announcement_index')) {
                    $role->givePermissionTo('announcements_module');
                    $role->givePermissionTo('announcements.view');
                    $role->givePermissionTo('announcements.create');
                }
            } catch (\Exception $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('wa_announcement_reminders');
        Schema::dropIfExists('wa_announcements');
        Schema::dropIfExists('wa_announcement_templates');
        Schema::dropIfExists('wa_announcement_categories');
        Schema::dropIfExists('wa_announcement_settings');
    }
}
