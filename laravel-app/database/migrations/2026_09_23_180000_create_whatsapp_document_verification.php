<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWhatsappDocumentVerification extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_verification_challenges')) {
            Schema::create('whatsapp_verification_challenges', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('whatsapp_contact_id')->index();
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->string('identity_type', 32);
                $table->unsignedInteger('identity_id')->nullable();
                $table->string('purpose', 64);
                $table->string('otp_hash', 128);
                $table->timestamp('expires_at')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(5);
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('invalidated_at')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_verification_sessions')) {
            Schema::create('whatsapp_verification_sessions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('whatsapp_contact_id')->index();
                $table->unsignedInteger('conversation_id')->nullable();
                $table->unsignedInteger('challenge_id')->nullable();
                $table->string('identity_type', 32);
                $table->unsignedInteger('identity_id')->nullable();
                $table->string('purpose', 64);
                $table->string('method', 32)->default('otp');
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('invalidated_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_document_requests')) {
            Schema::create('whatsapp_document_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('whatsapp_contact_id')->nullable()->index();
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->string('identity_type', 32)->nullable();
                $table->unsignedInteger('identity_id')->nullable();
                $table->string('document_type', 64);
                $table->string('requested_reference', 64)->nullable();
                $table->string('resolved_record_id', 64)->nullable();
                $table->string('sensitivity', 32)->nullable();
                $table->unsignedInteger('challenge_id')->nullable();
                $table->unsignedInteger('session_id')->nullable();
                $table->string('status', 32);
                $table->string('failure_code', 64)->nullable();
                $table->string('public_message', 500)->nullable();
                $table->string('file_name', 180)->nullable();
                $table->string('local_path', 500)->nullable();
                $table->boolean('ephemeral')->default(false);
                $table->string('provider_message_id')->nullable()->unique();
                $table->unsignedInteger('outbound_message_id')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }
        $this->seedPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_document_requests');
        Schema::dropIfExists('whatsapp_verification_sessions');
        Schema::dropIfExists('whatsapp_verification_challenges');
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp.documents.view',
            'whatsapp.documents.manage',
            'whatsapp.documents.retry',
            'whatsapp.verification',
            'whatsapp.verification.invalidate',
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
