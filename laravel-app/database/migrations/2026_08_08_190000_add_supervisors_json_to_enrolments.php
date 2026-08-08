<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupervisorsJsonToEnrolments extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_enrolments') && ! Schema::hasColumn('internship_enrolments', 'supervisors_json')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->text('supervisors_json')->nullable()->after('supervisor_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_enrolments') && Schema::hasColumn('internship_enrolments', 'supervisors_json')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->dropColumn('supervisors_json');
            });
        }
    }
}
