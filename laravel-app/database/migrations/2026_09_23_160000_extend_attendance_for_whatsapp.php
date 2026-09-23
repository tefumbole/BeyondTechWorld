<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ExtendAttendanceForWhatsapp extends Migration
{
    public function up()
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'intern_user_id')) {
                    $table->unsignedInteger('intern_user_id')->nullable()->index();
                }
                if (! Schema::hasColumn('attendances', 'source')) {
                    $table->string('source', 32)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'whatsapp_conversation_id')) {
                    $table->unsignedInteger('whatsapp_conversation_id')->nullable()->index();
                }
                if (! Schema::hasColumn('attendances', 'whatsapp_message_id')) {
                    $table->string('whatsapp_message_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('attendances', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'location_accuracy')) {
                    $table->unsignedInteger('location_accuracy')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'location_at')) {
                    $table->timestamp('location_at')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'location_status')) {
                    $table->string('location_status', 40)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'distance_meters')) {
                    $table->unsignedInteger('distance_meters')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'allowed_radius_meters')) {
                    $table->unsignedInteger('allowed_radius_meters')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'event_assignment_id')) {
                    $table->string('event_assignment_id', 64)->nullable()->index();
                }
            });
            if (Schema::getConnection()->getDoctrineColumn('attendances', 'checkout')->getNotnull()) {
                Schema::table('attendances', function (Blueprint $table) {
                    $table->string('checkout')->nullable()->change();
                });
            }
            if (Schema::getConnection()->getDoctrineColumn('attendances', 'employee_id')->getNotnull()) {
                Schema::table('attendances', function (Blueprint $table) {
                    $table->integer('employee_id')->nullable()->change();
                });
            }
        }
        if (Schema::hasTable('btw_events')) {
            Schema::table('btw_events', function (Blueprint $table) {
                if (! Schema::hasColumn('btw_events', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('btw_events', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('btw_events', 'geofence_radius_meters')) {
                    $table->unsignedInteger('geofence_radius_meters')->nullable();
                }
            });
        }
        if (! Schema::hasTable('attendance_correction_requests')) {
            Schema::create('attendance_correction_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('intern_user_id')->nullable();
                $table->unsignedInteger('attendance_id')->nullable()->index();
                $table->text('reason')->nullable();
                $table->string('requested_checkout', 8)->nullable();
                $table->string('status', 16)->default('PENDING');
                $table->unsignedInteger('approver_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedInteger('conversation_id')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('whatsapp_attendance_activities')) {
            Schema::create('whatsapp_attendance_activities', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('conversation_id')->nullable()->index();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->unsignedInteger('employee_id')->nullable();
                $table->unsignedInteger('attendance_id')->nullable();
                $table->string('type', 64);
                $table->text('body')->nullable();
                $table->timestamps();
            });
        }
        $this->seedPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_attendance_activities');
        Schema::dropIfExists('attendance_correction_requests');
    }

    private function seedPermissions()
    {
        $names = [
            'whatsapp.attendance',
            'whatsapp.attendance.view',
            'whatsapp.attendance.manage',
            'whatsapp.attendance.location',
            'whatsapp.attendance.corrections',
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
