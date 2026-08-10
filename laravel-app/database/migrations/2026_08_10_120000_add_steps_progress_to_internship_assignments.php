<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStepsProgressToInternshipAssignments extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('internship_task_assignments')) {
            return;
        }
        if (! Schema::hasColumn('internship_task_assignments', 'steps_progress_json')) {
            Schema::table('internship_task_assignments', function (Blueprint $table) {
                $table->text('steps_progress_json')->nullable()->after('attempt_count');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_task_assignments')
            && Schema::hasColumn('internship_task_assignments', 'steps_progress_json')) {
            Schema::table('internship_task_assignments', function (Blueprint $table) {
                $table->dropColumn('steps_progress_json');
            });
        }
    }
}
