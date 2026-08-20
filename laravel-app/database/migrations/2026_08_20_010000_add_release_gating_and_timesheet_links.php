<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReleaseGatingAndTimesheetLinks extends Migration
{
    public function up()
    {
        if (Schema::hasTable('internship_enrolments') && ! Schema::hasColumn('internship_enrolments', 'next_release_date')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->date('next_release_date')->nullable()->after('last_release_date');
            });
        }

        if (Schema::hasTable('be_timesheet_entries')) {
            Schema::table('be_timesheet_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('be_timesheet_entries', 'assignment_id')) {
                    $table->unsignedBigInteger('assignment_id')->nullable()->index()->after('activity_name');
                }
                if (! Schema::hasColumn('be_timesheet_entries', 'approved_by')) {
                    $table->unsignedInteger('approved_by')->nullable()->after('status');
                }
                if (! Schema::hasColumn('be_timesheet_entries', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('be_timesheet_entries', 'review_note')) {
                    $table->text('review_note')->nullable()->after('approved_at');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('internship_enrolments') && Schema::hasColumn('internship_enrolments', 'next_release_date')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->dropColumn('next_release_date');
            });
        }

        if (Schema::hasTable('be_timesheet_entries')) {
            Schema::table('be_timesheet_entries', function (Blueprint $table) {
                foreach (['assignment_id', 'approved_by', 'approved_at', 'review_note'] as $col) {
                    if (Schema::hasColumn('be_timesheet_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
}
