<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAutoAcceptToInternshipGrades extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('internship_grades')) {
            return;
        }

        Schema::table('internship_grades', function (Blueprint $table) {
            if (! Schema::hasColumn('internship_grades', 'auto_accepted')) {
                $table->boolean('auto_accepted')->default(false)->after('decision');
            }
        });

        // System auto-acceptance has no human grader.
        try {
            DB::statement('ALTER TABLE internship_grades MODIFY grader_id INT UNSIGNED NULL');
        } catch (\Exception $e) {
            // already nullable / non-MySQL driver
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_grades') && Schema::hasColumn('internship_grades', 'auto_accepted')) {
            Schema::table('internship_grades', function (Blueprint $table) {
                $table->dropColumn('auto_accepted');
            });
        }
    }
}
