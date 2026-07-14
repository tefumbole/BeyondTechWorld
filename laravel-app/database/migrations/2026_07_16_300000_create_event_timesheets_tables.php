<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateEventTimesheetsTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_timesheets')) {
            Schema::create('event_timesheets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->uuid('assignment_id');
                $table->uuid('worker_profile_id');
                $table->string('status', 32)->default('draft');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->unsignedSmallInteger('total_days')->default(0);
                $table->decimal('total_hours', 6, 2)->default(0);
                $table->text('notes')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index('assignment_id');
            });
        }

        if (! Schema::hasTable('event_timesheet_entries')) {
            Schema::create('event_timesheet_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('timesheet_id');
                $table->date('work_date');
                $table->decimal('hours', 5, 2)->default(8);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('timesheet_id');
            });
        }

        foreach (['event_timesheets.view', 'event_timesheets.manage', 'event_timesheets.approve'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach (['event_timesheets.view', 'event_timesheets.manage', 'event_timesheets.approve'] as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_timesheet_entries');
        Schema::dropIfExists('event_timesheets');
    }
}
