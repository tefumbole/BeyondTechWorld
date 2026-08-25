<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each internship task can list the screenshots a student must attach, and
 * each uploaded file can carry the short note they wrote about that shot.
 */
class AddSubmissionEvidenceNotes extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_program_tasks')
            && ! Schema::hasColumn('internship_program_tasks', 'evidence_slots_json')) {
            Schema::table('internship_program_tasks', function (Blueprint $table) {
                $table->longText('evidence_slots_json')->nullable()->after('submission_requirements');
            });
        }

        if (Schema::hasTable('internship_submission_files')
            && ! Schema::hasColumn('internship_submission_files', 'caption')) {
            Schema::table('internship_submission_files', function (Blueprint $table) {
                $table->string('caption', 400)->nullable()->after('original_name');
                $table->unsignedSmallInteger('sort_order')->default(0)->after('caption');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('internship_program_tasks', 'evidence_slots_json')) {
            Schema::table('internship_program_tasks', function (Blueprint $table) {
                $table->dropColumn('evidence_slots_json');
            });
        }
        if (Schema::hasColumn('internship_submission_files', 'caption')) {
            Schema::table('internship_submission_files', function (Blueprint $table) {
                $table->dropColumn(['caption', 'sort_order']);
            });
        }
    }
}
