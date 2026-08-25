<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimesheetOvertimeFlags extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('be_timesheet_entries')) {
            return;
        }

        Schema::table('be_timesheet_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('be_timesheet_entries', 'overtime_hours')) {
                $table->decimal('overtime_hours', 5, 2)->default(0)->after('hours');
            }
            if (! Schema::hasColumn('be_timesheet_entries', 'requires_ot_approval')) {
                $table->boolean('requires_ot_approval')->default(false)->after('overtime_hours');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('be_timesheet_entries')) {
            return;
        }

        Schema::table('be_timesheet_entries', function (Blueprint $table) {
            if (Schema::hasColumn('be_timesheet_entries', 'requires_ot_approval')) {
                $table->dropColumn('requires_ot_approval');
            }
            if (Schema::hasColumn('be_timesheet_entries', 'overtime_hours')) {
                $table->dropColumn('overtime_hours');
            }
        });
    }
}
