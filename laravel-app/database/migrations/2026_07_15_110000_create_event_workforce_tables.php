<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateEventWorkforceTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_worker_profiles')) {
            Schema::create('event_worker_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->uuid('be_user_id')->nullable()->index();
                $table->unsignedBigInteger('worker_category_id')->nullable()->index();
                $table->unsignedInteger('standard_daily_rate')->default(0);
                $table->unsignedInteger('standard_hourly_rate')->nullable();
                $table->unsignedInteger('overtime_rate')->nullable();
                $table->text('skills')->nullable();
                $table->string('specialization', 128)->nullable();
                $table->string('experience_level', 64)->nullable();
                $table->string('telephone', 64)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->text('mobile_money_details')->nullable();
                $table->text('bank_details')->nullable();
                $table->string('emergency_contact')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_labour_budgets')) {
            Schema::create('event_labour_budgets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->unique();
                $table->unsignedInteger('total_budget')->default(0);
                $table->unsignedInteger('allocated_amount')->default(0);
                $table->string('distribution_mode', 32)->default('manual');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_assignments')) {
            Schema::create('event_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->index();
                $table->uuid('worker_profile_id')->index();
                $table->string('assignment_role')->nullable();
                $table->boolean('is_supervisor')->default(false);
                $table->dateTime('reporting_time')->nullable();
                $table->date('work_start_date')->nullable();
                $table->date('work_end_date')->nullable();
                $table->unsignedSmallInteger('expected_days')->default(1);
                $table->unsignedInteger('default_daily_rate')->default(0);
                $table->unsignedInteger('event_daily_rate')->default(0);
                $table->unsignedInteger('hourly_rate')->nullable();
                $table->unsignedInteger('fixed_amount')->nullable();
                $table->string('compensation_method', 32)->default('daily');
                $table->unsignedInteger('expected_total')->default(0);
                $table->string('contract_status', 32)->default('draft');
                $table->string('attendance_status', 32)->default('pending');
                $table->string('timesheet_status', 32)->default('pending');
                $table->string('payment_status', 32)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        foreach (['event_workers.view', 'event_workers.create', 'event_workers.update', 'events.publish', 'events.unpublish'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            foreach (Role::whereIn('id', [1, 2])->get() as $role) {
                $perm = Permission::where('name', $name)->first();
                if ($perm && ! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_assignments');
        Schema::dropIfExists('event_labour_budgets');
        Schema::dropIfExists('event_worker_profiles');
    }
}
