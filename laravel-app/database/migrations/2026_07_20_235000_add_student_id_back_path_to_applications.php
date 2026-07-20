<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStudentIdBackPathToApplications extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('applications')) {
            return;
        }
        if (! Schema::hasColumn('applications', 'student_id_back_path')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->string('student_id_back_path')->nullable()->after('student_id_path');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'student_id_back_path')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('student_id_back_path');
            });
        }
    }
}
