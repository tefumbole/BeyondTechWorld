<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayrollOtherDeduction extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cm_salary_profiles') && ! Schema::hasColumn('cm_salary_profiles', 'other_deduction')) {
            Schema::table('cm_salary_profiles', function (Blueprint $table) {
                $table->unsignedInteger('other_deduction')->default(0)->after('target_net');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('cm_salary_profiles') && Schema::hasColumn('cm_salary_profiles', 'other_deduction')) {
            Schema::table('cm_salary_profiles', function (Blueprint $table) {
                $table->dropColumn('other_deduction');
            });
        }
    }
}
