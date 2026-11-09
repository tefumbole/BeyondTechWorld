<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventUnpackDates extends Migration
{
    public function up()
    {
        if (Schema::hasTable('btw_events') && ! Schema::hasColumn('btw_events', 'unpack_dates')) {
            Schema::table('btw_events', function (Blueprint $table) {
                $table->text('unpack_dates')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('btw_events') && Schema::hasColumn('btw_events', 'unpack_dates')) {
            Schema::table('btw_events', function (Blueprint $table) {
                $table->dropColumn('unpack_dates');
            });
        }
    }
}
