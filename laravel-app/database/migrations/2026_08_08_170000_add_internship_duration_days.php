<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInternshipDurationDays extends Migration
{
    public function up()
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'internship_duration_days')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedSmallInteger('internship_duration_days')->nullable()->after('internship_program_id');
            });
        }

        if (Schema::hasTable('internship_enrolments') && ! Schema::hasColumn('internship_enrolments', 'planned_duration_days')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->unsignedSmallInteger('planned_duration_days')->default(180)->after('start_date');
            });
        }

        // Candidate-facing names should not advertise "180-Day".
        if (Schema::hasTable('internship_programs')) {
            $programs = DB::table('internship_programs')->select('id', 'name')->get();
            foreach ($programs as $program) {
                $clean = preg_replace('/^180[- ]Day\s+/i', '', (string) $program->name);
                $clean = preg_replace('/\s+Internship$/i', '', $clean);
                $clean = trim($clean);
                if ($clean !== '' && $clean !== $program->name) {
                    DB::table('internship_programs')->where('id', $program->id)->update(['name' => $clean]);
                }
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'internship_duration_days')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('internship_duration_days');
            });
        }
        if (Schema::hasTable('internship_enrolments') && Schema::hasColumn('internship_enrolments', 'planned_duration_days')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->dropColumn('planned_duration_days');
            });
        }
    }
}
