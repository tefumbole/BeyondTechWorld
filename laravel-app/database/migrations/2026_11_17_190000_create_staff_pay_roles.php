<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStaffPayRoles extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cm_pay_roles')) {
            Schema::create('cm_pay_roles', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->string('pay_type', 16)->default('daily');
                $table->unsignedInteger('daily_rate')->default(0);
                $table->unsignedInteger('monthly_salary')->default(0);
                $table->string('salary_category_code', 40)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'pay_role_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedInteger('pay_role_id')->nullable();
                $table->string('pay_type', 16)->nullable();
                $table->unsignedInteger('daily_rate')->default(0);
                $table->unsignedInteger('monthly_salary')->default(0);
            });
        }
        if (class_exists(\App\Payroll\StaffPayRoles::class)) {
            \App\Payroll\StaffPayRoles::ensure();
        }
    }

    public function down()
    {
        Schema::dropIfExists('cm_pay_roles');
    }
}
