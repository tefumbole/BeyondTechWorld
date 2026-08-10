<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddConfiguredAtToWorkingWeek extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('be_working_week')) {
            return;
        }

        if (! Schema::hasColumn('be_working_week', 'configured_at')) {
            Schema::table('be_working_week', function (Blueprint $table) {
                $table->timestamp('configured_at')->nullable()->after('expected_hours_per_day');
            });
        }

        // Existing schedules were already in use — treat as configured so we only
        // force the wizard for new interns who have never saved a week.
        try {
            DB::table('be_working_week')
                ->whereNull('configured_at')
                ->update(['configured_at' => DB::raw('COALESCE(updated_at, created_at, NOW())')]);
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        if (Schema::hasTable('be_working_week') && Schema::hasColumn('be_working_week', 'configured_at')) {
            Schema::table('be_working_week', function (Blueprint $table) {
                $table->dropColumn('configured_at');
            });
        }
    }
}
