<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInternshipProgramsToJobsAndApplications extends Migration
{
    public function up()
    {
        if (Schema::hasTable('job_postings') && ! Schema::hasColumn('job_postings', 'internship_program_ids')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->text('internship_program_ids')->nullable()->after('posting_type');
            });
        }

        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'internship_program_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedBigInteger('internship_program_id')->nullable()->index()->after('job_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('job_postings') && Schema::hasColumn('job_postings', 'internship_program_ids')) {
            Schema::table('job_postings', function (Blueprint $table) {
                $table->dropColumn('internship_program_ids');
            });
        }
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'internship_program_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('internship_program_id');
            });
        }
    }
}
