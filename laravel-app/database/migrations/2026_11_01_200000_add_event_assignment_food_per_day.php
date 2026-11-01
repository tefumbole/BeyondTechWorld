<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventAssignmentFoodPerDay extends Migration
{
    public function up()
    {
        if (Schema::hasTable('event_assignments') && ! Schema::hasColumn('event_assignments', 'food_per_day')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->unsignedInteger('food_per_day')->default(0)->after('expected_total');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('event_assignments') && Schema::hasColumn('event_assignments', 'food_per_day')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->dropColumn('food_per_day');
            });
        }
    }
}
