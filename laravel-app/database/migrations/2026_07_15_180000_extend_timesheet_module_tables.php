<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ExtendTimesheetModuleTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('be_timesheet_categories')) {
            Schema::create('be_timesheet_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('color', 20)->default('#3b82f6');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            foreach ([
                ['Development', 'Coding and engineering tasks', '#3b82f6'],
                ['Meetings', 'Internal and external meetings', '#eab308'],
                ['Administrative', 'HR, Finance, and Ops', '#a855f7'],
            ] as $row) {
                DB::table('be_timesheet_categories')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $row[0],
                    'description' => $row[1],
                    'color' => $row[2],
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('be_timesheet_activities')) {
            Schema::table('be_timesheet_activities', function (Blueprint $table) {
                if (! Schema::hasColumn('be_timesheet_activities', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                if (! Schema::hasColumn('be_timesheet_activities', 'category_id')) {
                    $table->uuid('category_id')->nullable()->index()->after('description');
                }
                if (! Schema::hasColumn('be_timesheet_activities', 'category')) {
                    $table->string('category', 100)->nullable()->after('category_id');
                }
                if (! Schema::hasColumn('be_timesheet_activities', 'owner_user_id')) {
                    $table->unsignedInteger('owner_user_id')->nullable()->index()->after('category');
                }
                if (! Schema::hasColumn('be_timesheet_activities', 'owner_be_user_id')) {
                    $table->uuid('owner_be_user_id')->nullable()->index()->after('owner_user_id');
                }
            });
        }

        if (Schema::hasTable('be_timesheet_entries')) {
            Schema::table('be_timesheet_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('be_timesheet_entries', 'user_id')) {
                    $table->unsignedInteger('user_id')->nullable()->index()->after('be_user_id');
                }
                if (! Schema::hasColumn('be_timesheet_entries', 'employee_name')) {
                    $table->string('employee_name')->nullable()->after('user_id');
                }
            });
            // Allow admin entries without portal user
            try {
                DB::statement('ALTER TABLE be_timesheet_entries MODIFY be_user_id CHAR(36) NULL');
            } catch (\Exception $e) {
                // ignore if already nullable / non-MySQL
            }
        }

        if (! Schema::hasTable('be_working_week')) {
            Schema::create('be_working_week', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('user_id')->nullable()->unique();
                $table->uuid('be_user_id')->nullable()->unique();
                $table->boolean('monday')->default(true);
                $table->boolean('tuesday')->default(true);
                $table->boolean('wednesday')->default(true);
                $table->boolean('thursday')->default(true);
                $table->boolean('friday')->default(true);
                $table->boolean('saturday')->default(false);
                $table->boolean('sunday')->default(false);
                $table->string('monday_start', 5)->nullable()->default('08:00');
                $table->string('monday_end', 5)->nullable()->default('17:00');
                $table->string('tuesday_start', 5)->nullable()->default('08:00');
                $table->string('tuesday_end', 5)->nullable()->default('17:00');
                $table->string('wednesday_start', 5)->nullable()->default('08:00');
                $table->string('wednesday_end', 5)->nullable()->default('17:00');
                $table->string('thursday_start', 5)->nullable()->default('08:00');
                $table->string('thursday_end', 5)->nullable()->default('17:00');
                $table->string('friday_start', 5)->nullable()->default('08:00');
                $table->string('friday_end', 5)->nullable()->default('17:00');
                $table->string('saturday_start', 5)->nullable();
                $table->string('saturday_end', 5)->nullable();
                $table->string('sunday_start', 5)->nullable();
                $table->string('sunday_end', 5)->nullable();
                $table->unsignedSmallInteger('lunch_break_minutes')->default(60);
                $table->decimal('expected_hours_per_day', 5, 2)->default(8);
                $table->timestamps();
            });
        }

        $this->seedPermissions();
    }

    private function seedPermissions()
    {
        $names = [
            'timesheets_module',
            'timesheets.employee',
            'timesheets.admin',
            'timesheets.view',
            'timesheets.manage',
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

    public function down()
    {
        Schema::dropIfExists('be_working_week');
        Schema::dropIfExists('be_timesheet_categories');
    }
}
