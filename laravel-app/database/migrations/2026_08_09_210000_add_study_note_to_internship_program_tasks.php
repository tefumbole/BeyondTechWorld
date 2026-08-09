<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStudyNoteToInternshipProgramTasks extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('internship_program_tasks')) {
            return;
        }
        if (! Schema::hasColumn('internship_program_tasks', 'study_note')) {
            Schema::table('internship_program_tasks', function (Blueprint $table) {
                $table->longText('study_note')->nullable()->after('objective');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_program_tasks') && Schema::hasColumn('internship_program_tasks', 'study_note')) {
            Schema::table('internship_program_tasks', function (Blueprint $table) {
                $table->dropColumn('study_note');
            });
        }
    }
}
