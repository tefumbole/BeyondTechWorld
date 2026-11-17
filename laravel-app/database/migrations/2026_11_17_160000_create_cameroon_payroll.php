<?php

use App\Payroll\CameroonPayrollRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateCameroonPayroll extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cm_payroll_rules')) {
            Schema::create('cm_payroll_rules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->longText('rules_json');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cm_salary_categories')) {
            Schema::create('cm_salary_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->string('code', 40);
                $table->string('name');
                $table->longText('components_json');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['cloud_tenant_id', 'code']);
            });
        }
        if (! Schema::hasTable('cm_salary_profiles')) {
            Schema::create('cm_salary_profiles', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->unsignedInteger('employee_id')->index();
                $table->unsignedInteger('category_id');
                $table->unsignedInteger('target_net');
                $table->string('cnps_number', 40)->nullable();
                $table->string('bank_name', 120)->nullable();
                $table->string('bank_account', 80)->nullable();
                $table->string('payment_method', 40)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cm_payroll_runs')) {
            Schema::create('cm_payroll_runs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
                $table->char('period', 7);
                $table->string('title');
                $table->string('status', 20)->default('calculated')->index();
                $table->unsignedInteger('revision_of_id')->nullable();
                $table->unsignedInteger('rule_id');
                $table->unsignedBigInteger('total_gross')->default(0);
                $table->unsignedBigInteger('total_net')->default(0);
                $table->unsignedBigInteger('total_employer')->default(0);
                $table->unsignedInteger('prepared_by')->nullable();
                $table->unsignedInteger('submitted_by')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->unsignedInteger('paid_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cm_payroll_lines')) {
            Schema::create('cm_payroll_lines', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('run_id')->index();
                $table->unsignedInteger('profile_id');
                $table->unsignedInteger('employee_id')->index();
                $table->string('payslip_no', 40)->unique();
                $table->string('verification_code', 24)->unique();
                $table->longText('snapshot_json');
                $table->unsignedInteger('gross');
                $table->unsignedInteger('net');
                $table->string('payment_status', 20)->default('pending');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cm_payroll_settings')) {
            Schema::create('cm_payroll_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable()->unique();
                $table->string('niu', 40)->nullable();
                $table->string('cnps_employer_no', 40)->nullable();
                $table->string('company_address', 255)->nullable();
                $table->decimal('accident_rate', 6, 4)->default(0.0175);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('cm_payroll_audits')) {
            Schema::create('cm_payroll_audits', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('action', 40);
                $table->string('subject_type', 40);
                $table->string('subject_id', 40);
                $table->text('detail')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable('cm_payroll_rules') && ! \DB::table('cm_payroll_rules')->where('effective_from', '2026-01-01')->exists()) {
            $rules = CameroonPayrollRules::twentyTwentySix();
            \DB::table('cm_payroll_rules')->insert([
                'name' => $rules['name'],
                'effective_from' => $rules['effective_from'],
                'rules_json' => json_encode($rules),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if (Schema::hasTable('cm_salary_categories') && \DB::table('cm_salary_categories')->count() === 0) {
            foreach (CameroonPayrollRules::categories() as $category) {
                \DB::table('cm_salary_categories')->insert([
                    'cloud_tenant_id' => null,
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'components_json' => json_encode($category['components']),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $permission = Permission::firstOrCreate(['name' => 'payroll-approve', 'guard_name' => 'web']);
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('cm_payroll_audits');
        Schema::dropIfExists('cm_payroll_lines');
        Schema::dropIfExists('cm_payroll_runs');
        Schema::dropIfExists('cm_salary_profiles');
        Schema::dropIfExists('cm_salary_categories');
        Schema::dropIfExists('cm_payroll_settings');
        Schema::dropIfExists('cm_payroll_rules');
    }
}
