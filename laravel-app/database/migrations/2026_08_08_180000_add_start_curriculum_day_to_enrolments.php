<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStartCurriculumDayToEnrolments extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_enrolments') && ! Schema::hasColumn('internship_enrolments', 'start_curriculum_day')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->unsignedSmallInteger('start_curriculum_day')->default(1)->after('planned_duration_days');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_enrolments') && Schema::hasColumn('internship_enrolments', 'start_curriculum_day')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->dropColumn('start_curriculum_day');
            });
        }
    }
}
