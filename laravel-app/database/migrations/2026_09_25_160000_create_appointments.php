<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAppointments extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference', 32)->unique();
                $table->string('category', 64);
                $table->string('purpose', 255);
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedInteger('staff_user_id')->nullable()->index();
                $table->string('staff_label', 191)->nullable();
                $table->string('location', 191)->nullable();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at');
                $table->string('status', 32)->default('CONFIRMED')->index();
                $table->string('source', 32)->default('WHATSAPP');
                $table->string('customer_response', 32)->nullable();
                $table->string('google_event_id', 191)->nullable()->index();
                $table->string('google_sync_status', 32)->default('NOT_CONFIGURED');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('appointment_availability')) {
            Schema::create('appointment_availability', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('staff_user_id')->nullable()->index();
                $table->unsignedTinyInteger('weekday');
                $table->string('starts_time', 8);
                $table->string('ends_time', 8);
                $table->unsignedInteger('slot_minutes')->default(60);
                $table->string('location', 191)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('appointment_reminders')) {
            Schema::create('appointment_reminders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('appointment_id')->index();
                $table->string('interval_key', 16);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['appointment_id', 'interval_key']);
            });
        }
        if (! Schema::hasTable('appointment_activities')) {
            Schema::create('appointment_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('appointment_id')->nullable()->index();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->string('type', 64);
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('permissions')) {
            $perm = Permission::firstOrCreate(['name' => 'whatsapp.appointments', 'guard_name' => 'web']);
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
        Schema::dropIfExists('appointment_activities');
        Schema::dropIfExists('appointment_reminders');
        Schema::dropIfExists('appointment_availability');
        Schema::dropIfExists('appointments');
    }
}
