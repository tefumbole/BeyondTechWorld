<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAntivirusDepartmentLines extends Migration
{
    public function up()
    {
        if (Schema::hasTable('antivirus_requests') && ! Schema::hasColumn('antivirus_requests', 'department_lines')) {
            Schema::table('antivirus_requests', function (Blueprint $table) {
                $table->text('department_lines')->nullable()->after('quantity');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('antivirus_requests') && Schema::hasColumn('antivirus_requests', 'department_lines')) {
            Schema::table('antivirus_requests', function (Blueprint $table) {
                $table->dropColumn('department_lines');
            });
        }
    }
}
