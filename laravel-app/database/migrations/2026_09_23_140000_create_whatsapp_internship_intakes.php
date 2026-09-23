<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappInternshipIntakes extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_internship_intakes')) {
            Schema::create('whatsapp_internship_intakes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('conversation_id')->index();
                $table->unsignedInteger('intern_user_id')->nullable()->index();
                $table->unsignedInteger('enrolment_id')->nullable()->index();
                $table->unsignedInteger('assignment_id')->nullable()->index();
                $table->string('status', 32)->default('collecting');
                $table->text('text_body')->nullable();
                $table->text('links_json')->nullable();
                $table->string('validation_state', 32)->nullable();
                $table->unsignedInteger('submission_id')->nullable()->index();
                $table->text('provider_message_ids')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_internship_intake_files')) {
            Schema::create('whatsapp_internship_intake_files', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('intake_id')->index();
                $table->string('provider_message_id')->nullable()->unique();
                $table->string('kind', 16)->default('file');
                $table->string('disk', 32)->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->string('checksum', 64)->nullable();
                $table->text('url')->nullable();
                $table->string('status', 32)->default('pending');
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_internship_activities')) {
            Schema::create('whatsapp_internship_activities', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->unsignedInteger('intake_id')->nullable()->index();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->string('type', 64);
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('internship_submissions')) {
            Schema::table('internship_submissions', function (Blueprint $table) {
                if (! Schema::hasColumn('internship_submissions', 'source')) {
                    $table->string('source', 32)->nullable();
                }
                if (! Schema::hasColumn('internship_submissions', 'whatsapp_conversation_id')) {
                    $table->unsignedInteger('whatsapp_conversation_id')->nullable()->index();
                }
                if (! Schema::hasColumn('internship_submissions', 'whatsapp_message_ids')) {
                    $table->text('whatsapp_message_ids')->nullable();
                }
            });
        }
        $this->seedPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_internship_activities');
        Schema::dropIfExists('whatsapp_internship_intake_files');
        Schema::dropIfExists('whatsapp_internship_intakes');
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp.internship',
            'whatsapp.internship.view',
            'whatsapp.internship.submit',
            'whatsapp.internship.manage',
            'whatsapp.internship.media',
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
}
