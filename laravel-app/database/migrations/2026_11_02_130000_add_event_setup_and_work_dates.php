<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventSetupAndWorkDates extends Migration
{
    public function up()
    {
        if (Schema::hasTable('btw_events') && ! Schema::hasColumn('btw_events', 'setup_dates')) {
            Schema::table('btw_events', function (Blueprint $table) {
                $table->text('setup_dates')->nullable();
            });
        }
        if (Schema::hasTable('event_assignments') && ! Schema::hasColumn('event_assignments', 'work_dates')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->text('work_dates')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('btw_events') && Schema::hasColumn('btw_events', 'setup_dates')) {
            Schema::table('btw_events', function (Blueprint $table) {
                $table->dropColumn('setup_dates');
            });
        }
        if (Schema::hasTable('event_assignments') && Schema::hasColumn('event_assignments', 'work_dates')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->dropColumn('work_dates');
            });
        }
    }
}
